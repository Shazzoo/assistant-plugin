<?php

namespace Shazzoo\Assistant\Avatar;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Shazzoo\Assistant\Models\AvatarSession;
use Shazzoo\Assistant\Models\AvatarSettings;
use Throwable;

/**
 * Bepaalt of de pratende avatar mag starten (aan, sleutel, budget, gelijktijdigheid)
 * en houdt de sessies bij. Kan de avatar niet starten, dan blijft de foto staan.
 */
class AvatarSessions
{
    /** Wat een minuut van de eigen avatar (FULL-modus) kost. De sandbox is gratis. */
    public const int CREDITS_PER_MINUTE = 2;

    public function __construct(private LiveAvatarClient $client) {}

    /**
     * Waarom de avatar nu niet kan starten, of null als hij kan.
     */
    public function unavailableReason(): ?string
    {
        $settings = AvatarSettings::current();

        return match (true) {
            ! $settings->enabled => 'De avatar staat uit.',
            ! $this->client->isConfigured() => 'Er is nog geen API-key van LiveAvatar ingesteld.',
            $settings->effectiveAvatarId() === null => 'Er is nog geen avatar-id voor de eigen avatar ingesteld.',
            ! $settings->sandbox && $this->minutesUsedThisMonth() >= $settings->monthly_budget_minutes => 'Het maandbudget van '.$settings->monthly_budget_minutes.' minuten is op.',
            AvatarSession::query()->running()->count() >= $settings->max_concurrent => 'Er lopen al '.$settings->max_concurrent.' sessies tegelijk.',
            ! $settings->sandbox && ($this->creditsLeft() ?? INF) < self::CREDITS_PER_MINUTE => 'Het saldo bij LiveAvatar is op.',
            default => null,
        };
    }

    /**
     * Start een sessie, of null als dat niet kan of mislukt.
     *
     * @return array{session_id: string, livekit_url: string, livekit_client_token: string, idle_stop_seconds: int, stop_url: string}|null
     */
    public function start(): ?array
    {
        if ($this->unavailableReason() !== null) {
            return null;
        }

        $settings = AvatarSettings::current();

        try {
            $session = $this->client->startSession($settings);
        } catch (Throwable $exception) {
            Log::warning('Pratende avatar kon niet starten', ['exception' => $exception->getMessage()]);

            return null;
        }

        AvatarSession::query()->create([
            'session_id' => $session['session_id'],
            'sandbox' => $settings->sandbox,
            'started_at' => now(),
        ]);

        return [
            'session_id' => $session['session_id'],
            'livekit_url' => $session['livekit_url'],
            'livekit_client_token' => $session['livekit_client_token'],
            'idle_stop_seconds' => $settings->idle_stop_seconds,
            // Voor het wegklikken van de pagina: navigator.sendBeacon zonder cookies of CSRF.
            'stop_url' => URL::temporarySignedRoute('assistant.avatar.stop', now()->addSeconds($settings->effectiveMaxSessionSeconds() + 60), ['sessionId' => $session['session_id']]),
        ];
    }

    public function stop(string $sessionId, string $reason = 'idle'): void
    {
        $session = AvatarSession::query()->where('session_id', $sessionId)->whereNull('ended_at')->first();

        if ($session === null) {
            return;
        }

        $session->update(['ended_at' => now(), 'end_reason' => $reason]);

        // Na een sessie is het saldo lager: de volgende keer opnieuw opvragen.
        Cache::forget($this->creditsCacheKey());

        try {
            $this->client->stopSession($sessionId, $reason === 'idle' ? 'IDLE_TIMEOUT' : 'USER_CLOSED');
        } catch (Throwable $exception) {
            Log::warning('Pratende avatar kon niet worden gestopt', ['session_id' => $sessionId, 'exception' => $exception->getMessage()]);
        }
    }

    /**
     * Sessies die nooit netjes zijn gestopt: LiveAvatar stopt ze zelf na de maximale duur.
     */
    public function closeStale(): int
    {
        return AvatarSession::query()
            ->whereNull('ended_at')
            ->get()
            ->filter(fn (AvatarSession $session): bool => $session->started_at->lte(now()->subSeconds($session->maxSeconds())))
            ->each(fn (AvatarSession $session) => $session->update([
                'ended_at' => $session->started_at->copy()->addSeconds($session->maxSeconds()),
                'end_reason' => 'max_duration',
            ]))
            ->count();
    }

    public function minutesUsedThisMonth(): int
    {
        return AvatarSession::query()
            ->where('sandbox', false)
            ->where('started_at', '>=', now()->startOfMonth())
            ->get()
            ->sum(fn (AvatarSession $session): int => $session->billedMinutes());
    }

    /**
     * Het saldo bij LiveAvatar, vijf minuten onthouden zodat niet elke paginaweergave een
     * aanvraag kost. Null als het niet op te vragen is; dat houdt de avatar niet tegen.
     */
    public function creditsLeft(): ?float
    {
        if (! $this->client->isConfigured()) {
            return null;
        }

        // Ook een mislukte opvraging wordt onthouden (als 'onbekend'), zodat een haperende API
        // niet elke paginaweergave vertraagt.
        $credits = Cache::remember($this->creditsCacheKey(), now()->addMinutes(5), function (): float|string {
            try {
                return $this->client->creditsLeft();
            } catch (Throwable $exception) {
                Log::warning('Saldo van LiveAvatar kon niet worden opgevraagd', ['exception' => $exception->getMessage()]);

                return 'onbekend';
            }
        });

        return is_float($credits) ? $credits : null;
    }

    /**
     * Per sleutel, zodat een nieuwe sleutel niet het saldo van de oude laat zien.
     */
    private function creditsCacheKey(): string
    {
        return 'assistant:avatar-credits:'.substr(hash('sha256', (string) AvatarSettings::current()->apiKey()), 0, 16);
    }
}

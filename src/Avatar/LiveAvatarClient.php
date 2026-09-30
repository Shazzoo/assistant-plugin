<?php

namespace Shazzoo\Assistant\Avatar;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Shazzoo\Assistant\Models\AvatarSettings;

/**
 * De server-kant van HeyGen LiveAvatar: sessies starten en stoppen.
 *
 * De API-key blijft op de server. De browser krijgt alleen de LiveKit-gegevens
 * om de video te ontvangen en de antwoorden van de assistent door te geven; hij praat niet
 * met de LiveAvatar-API zelf, dus er komen geen cookies van HeyGen aan te pas.
 *
 * @see https://docs.liveavatar.com/api-reference/sessions/create-session-token
 */
class LiveAvatarClient
{
    public function __construct(
        private ?string $apiKey,
        private string $baseUrl,
    ) {}

    public function isConfigured(): bool
    {
        return filled($this->apiKey);
    }

    /**
     * Maakt een sessie aan en start die. Vanaf hier rekent LiveAvatar minuten.
     *
     * @return array{session_id: string, livekit_url: string, livekit_client_token: string, max_session_duration: ?int}
     */
    public function startSession(AvatarSettings $settings): array
    {
        $token = $this->unwrap($this->api()->post('/v1/sessions/token', array_filter([
            'mode' => 'FULL',
            'avatar_id' => $settings->effectiveAvatarId(),
            'is_sandbox' => $settings->sandbox,
            'video_settings' => ['quality' => $settings->quality, 'encoding' => 'H264'],
            'max_session_duration' => $settings->effectiveMaxSessionSeconds(),
            'avatar_persona' => array_filter([
                'voice_id' => $settings->effectiveVoiceId(),
                'context_id' => $settings->context_id,
                'language' => $settings->language,
            ]) ?: null,
            // Geen gesprek via de microfoon: De assistent spreekt alleen uit wat wij sturen.
            'interactivity_type' => 'PUSH_TO_TALK',
        ], fn (mixed $value): bool => $value !== null)), 'token');

        $started = $this->unwrap(
            Http::baseUrl($this->baseUrl)->acceptJson()->timeout(30)->withToken($token['session_token'])->post('/v1/sessions/start'),
            'start',
        );

        return [
            'session_id' => (string) ($started['session_id'] ?? $token['session_id']),
            'livekit_url' => (string) $started['livekit_url'],
            'livekit_client_token' => (string) $started['livekit_client_token'],
            'max_session_duration' => isset($started['max_session_duration']) ? (int) $started['max_session_duration'] : null,
        ];
    }

    public function stopSession(string $sessionId, string $reason = 'USER_CLOSED'): void
    {
        $this->unwrap($this->api()->post('/v1/sessions/stop', ['session_id' => $sessionId, 'reason' => $reason]), 'stop', allowEmpty: true);
    }

    /**
     * Het saldo van het LiveAvatar-account in credits. Het totaal of het abonnement geeft de API niet.
     */
    public function creditsLeft(): float
    {
        $credits = $this->unwrap($this->api()->timeout(5)->get('/v1/users/credits'), 'credits')['credits_left'] ?? null;

        if (! is_numeric($credits)) {
            throw new RuntimeException('LiveAvatar (credits) gaf geen saldo terug.');
        }

        return (float) $credits;
    }

    private function api(): PendingRequest
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('Er is geen API-key van LiveAvatar ingesteld.');
        }

        return Http::baseUrl($this->baseUrl)
            ->acceptJson()
            ->timeout(20)
            ->withHeaders(['X-API-KEY' => $this->apiKey]);
    }

    /**
     * LiveAvatar verpakt antwoorden als {code, data, message}.
     *
     * @return array<string, mixed>
     */
    private function unwrap(Response $response, string $step, bool $allowEmpty = false): array
    {
        if ($response->failed()) {
            throw new RuntimeException("LiveAvatar ({$step}) gaf HTTP {$response->status()}: {$response->body()}");
        }

        $data = $response->json('data');

        if (! is_array($data) && ! $allowEmpty) {
            throw new RuntimeException("LiveAvatar ({$step}) gaf geen gegevens terug: {$response->body()}");
        }

        return is_array($data) ? $data : [];
    }
}

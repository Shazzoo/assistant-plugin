<?php

namespace Shazzoo\Assistant\Models;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Shazzoo\Assistant\Models\Concerns\TracksEditor;

/**
 * Instellingen van de pratende avatar. Er is precies één rij; de beginwaarden komen uit config/assistant.php.
 *
 * De API-key wordt versleuteld opgeslagen, is niet mass-assignable en komt nooit in toArray()
 * (dus ook niet in een formulier). Zet hem alleen via setApiKey(). LIVEAVATAR_API_KEY in .env blijft als reserve werken.
 */
#[Fillable(['enabled', 'sandbox', 'avatar_id', 'voice_id', 'context_id', 'language', 'quality', 'idle_stop_seconds', 'max_session_seconds', 'max_concurrent', 'monthly_budget_minutes'])]
#[Hidden(['api_key'])]
class AvatarSettings extends Model
{
    use TracksEditor;

    protected $table = 'assistant_avatar_settings';

    /** De testavatar die LiveAvatar in de sandbox toestaat. */
    public const string SANDBOX_AVATAR_ID = 'dd73ea75-1218-4ef3-92ce-606d5f7fbc0a';

    /** De stem die bij Wayne hoort; zonder stem spreekt de avatar niets uit. */
    public const string SANDBOX_VOICE_ID = 'c2527536-6d1f-4412-a643-53a3497dada9';

    public const int SANDBOX_MAX_SESSION_SECONDS = 60;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'api_key' => 'encrypted',
            'enabled' => 'boolean',
            'sandbox' => 'boolean',
            'idle_stop_seconds' => 'integer',
            'max_session_seconds' => 'integer',
            'max_concurrent' => 'integer',
            'monthly_budget_minutes' => 'integer',
        ];
    }

    public static function current(): self
    {
        $config = config('assistant.avatar');

        return self::query()->firstOrCreate(['id' => 1], [
            'enabled' => $config['enabled'],
            'sandbox' => $config['sandbox'],
            'avatar_id' => $config['avatar_id'],
            'voice_id' => $config['voice_id'],
            'context_id' => $config['context_id'],
            'language' => $config['language'],
            'quality' => $config['quality'],
            'idle_stop_seconds' => $config['idle_stop_seconds'],
            'max_session_seconds' => $config['max_session_seconds'],
            'max_concurrent' => $config['max_concurrent'],
            'monthly_budget_minutes' => $config['monthly_budget_minutes'],
        ]);
    }

    /**
     * De sleutel uit het dashboard, anders die uit .env.
     */
    public function apiKey(): ?string
    {
        return $this->storedApiKey() ?? config('assistant.avatar.api_key');
    }

    /**
     * Na een nieuwe APP_KEY is de oude sleutel niet meer te ontsleutelen: dan geldt hij als leeg,
     * zodat de avatar uitvalt in plaats van de chat.
     */
    private function storedApiKey(): ?string
    {
        try {
            return filled($this->api_key) ? $this->api_key : null;
        } catch (DecryptException) {
            return null;
        }
    }

    public function setApiKey(?string $apiKey): void
    {
        $this->api_key = filled($apiKey) ? trim($apiKey) : null;
        $this->save();
    }

    /**
     * Wat het dashboard over de sleutel laat zien: nooit de sleutel zelf.
     */
    public function apiKeyHint(): string
    {
        $stored = $this->storedApiKey();

        return match (true) {
            $stored !== null => 'Ingesteld in het dashboard, eindigt op …'.mb_substr($stored, -4),
            filled(config('assistant.avatar.api_key')) => 'Ingesteld in .env, eindigt op …'.mb_substr((string) config('assistant.avatar.api_key'), -4),
            default => 'Nog niet ingesteld',
        };
    }

    /**
     * In de sandbox mag alleen de testavatar; daarbuiten de ingestelde (echte de assistent).
     */
    public function effectiveAvatarId(): ?string
    {
        return $this->sandbox ? self::SANDBOX_AVATAR_ID : $this->avatar_id;
    }

    /**
     * De ingestelde stem; in de sandbox zonder ingestelde stem die van Wayne.
     */
    public function effectiveVoiceId(): ?string
    {
        return $this->voice_id ?: ($this->sandbox ? self::SANDBOX_VOICE_ID : null);
    }

    /**
     * Een sandbox-sessie mag van LiveAvatar hooguit een minuut duren.
     */
    public function effectiveMaxSessionSeconds(): int
    {
        return $this->sandbox ? min(self::SANDBOX_MAX_SESSION_SECONDS, $this->max_session_seconds) : $this->max_session_seconds;
    }
}

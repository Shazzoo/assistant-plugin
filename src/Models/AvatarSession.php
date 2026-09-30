<?php

namespace Shazzoo\Assistant\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Shazzoo\Assistant\Database\Factories\AvatarSessionFactory;

/**
 * Een sessie van de pratende avatar. Houdt alleen tijden bij, voor budget en gelijktijdigheid.
 */
#[UseFactory(AvatarSessionFactory::class)]
#[Fillable(['session_id', 'sandbox', 'started_at', 'ended_at', 'end_reason'])]
class AvatarSession extends Model
{
    /** @use HasFactory<AvatarSessionFactory> */
    use HasFactory;

    protected $table = 'assistant_avatar_sessions';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sandbox' => 'boolean',
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    /**
     * Sessies die volgens ons nog lopen: niet gestopt en jonger dan de maximale duur.
     */
    #[Scope]
    protected function running(Builder $query): void
    {
        $max = AvatarSettings::current()->max_session_seconds;
        $sandboxMax = min(AvatarSettings::SANDBOX_MAX_SESSION_SECONDS, $max);

        $query->whereNull('ended_at')->where(fn (Builder $query) => $query
            ->where(fn (Builder $query) => $query->where('sandbox', false)->where('started_at', '>', now()->subSeconds($max)))
            ->orWhere(fn (Builder $query) => $query->where('sandbox', true)->where('started_at', '>', now()->subSeconds($sandboxMax))));
    }

    /**
     * Hoe lang deze sessie hooguit kan lopen; LiveAvatar stopt hem daarna zelf.
     */
    public function maxSeconds(): int
    {
        $max = AvatarSettings::current()->max_session_seconds;

        return $this->sandbox ? min(AvatarSettings::SANDBOX_MAX_SESSION_SECONDS, $max) : $max;
    }

    /**
     * Gefactureerde minuten: per begonnen minuut, zoals LiveAvatar rekent. Sandbox telt niet.
     */
    public function billedMinutes(): int
    {
        if ($this->sandbox) {
            return 0;
        }

        $end = $this->ended_at ?? now();
        $maxEnd = $this->started_at->copy()->addSeconds($this->maxSeconds());

        return max(1, (int) ceil($this->started_at->diffInSeconds($end->min($maxEnd)) / 60));
    }
}

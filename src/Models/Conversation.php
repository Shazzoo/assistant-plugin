<?php

namespace Shazzoo\Assistant\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Shazzoo\Assistant\Database\Factories\ConversationFactory;

/**
 * Een geschoonde transcriptie van één gesprek met de assistent.
 */
#[UseFactory(ConversationFactory::class)]
#[Fillable(['session_number', 'page', 'language'])]
class Conversation extends Model
{
    /** @use HasFactory<ConversationFactory> */
    use HasFactory, MassPrunable;

    protected $table = 'assistant_conversations';

    /**
     * @return HasMany<ConversationMessage, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(ConversationMessage::class);
    }

    /**
     * Transcripties ouder dan de bewaartermijn; de berichten gaan mee via de foreign key.
     */
    public function prunable(): Builder
    {
        return static::where('created_at', '<=', now()->subDays((int) config('assistant.transcripts.retention_days')));
    }
}

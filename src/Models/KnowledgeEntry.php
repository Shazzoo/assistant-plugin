<?php

namespace Shazzoo\Assistant\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Shazzoo\Assistant\Database\Factories\KnowledgeEntryFactory;
use Shazzoo\Assistant\KnowledgeStatus;
use Shazzoo\Assistant\Models\Concerns\TracksEditor;

#[UseFactory(KnowledgeEntryFactory::class)]
#[Fillable(['id', 'question', 'variants', 'answer', 'category', 'status', 'source', 'valid_until', 'owner', 'checked_at'])]
class KnowledgeEntry extends Model
{
    /** @use HasFactory<KnowledgeEntryFactory> */
    use HasFactory, TracksEditor;

    protected $table = 'assistant_knowledge_entries';

    /**
     * Herkent nog niet ingevulde plaatshouders zoals [BEDRAG], [NAAM] of [1 WERKDAG].
     */
    public const string PLACEHOLDER_PATTERN = '/\[[A-Z0-9][A-Z0-9_ ]*\]/';

    protected static function booted(): void
    {
        // Wie een regel in /admin opslaat, heeft hem gecontroleerd.
        static::saving(function (self $entry): void {
            if (auth()->check()) {
                $entry->checked_at = today();
            }
        });
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => KnowledgeStatus::class,
            'valid_until' => 'date',
            'checked_at' => 'date',
        ];
    }

    /**
     * Regels die de assistent als antwoord mag gebruiken: vrij of voorwaarde, met een antwoord,
     * niet verlopen, en bij "voorwaarde" altijd met een geldig_tot-datum.
     */
    #[Scope]
    protected function usable(Builder $query): void
    {
        $query
            ->whereIn('status', [KnowledgeStatus::Free, KnowledgeStatus::Conditional])
            ->whereNotNull('answer')
            ->where('answer', '!=', '')
            ->where(fn (Builder $query) => $query
                ->whereDate('valid_until', '>=', today())
                ->orWhere(fn (Builder $query) => $query
                    ->whereNull('valid_until')
                    ->where('status', KnowledgeStatus::Free)));
    }

    /**
     * Onderwerpen waarover de assistent nooit zelf antwoordt, maar doorverbindt.
     */
    #[Scope]
    protected function forbidden(Builder $query): void
    {
        $query->where('status', KnowledgeStatus::Never);
    }

    /**
     * Een antwoord met een plaatshouder is nog niet af en mag niet naar buiten.
     */
    public function hasPlaceholder(): bool
    {
        return preg_match(self::PLACEHOLDER_PATTERN, (string) $this->answer) === 1;
    }

    /**
     * Waarom de assistent deze regel niet als antwoord gebruikt, of null als ze dat wel doet.
     * Volgt dezelfde regels als de scope usable() plus de controle op plaatshouders.
     */
    public function usageProblem(): ?string
    {
        return match (true) {
            $this->status === KnowledgeStatus::Never => 'Status nooit: De assistent verbindt door',
            trim((string) $this->answer) === '' => 'Nog geen antwoord',
            $this->hasPlaceholder() => 'Antwoord bevat nog een plaatshouder',
            $this->valid_until?->lt(today()) => 'Verlopen op '.$this->valid_until->format('j-n-Y'),
            $this->status === KnowledgeStatus::Conditional && $this->valid_until === null => 'Voorwaarde zonder geldig_tot',
            default => null,
        };
    }

    public function isUsedByAssistant(): bool
    {
        return $this->usageProblem() === null;
    }

    /**
     * @return list<string>
     */
    public function variantList(): array
    {
        return array_values(array_filter(array_map('trim', explode(';', (string) $this->variants))));
    }
}

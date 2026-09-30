<?php

namespace Shazzoo\Assistant\Filament\Actions;

use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\DB;
use Shazzoo\Assistant\Filament\Tabs\KnowledgeTab;
use Shazzoo\Assistant\KnowledgeStatus;
use Shazzoo\Assistant\Models\KnowledgeEntry;
use Shazzoo\Assistant\Models\UnansweredQuestion;
use Shazzoo\Assistant\UnansweredStatus;

/**
 * Van onbeantwoorde vraag naar regel in het kennisbestand, in één stap.
 * De vraag telt daarna als afgehandeld, met de nieuwe regel als besluit.
 */
class AddToKnowledgeAction
{
    public static function make(): Action
    {
        return Action::make('addToKnowledge')
            ->label('Toevoegen aan kennisbestand')
            ->icon(Heroicon::OutlinedBookOpen)
            ->color('primary')
            ->modalHeading('Toevoegen aan het kennisbestand')
            ->modalDescription('De vraag van de bezoeker staat al ingevuld. Schrijf het antwoord dat de assistent voortaan mag geven.')
            ->modalSubmitActionLabel('Toevoegen en afhandelen')
            ->modalWidth('3xl')
            ->hidden(fn (UnansweredQuestion $record): bool => $record->status === UnansweredStatus::Resolved)
            ->fillForm(fn (UnansweredQuestion $record): array => [
                'question' => $record->question,
                'status' => KnowledgeStatus::Free->value,
                'source' => 'opgave organisatie',
                'owner' => auth()->user()?->name,
            ])
            ->schema(KnowledgeTab::formComponents())
            ->action(function (array $data, UnansweredQuestion $record): void {
                $entry = DB::transaction(function () use ($data, $record): KnowledgeEntry {
                    $entry = KnowledgeEntry::query()->create($data);

                    $record->update([
                        'status' => UnansweredStatus::Resolved,
                        'assignee' => $record->assignee ?? auth()->user()?->name,
                        'resolution' => "Regel {$entry->id} toegevoegd aan het kennisbestand.",
                        'resolved_at' => now(),
                    ]);

                    return $entry;
                });

                Notification::make()
                    ->title("Regel {$entry->id} toegevoegd")
                    ->body($entry->isUsedByAssistant() ? 'De assistent gebruikt dit antwoord vanaf de volgende vraag.' : 'De assistent gebruikt deze regel nog niet: '.mb_lcfirst((string) $entry->usageProblem()).'.')
                    ->success()
                    ->send();
            });
    }
}

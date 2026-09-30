<?php

namespace Shazzoo\Assistant\Filament\Tabs;

use Filament\Actions\ViewAction;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Shazzoo\Assistant\Models\Conversation;
use Shazzoo\Assistant\UnansweredReason;

/**
 * De geschoonde gesprekken, om te lezen hoe bezoekers de assistent gebruiken.
 */
final class ConversationsTab
{
    /**
     * @return array<int, mixed>
     */
    public static function infolistComponents(): array
    {
        return [
            Section::make()
                ->columns(4)
                ->schema([
                    TextEntry::make('created_at')
                        ->label('Begonnen')
                        ->dateTime('j F Y, H:i'),
                    TextEntry::make('page')
                        ->label('Pagina')
                        ->placeholder('onbekend'),
                    TextEntry::make('language')
                        ->label('Taal')
                        ->placeholder('onbekend'),
                    TextEntry::make('session_number')
                        ->label('Sessienummer')
                        ->copyable()
                        ->fontFamily('mono')
                        ->size('xs'),
                ]),

            RepeatableEntry::make('messages')
                ->label('Gesprek')
                // Vers ophalen: de tabel laadt per gesprek alleen de eerste vraag.
                ->state(fn (Conversation $record) => $record->messages()->orderBy('id')->get())
                ->contained(false)
                ->schema([
                    TextEntry::make('content')
                        ->label(fn ($record): string => $record->role === 'user' ? 'Bezoeker' : 'Assistent')
                        ->markdown()
                        ->prose(),
                    TextEntry::make('meta')
                        ->hiddenLabel()
                        ->state(fn ($record): ?string => $record->role === 'user' ? null : collect([
                            UnansweredReason::tryFrom((string) $record->status)?->getLabel() ?? ($record->status === 'fout' ? 'Fout bij ophalen antwoord' : null),
                            $record->source ? "Bron: {$record->source}" : null,
                        ])->filter()->implode(' · '))
                        ->color(fn ($record): string => UnansweredReason::tryFrom((string) $record->status) ? 'danger' : 'gray')
                        ->size('xs')
                        ->hidden(fn ($record): bool => $record->role === 'user' || ($record->status === 'beantwoord' && $record->source === null)),
                ]),
        ];
    }

    public static function table(Table $table): Table
    {
        $unanswered = array_map(fn (UnansweredReason $reason): string => $reason->value, UnansweredReason::cases());

        return $table
            ->query(Conversation::query())
            ->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->withCount([
                    'messages as questions_count' => fn (Builder $query) => $query->where('role', 'user'),
                    'messages as unanswered_count' => fn (Builder $query) => $query->whereIn('status', $unanswered),
                ])
                ->with(['messages' => fn (HasMany $query) => $query->where('role', 'user')->orderBy('id')->limit(1)]))
            ->columns([
                TextColumn::make('created_at')
                    ->label('Begonnen')
                    ->dateTime('j M Y, H:i')
                    ->sortable(),
                TextColumn::make('first_question')
                    ->label('Eerste vraag')
                    ->state(fn ($record): ?string => $record->messages->first()?->content)
                    ->limit(90)
                    ->wrap(),
                TextColumn::make('questions_count')
                    ->label('Vragen')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('unanswered_count')
                    ->label('Onbeantwoord')
                    ->numeric()
                    ->sortable()
                    ->color(fn (int $state): string => $state > 0 ? 'danger' : 'gray'),
                TextColumn::make('page')
                    ->label('Pagina'),
                TextColumn::make('language')
                    ->label('Taal')
                    ->badge()
                    ->color('gray'),
            ])
            ->filters([
                Filter::make('met_onbeantwoord')
                    ->label('Met een onbeantwoorde vraag')
                    ->query(fn (Builder $query) => $query->whereHas('messages', fn (Builder $query) => $query->whereIn('status', $unanswered))),
                SelectFilter::make('language')
                    ->label('Taal')
                    ->options(['nl' => 'Nederlands', 'en' => 'Engels', 'de' => 'Duits', 'fr' => 'Frans', 'anders' => 'Anders']),
            ])
            ->recordActions([
                ViewAction::make()->label('Lezen')->schema(self::infolistComponents())->modalWidth('3xl'),
            ])
            ->emptyStateHeading('Nog geen gesprekken')
            ->emptyStateDescription('Zodra bezoekers de assistent iets vragen, verschijnen hier de geschoonde gesprekken.');
    }
}

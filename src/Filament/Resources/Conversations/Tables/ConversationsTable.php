<?php

namespace Shazzoo\Assistant\Filament\Resources\Conversations\Tables;

use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Shazzoo\Assistant\UnansweredReason;

class ConversationsTable
{
    public static function configure(Table $table): Table
    {
        $unanswered = array_map(fn (UnansweredReason $reason): string => $reason->value, UnansweredReason::cases());

        return $table
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
                ViewAction::make()->label('Lezen'),
            ])
            ->emptyStateHeading('Nog geen gesprekken')
            ->emptyStateDescription('Zodra bezoekers de assistent iets vragen, verschijnen hier de geschoonde gesprekken.');
    }
}

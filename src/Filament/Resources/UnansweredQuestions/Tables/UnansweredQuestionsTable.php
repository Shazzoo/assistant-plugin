<?php

namespace Shazzoo\Assistant\Filament\Resources\UnansweredQuestions\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Shazzoo\Assistant\Filament\Actions\AddToKnowledgeAction;
use Shazzoo\Assistant\UnansweredReason;
use Shazzoo\Assistant\UnansweredStatus;

class UnansweredQuestionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            // Op frequentie, niet op datum: dat vertelt waar je moet beginnen.
            ->defaultSort('times_asked', 'desc')
            ->columns([
                TextColumn::make('question')
                    ->label('Vraag')
                    ->searchable()
                    ->wrap(),
                TextColumn::make('times_asked')
                    ->label('Keer')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('reason')
                    ->label('Reden')
                    ->badge(),
                TextColumn::make('status')
                    ->badge(),
                TextColumn::make('assignee')
                    ->label('Wie')
                    ->placeholder('—'),
                TextColumn::make('page')
                    ->label('Pagina')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('first_seen_at')
                    ->label('Eerst gezien')
                    ->since()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('last_seen_at')
                    ->label('Laatst gezien')
                    ->since()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(UnansweredStatus::class)
                    ->multiple()
                    ->default([UnansweredStatus::New->value, UnansweredStatus::InProgress->value]),
                SelectFilter::make('reason')
                    ->label('Reden')
                    ->options(UnansweredReason::class),
            ])
            ->recordActions([
                AddToKnowledgeAction::make()->label('Naar kennisbestand')->iconButton()->tooltip('Toevoegen aan kennisbestand'),
                EditAction::make()->label('Afhandelen'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('Geen openstaande vragen')
            ->emptyStateDescription('Alles wat de assistent niet kon beantwoorden is afgehandeld.');
    }
}

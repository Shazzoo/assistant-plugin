<?php

namespace Shazzoo\Assistant\Filament\Tabs;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Shazzoo\Assistant\Filament\Actions\AddToKnowledgeAction;
use Shazzoo\Assistant\Models\UnansweredQuestion;
use Shazzoo\Assistant\UnansweredReason;
use Shazzoo\Assistant\UnansweredStatus;

/**
 * Vragen waarop de assistent het antwoord schuldig bleef: de inhoudsopgave voor het kennisbestand en de site.
 */
final class UnansweredTab
{
    public const string DESCRIPTION = 'Vragen waarop de assistent het antwoord schuldig bleef, in de woorden van de bezoeker. Dit is de inhoudsopgave voor het kennisbestand en de site.';

    /**
     * @return array<int, mixed>
     */
    public static function formComponents(): array
    {
        return [
            Section::make('De vraag')
                ->description('Zoals de bezoeker hem stelde, na het schonen van contactgegevens.')
                ->columns(3)
                ->schema([
                    TextEntry::make('question')
                        ->hiddenLabel()
                        ->size('lg')
                        ->columnSpanFull(),
                    TextEntry::make('times_asked')
                        ->label('Aantal keer gesteld'),
                    TextEntry::make('first_seen_at')
                        ->label('Eerst gezien')
                        ->dateTime('j F Y, H:i'),
                    TextEntry::make('last_seen_at')
                        ->label('Laatst gezien')
                        ->since(),
                    TextEntry::make('reason')
                        ->label('Reden')
                        ->badge()
                        ->helperText(fn ($record) => $record?->reason?->getDescription()),
                    TextEntry::make('page')
                        ->label('Pagina')
                        ->placeholder('onbekend'),
                ]),

            Section::make('Afhandeling')
                ->columns(2)
                ->schema([
                    Select::make('status')
                        ->options(UnansweredStatus::class)
                        ->required()
                        ->live(),
                    TextInput::make('assignee')
                        ->label('Wie pakt het op')
                        ->maxLength(100)
                        ->default(fn (): ?string => auth()->user()?->name),
                    Textarea::make('resolution')
                        ->label('Wat is er veranderd')
                        ->helperText('Welke regel in het kennisbestand, welke alinea op welke pagina, of "bewust niet". Afvinken zonder wijziging is geen afhandeling.')
                        ->rows(3)
                        ->required(fn (Get $get): bool => $get('status') === UnansweredStatus::Resolved || $get('status') === UnansweredStatus::Resolved->value)
                        ->columnSpanFull(),
                ]),
        ];
    }

    public static function table(Table $table): Table
    {
        return $table
            ->query(UnansweredQuestion::query())
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
                EditAction::make()
                    ->label('Afhandelen')
                    ->schema(self::formComponents())
                    ->modalWidth('3xl')
                    ->mutateDataUsing(function (array $data, UnansweredQuestion $record): array {
                        $status = $data['status'] instanceof UnansweredStatus ? $data['status'] : UnansweredStatus::tryFrom((string) $data['status']);
                        $data['resolved_at'] = $status === UnansweredStatus::Resolved ? ($record->resolved_at ?? now()) : null;

                        return $data;
                    }),
                DeleteAction::make()
                    ->label('Verwijderen')
                    ->modalDescription('Bijvoorbeeld als de vraag na het schonen onleesbaar is geworden. Wat je afhandelt, bewaar je juist: dat is de besluitenlijst.'),
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

<?php

namespace Shazzoo\Assistant\Filament\Sections;

use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Shazzoo\Assistant\Models\ClientReference;

/**
 * Klanten die de assistent mag noemen, en hoe.
 */
final class ReferencesSection
{
    public const string DESCRIPTION = 'Leg bij elke vrijgave vast waar de toestemming staat. Zonder vrijgave noemt de assistent alleen sector en omvang.';

    /**
     * @return array<int, mixed>
     */
    public static function formComponents(): array
    {
        return [
            TextInput::make('client')->label('Klant')->required()->maxLength(255),
            TextInput::make('sector')->label('Sector')->required()->maxLength(255),
            TextInput::make('size')->label('Omvang')->placeholder('bijvoorbeeld 140 medewerkers')->maxLength(255),
            TextInput::make('recorded_in')->label('Toestemming vastgelegd in')->placeholder('bijvoorbeeld mail van de contactpersoon')->maxLength(255),
            Textarea::make('what_we_did')->label('Wat we deden')->rows(2)->columnSpanFull(),
            Toggle::make('name_released')
                ->label('Klantnaam mag genoemd worden')
                ->helperText('Uit: de assistent noemt alleen sector en omvang.')
                ->live(),
            Toggle::make('figures_released')->label('Cijfers mogen genoemd worden')->live(),
            DatePicker::make('released_at')
                ->label('Vrijgegeven op')
                ->required(fn (Get $get): bool => (bool) $get('name_released') || (bool) $get('figures_released'))
                ->native(false)
                ->displayFormat('j F Y'),
        ];
    }

    public static function table(Table $table): Table
    {
        return $table
            ->query(ClientReference::query())
            ->defaultSort('client')
            ->columns([
                TextColumn::make('client')->label('Klant')->searchable()->sortable(),
                TextColumn::make('public_name')
                    ->label('De assistent noemt dit als')
                    ->state(fn (ClientReference $record): string => $record->publicName())
                    ->wrap(),
                IconColumn::make('figures_released')->label('Cijfers')->boolean(),
                TextColumn::make('released_at')->label('Vrijgegeven op')->date('j-n-Y')->placeholder('—'),
                TextColumn::make('updated_at')
                    ->label('Gewijzigd')
                    ->since()
                    ->description(fn (ClientReference $record): ?string => $record->updated_by)
                    ->toggleable(),
            ])
            ->headerActions([
                CreateAction::make()->label('Nieuwe referentie')->model(ClientReference::class)->schema([Grid::make(2)->schema(self::formComponents())]),
            ])
            ->recordActions([
                EditAction::make()->label('Bewerken')->schema([Grid::make(2)->schema(self::formComponents())]),
                DeleteAction::make()->label('Verwijderen'),
            ]);
    }
}

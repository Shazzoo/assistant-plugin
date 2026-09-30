<?php

namespace Shazzoo\Assistant\Filament\Resources\ClientReferences;

use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Shazzoo\Assistant\Filament\Resources\ClientReferences\Pages\ManageClientReferences;
use Shazzoo\Assistant\Models\ClientReference;
use UnitEnum;

/**
 * Referenties: een klantnaam alleen als de klant die schriftelijk heeft vrijgegeven.
 */
class ClientReferenceResource extends Resource
{
    protected static ?string $model = ClientReference::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static string|UnitEnum|null $navigationGroup = 'Assistent';

    protected static ?string $slug = 'assistent/referenties';

    protected static ?string $modelLabel = 'referentie';

    protected static ?string $pluralModelLabel = 'referenties';

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $recordTitleAttribute = 'client';

    protected static ?int $navigationSort = 12;

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('client')->label('Klant')->required()->maxLength(255),
            TextInput::make('sector')->label('Sector')->required()->maxLength(255),
            TextInput::make('size')->label('Omvang')->placeholder('bijvoorbeeld 140 medewerkers')->maxLength(255),
            TextInput::make('recorded_in')->label('Toestemming vastgelegd in')->placeholder('bijvoorbeeld mail van de contactpersoon')->maxLength(255),
            Textarea::make('what_we_did')->label('Wat we deden')->rows(2)->columnSpanFull(),
            Toggle::make('name_released')
                ->label('Klantnaam mag genoemd worden')
                ->helperText('Uit: De assistent noemt alleen sector en omvang.')
                ->live(),
            Toggle::make('figures_released')->label('Cijfers mogen genoemd worden')->live(),
            DatePicker::make('released_at')
                ->label('Vrijgegeven op')
                ->required(fn (Get $get): bool => (bool) $get('name_released') || (bool) $get('figures_released'))
                ->native(false)
                ->displayFormat('j F Y'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
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
            ->recordActions([
                EditAction::make()->label('Bewerken'),
                DeleteAction::make()->label('Verwijderen'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageClientReferences::route('/'),
        ];
    }
}

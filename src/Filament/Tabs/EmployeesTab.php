<?php

namespace Shazzoo\Assistant\Filament\Tabs;

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
use Shazzoo\Assistant\Models\Employee;

/**
 * Medewerkers die de assistent mag noemen. Persoonsgegevens: alleen met akkoord van de medewerker.
 */
final class EmployeesTab
{
    public const string DESCRIPTION = "De assistent noemt alleen wat hier staat: naam, functie, vakgebied, ervaring en hobby's. Nooit beschikbaarheid of privégegevens.";

    /**
     * @return array<int, mixed>
     */
    public static function formComponents(): array
    {
        return [
            TextInput::make('name')->label('Naam')->required()->maxLength(255),
            TextInput::make('role')->label('Functie')->required()->maxLength(255),
            TextInput::make('expertise')->label('Vakgebied')->maxLength(255),
            TextInput::make('years_of_experience')->label('Jaren ervaring')->numeric()->minValue(0)->maxValue(60),
            TextInput::make('hobby')->label("Hobby's")->maxLength(255)->columnSpanFull(),
            Toggle::make('may_be_named')
                ->label('De assistent mag deze medewerker noemen')
                ->helperText('Alleen aanzetten als de medewerker heeft gezien wat hier staat en akkoord is.')
                ->live(),
            DatePicker::make('consented_at')
                ->label('Akkoord gegeven op')
                ->required(fn (Get $get): bool => (bool) $get('may_be_named'))
                ->native(false)
                ->displayFormat('j F Y'),
            Textarea::make('notes')->label('Toelichting')->rows(2)->columnSpanFull(),
        ];
    }

    public static function table(Table $table): Table
    {
        return $table
            ->query(Employee::query())
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')->label('Naam')->searchable()->sortable(),
                TextColumn::make('role')->label('Functie')->wrap(),
                TextColumn::make('expertise')->label('Vakgebied')->wrap()->toggleable(),
                IconColumn::make('used')
                    ->label('Assistent')
                    ->state(fn (Employee $record): bool => $record->usageProblem() === null)
                    ->boolean()
                    ->tooltip(fn (Employee $record): string => $record->usageProblem() ?? 'De assistent mag deze medewerker noemen'),
                TextColumn::make('consented_at')->label('Akkoord op')->date('j-n-Y')->placeholder('—'),
                TextColumn::make('updated_at')
                    ->label('Gewijzigd')
                    ->since()
                    ->description(fn (Employee $record): ?string => $record->updated_by)
                    ->toggleable(),
            ])
            ->headerActions([
                CreateAction::make()->label('Nieuwe medewerker')->model(Employee::class)->schema([Grid::make(2)->schema(self::formComponents())]),
            ])
            ->recordActions([
                EditAction::make()->label('Bewerken')->schema([Grid::make(2)->schema(self::formComponents())]),
                DeleteAction::make()->label('Verwijderen')->modalDescription('Doe dit ook als een medewerker vraagt om uit de assistent gehaald te worden.'),
            ]);
    }
}

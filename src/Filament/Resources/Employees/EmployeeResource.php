<?php

namespace Shazzoo\Assistant\Filament\Resources\Employees;

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
use Shazzoo\Assistant\Filament\Resources\Employees\Pages\ManageEmployees;
use Shazzoo\Assistant\Models\Employee;
use UnitEnum;

/**
 * Medewerkers die de assistent mag noemen. Persoonsgegevens: alleen met akkoord van de medewerker.
 */
class EmployeeResource extends Resource
{
    protected static ?string $model = Employee::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static string|UnitEnum|null $navigationGroup = 'Assistent';

    protected static ?string $slug = 'assistent/medewerkers';

    protected static ?string $modelLabel = 'medewerker';

    protected static ?string $pluralModelLabel = 'medewerkers';

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?int $navigationSort = 11;

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
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
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
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
            ->recordActions([
                EditAction::make()->label('Bewerken'),
                DeleteAction::make()->label('Verwijderen')->modalDescription('Doe dit ook als een medewerker vraagt om uit de assistent gehaald te worden.'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageEmployees::route('/'),
        ];
    }
}

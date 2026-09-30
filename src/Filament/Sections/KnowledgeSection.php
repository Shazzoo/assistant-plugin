<?php

namespace Shazzoo\Assistant\Filament\Sections;

use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Shazzoo\Assistant\KnowledgeStatus;
use Shazzoo\Assistant\Models\KnowledgeEntry;

/**
 * Het kennisbestand: wat de assistent naast de website mag weten.
 */
final class KnowledgeSection
{
    public const array CATEGORIES = ['Diensten', 'Tarieven', 'Mensen', 'Referenties', 'Techniek', 'Werkwijze', 'Juridisch', 'Assistent'];

    /**
     * De vaste categorieën plus die al in gebruik zijn, zodat een geïmporteerde categorie kiesbaar blijft.
     *
     * @return array<string, string>
     */
    public static function categoryOptions(): array
    {
        $categories = collect(self::CATEGORIES)
            ->merge(KnowledgeEntry::query()->whereNotNull('category')->distinct()->pluck('category'))
            ->unique()
            ->values()
            ->all();

        return array_combine($categories, $categories);
    }

    /**
     * Ook gebruikt door "Toevoegen aan kennisbestand" bij onbeantwoorde vragen.
     *
     * @return list<Section>
     */
    public static function formComponents(): array
    {
        return [
            Section::make('Vraag en antwoord')
                ->schema([
                    TextInput::make('question')
                        ->label('Vraag')
                        ->helperText('Zoals een bezoeker hem stelt.')
                        ->required()
                        ->maxLength(255),
                    TagsInput::make('variants')
                        ->label('Ook gesteld als')
                        ->helperText('Andere formuleringen van dezelfde vraag. Druk op Enter na elke variant.')
                        ->separator(';')
                        ->splitKeys(['Enter', ';']),
                    Textarea::make('answer')
                        ->label('Antwoord')
                        ->helperText('In de je-vorm. Dit is wat de assistent mag zeggen. Laat [VUL IN] staan zolang iets nog niet vaststaat: dan gebruikt de assistent de regel niet.')
                        ->rows(4)
                        ->required(fn (Get $get): bool => self::statusOf($get) !== KnowledgeStatus::Never)
                        ->live(onBlur: true)
                        ->hint(fn (?string $state): ?string => $state !== null && preg_match(KnowledgeEntry::PLACEHOLDER_PATTERN, $state) ? 'Bevat nog een plaatshouder: de assistent gebruikt deze regel nog niet' : null)
                        ->hintColor('warning'),
                ]),

            Section::make('Wanneer de assistent dit mag zeggen')
                ->columns(2)
                ->schema([
                    Select::make('status')
                        ->options([
                            KnowledgeStatus::Free->value => 'vrij: de assistent antwoordt',
                            KnowledgeStatus::Conditional->value => 'voorwaarde: alleen tot geldig_tot',
                            KnowledgeStatus::Never->value => 'nooit: de assistent verbindt door',
                        ])
                        ->default(KnowledgeStatus::Free->value)
                        ->required()
                        ->live(),
                    DatePicker::make('valid_until')
                        ->label('Geldig tot')
                        ->helperText('Daarna gebruikt de assistent de regel niet meer. Verplicht bij "voorwaarde".')
                        ->required(fn (Get $get): bool => self::statusOf($get) === KnowledgeStatus::Conditional)
                        ->native(false)
                        ->displayFormat('j F Y'),
                    Select::make('category')
                        ->label('Categorie')
                        ->options(fn (): array => self::categoryOptions()),
                    TextInput::make('source')
                        ->label('Bron')
                        ->helperText('Wordt niet onder het antwoord getoond; alleen voor jezelf.')
                        ->default('opgave organisatie')
                        ->maxLength(255),
                    TextInput::make('owner')
                        ->label('Eigenaar')
                        ->helperText('Wie dit antwoord bijhoudt.')
                        ->default(fn (): ?string => auth()->user()?->name)
                        ->maxLength(100),
                ]),
        ];
    }

    public static function table(Table $table): Table
    {
        return $table
            ->query(KnowledgeEntry::query())
            ->defaultSort('id')
            ->columns([
                TextColumn::make('id')->label('#')->sortable(),
                TextColumn::make('question')->label('Vraag')->searchable(['question', 'variants', 'answer'])->wrap(),
                TextColumn::make('category')->label('Categorie')->badge()->color('gray'),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (KnowledgeStatus $state): string => $state->value)
                    ->color(fn (KnowledgeStatus $state): string => match ($state) {
                        KnowledgeStatus::Free => 'success',
                        KnowledgeStatus::Conditional => 'warning',
                        KnowledgeStatus::Never => 'danger',
                    }),
                IconColumn::make('used')
                    ->label('Assistent')
                    ->state(fn (KnowledgeEntry $record): bool => $record->isUsedByAssistant())
                    ->boolean()
                    ->tooltip(fn (KnowledgeEntry $record): string => $record->usageProblem() ?? 'De assistent gebruikt deze regel'),
                TextColumn::make('problem')
                    ->label('Waarom niet')
                    ->state(fn (KnowledgeEntry $record): ?string => $record->usageProblem())
                    ->color('danger')
                    ->placeholder('—')
                    ->wrap(),
                TextColumn::make('valid_until')->label('Geldig tot')->date('j-n-Y')->sortable()->placeholder('—'),
                TextColumn::make('owner')->label('Eigenaar')->placeholder('—')->toggleable(),
                TextColumn::make('updated_at')
                    ->label('Gewijzigd')
                    ->since()
                    ->description(fn (KnowledgeEntry $record): ?string => $record->updated_by)
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                TernaryFilter::make('used')
                    ->label('Gebruikt door de assistent')
                    ->queries(
                        true: fn (Builder $query) => $query->whereIn('id', self::usedIds()),
                        false: fn (Builder $query) => $query->whereNotIn('id', self::usedIds()),
                    ),
                SelectFilter::make('category')
                    ->label('Categorie')
                    ->options(fn (): array => self::categoryOptions()),
                SelectFilter::make('status')
                    ->options(collect(KnowledgeStatus::cases())->mapWithKeys(fn (KnowledgeStatus $status): array => [$status->value => $status->value])->all()),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Nieuwe regel')
                    ->model(KnowledgeEntry::class)
                    ->schema(self::formComponents())
                    ->modalWidth('3xl'),
            ])
            ->recordActions([
                EditAction::make()
                    ->label('Bewerken')
                    ->schema(self::formComponents())
                    ->modalWidth('3xl')
                    ->modalDescription(fn (KnowledgeEntry $record): string => $record->usageProblem() === null
                        ? 'De assistent gebruikt deze regel.'
                        : 'De assistent gebruikt deze regel nu niet: '.mb_lcfirst($record->usageProblem()).'.'),
                DeleteAction::make()->label('Verwijderen'),
            ])
            ->paginated([25, 50, 'all'])
            ->emptyStateHeading('Het kennisbestand is leeg')
            ->emptyStateDescription('Voeg een eerste vraag en antwoord toe.');
    }

    private static function statusOf(Get $get): ?KnowledgeStatus
    {
        $status = $get('status');

        return $status instanceof KnowledgeStatus ? $status : KnowledgeStatus::tryFrom((string) $status);
    }

    /**
     * @return list<int>
     */
    private static function usedIds(): array
    {
        return KnowledgeEntry::query()->get()->filter->isUsedByAssistant()->pluck('id')->values()->all();
    }
}

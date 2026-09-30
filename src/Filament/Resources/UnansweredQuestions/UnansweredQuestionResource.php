<?php

namespace Shazzoo\Assistant\Filament\Resources\UnansweredQuestions;

use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Shazzoo\Assistant\Filament\Resources\UnansweredQuestions\Pages\EditUnansweredQuestion;
use Shazzoo\Assistant\Filament\Resources\UnansweredQuestions\Pages\ListUnansweredQuestions;
use Shazzoo\Assistant\Filament\Resources\UnansweredQuestions\Schemas\UnansweredQuestionForm;
use Shazzoo\Assistant\Filament\Resources\UnansweredQuestions\Tables\UnansweredQuestionsTable;
use Shazzoo\Assistant\Models\UnansweredQuestion;
use Shazzoo\Assistant\UnansweredStatus;
use UnitEnum;

class UnansweredQuestionResource extends Resource
{
    protected static ?string $model = UnansweredQuestion::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQuestionMarkCircle;

    protected static string|UnitEnum|null $navigationGroup = 'Assistent';

    protected static ?string $slug = 'assistent/onbeantwoorde-vragen';

    protected static ?string $recordTitleAttribute = 'question';

    protected static ?string $modelLabel = 'onbeantwoorde vraag';

    protected static ?string $pluralModelLabel = 'onbeantwoorde vragen';

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return UnansweredQuestionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return UnansweredQuestionsTable::configure($table);
    }

    public static function getNavigationBadge(): ?string
    {
        $new = UnansweredQuestion::query()->where('status', UnansweredStatus::New)->count();

        return $new > 0 ? (string) $new : null;
    }

    public static function getNavigationBadgeColor(): string
    {
        return 'danger';
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUnansweredQuestions::route('/'),
            'edit' => EditUnansweredQuestion::route('/{record}/edit'),
        ];
    }
}

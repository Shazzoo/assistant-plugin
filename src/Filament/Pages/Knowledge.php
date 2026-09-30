<?php

namespace Shazzoo\Assistant\Filament\Pages;

use BackedEnum;
use Filament\Support\Icons\Heroicon;
use Shazzoo\Assistant\Filament\Tabs\EmployeesTab;
use Shazzoo\Assistant\Filament\Tabs\KnowledgeTab;
use Shazzoo\Assistant\Filament\Tabs\ReferencesTab;

class Knowledge extends TabbedTablePage
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBookOpen;

    protected static ?string $navigationLabel = 'Kennis';

    protected static ?string $title = 'Kennis';

    protected static ?string $slug = 'kennis';

    protected static ?int $navigationSort = 2;

    public static function tabs(): array
    {
        return [
            'kennisbestand' => ['Kennisbestand', KnowledgeTab::class, 'Wat de assistent naast de website mag weten. Staat een antwoord nergens, dan zegt hij dat hij het niet weet.'],
            'medewerkers' => ['Medewerkers', EmployeesTab::class, EmployeesTab::DESCRIPTION],
            'referenties' => ['Referenties', ReferencesTab::class, ReferencesTab::DESCRIPTION],
        ];
    }
}

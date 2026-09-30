<?php

namespace Shazzoo\Assistant\Filament\Pages;

use BackedEnum;
use Filament\Support\Icons\Heroicon;
use Shazzoo\Assistant\Filament\Sections\EmployeesSection;
use Shazzoo\Assistant\Filament\Sections\KnowledgeSection;
use Shazzoo\Assistant\Filament\Sections\ReferencesSection;

class Knowledge extends AccordionPage
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBookOpen;

    protected static ?string $navigationLabel = 'Kennis';

    protected static ?string $title = 'Kennis';

    protected static ?string $slug = 'kennis';

    protected static ?int $navigationSort = 2;

    public static function sections(): array
    {
        return [
            'kennisbestand' => ['Kennisbestand', KnowledgeSection::class, 'Wat de assistent naast de website mag weten. Staat een antwoord nergens, dan zegt hij dat hij het niet weet.'],
            'medewerkers' => ['Medewerkers', EmployeesSection::class, EmployeesSection::DESCRIPTION],
            'referenties' => ['Referenties', ReferencesSection::class, ReferencesSection::DESCRIPTION],
        ];
    }
}

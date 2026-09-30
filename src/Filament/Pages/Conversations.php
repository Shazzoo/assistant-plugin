<?php

namespace Shazzoo\Assistant\Filament\Pages;

use BackedEnum;
use Filament\Support\Icons\Heroicon;
use Shazzoo\Assistant\Filament\Sections\ConversationsSection;
use Shazzoo\Assistant\Filament\Sections\UnansweredSection;

class Conversations extends AccordionPage
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    protected static ?string $navigationLabel = 'Gesprekken';

    protected static ?string $title = 'Gesprekken';

    protected static ?string $slug = 'gesprekken';

    protected static ?int $navigationSort = 1;

    public static function sections(): array
    {
        return [
            'gesprekken' => ['Gesprekken', ConversationsSection::class, 'Geschoond: contactgegevens zijn eruit gehaald. Na '.config('assistant.transcripts.retention_days').' dagen worden ze verwijderd.'],
            'onbeantwoord' => ['Onbeantwoorde vragen', UnansweredSection::class, UnansweredSection::DESCRIPTION],
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return Assistant::getNavigationBadge();
    }

    public static function getNavigationBadgeColor(): string
    {
        return 'danger';
    }

    public function sectionBadge(string $section): ?string
    {
        return $section === 'onbeantwoord' ? Assistant::getNavigationBadge() : null;
    }
}

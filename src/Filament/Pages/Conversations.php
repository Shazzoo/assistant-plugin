<?php

namespace Shazzoo\Assistant\Filament\Pages;

use BackedEnum;
use Filament\Support\Icons\Heroicon;
use Shazzoo\Assistant\Filament\Tabs\ConversationsTab;
use Shazzoo\Assistant\Filament\Tabs\UnansweredTab;

class Conversations extends TabbedTablePage
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    protected static ?string $navigationLabel = 'Gesprekken';

    protected static ?string $title = 'Gesprekken';

    protected static ?string $slug = 'gesprekken';

    protected static ?int $navigationSort = 1;

    public static function tabs(): array
    {
        return [
            'gesprekken' => ['Gesprekken', ConversationsTab::class, 'Geschoond: contactgegevens zijn eruit gehaald. Na '.config('assistant.transcripts.retention_days').' dagen worden ze verwijderd.'],
            'onbeantwoord' => ['Onbeantwoorde vragen', UnansweredTab::class, UnansweredTab::DESCRIPTION],
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

    public function tabBadge(string $tab): ?string
    {
        return $tab === 'onbeantwoord' ? Assistant::getNavigationBadge() : null;
    }
}

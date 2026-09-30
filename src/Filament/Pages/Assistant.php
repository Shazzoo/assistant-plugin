<?php

namespace Shazzoo\Assistant\Filament\Pages;

use BackedEnum;
use Filament\Clusters\Cluster;
use Filament\Support\Icons\Heroicon;
use Shazzoo\Assistant\Models\UnansweredQuestion;
use Shazzoo\Assistant\UnansweredStatus;
use UnitEnum;

/**
 * Eén menu-item onder Plugins, met de pagina's van de assistent eronder: Gesprekken, Kennis en Instellingen.
 *
 * Staat bij de pagina's, omdat core in een plugin alleen Resources, Pages en Widgets ontdekt.
 */
class Assistant extends Cluster
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    protected static string|UnitEnum|null $navigationGroup = 'Plugins';

    protected static ?string $navigationLabel = 'AI-assistent';

    protected static ?string $clusterBreadcrumb = 'AI-assistent';

    protected static ?string $slug = 'assistent';

    protected static ?int $navigationSort = 40;

    public static function getNavigationBadge(): ?string
    {
        $new = UnansweredQuestion::query()->where('status', UnansweredStatus::New)->count();

        return $new > 0 ? (string) $new : null;
    }

    public static function getNavigationBadgeColor(): string
    {
        return 'danger';
    }
}

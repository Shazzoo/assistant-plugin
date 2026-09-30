<?php

namespace Shazzoo\Assistant\Filament\Pages;

use Filament\Pages\Page;

/**
 * Een pagina met een uitklapbaar blok per tabel. Elk blok is een klasse met een statische table().
 */
abstract class AccordionPage extends Page
{
    protected static ?string $cluster = Assistant::class;

    protected string $view = 'assistant::filament.accordion';

    /**
     * De blokken: sleutel => [kop, klasse met table(), omschrijving].
     *
     * @return array<string, array{0: string, 1: class-string, 2: ?string}>
     */
    abstract public static function sections(): array;

    /**
     * Het getal naast de kop van een blok, of null.
     */
    public function sectionBadge(string $section): ?string
    {
        return null;
    }
}

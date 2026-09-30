<?php

namespace Shazzoo\Assistant;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasLabel;

/**
 * De drie soorten "geen antwoord" uit het transcriptieontwerp (node 05).
 */
enum UnansweredReason: string implements HasColor, HasDescription, HasLabel
{
    /** Zij wist het niet: het kennisbestand mist een regel. */
    case NoSource = 'geen_bron';

    /** Zij vond het wel, maar de bron geeft geen uitsluitsel. */
    case UnclearSource = 'bron_onduidelijk';

    /** Zij mocht het niet zeggen: het valt buiten de kaders. */
    case OutOfBounds = 'buiten_de_kaders';

    public function label(): string
    {
        return match ($this) {
            self::NoSource => 'Geen bron',
            self::UnclearSource => 'Bron onduidelijk',
            self::OutOfBounds => 'Buiten de kaders',
        };
    }

    public function getLabel(): string
    {
        return $this->label();
    }

    public function getDescription(): string
    {
        return match ($this) {
            self::NoSource => 'Het kennisbestand mist een regel. Eén regel toevoegen en de vraag komt niet meer terug.',
            self::UnclearSource => 'De tekst op de site of in het kennisbestand geeft geen uitsluitsel. Pas die tekst aan.',
            self::OutOfBounds => 'Goed gedrag. Maar komt hij vaak langs, dan zegt dat iets over wat nog niet op de site staat.',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::NoSource => 'danger',
            self::UnclearSource => 'warning',
            self::OutOfBounds => 'gray',
        };
    }
}

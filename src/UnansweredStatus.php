<?php

namespace Shazzoo\Assistant;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Levensloop van een onbeantwoorde vraag in de werkvoorraad.
 */
enum UnansweredStatus: string implements HasColor, HasLabel
{
    case New = 'nieuw';
    case InProgress = 'in_behandeling';
    case Resolved = 'afgehandeld';

    public function getLabel(): string
    {
        return match ($this) {
            self::New => 'Nieuw',
            self::InProgress => 'In behandeling',
            self::Resolved => 'Afgehandeld',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::New => 'danger',
            self::InProgress => 'warning',
            self::Resolved => 'success',
        };
    }
}

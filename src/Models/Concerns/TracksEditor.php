<?php

namespace Shazzoo\Assistant\Models\Concerns;

/**
 * Legt vast wie een regel van het kennisbestand het laatst wijzigde (bij opslaan vanuit /admin).
 */
trait TracksEditor
{
    protected static function bootTracksEditor(): void
    {
        static::saving(function (self $model): void {
            if (auth()->check()) {
                $model->updated_by = auth()->user()->name;
            }
        });
    }
}

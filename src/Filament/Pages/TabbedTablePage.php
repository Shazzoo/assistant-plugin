<?php

namespace Shazzoo\Assistant\Filament\Pages;

use Filament\Pages\Page;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Livewire\Attributes\Url;

/**
 * Een pagina met een tabel per tabblad. Elk tabblad is een klasse met een statische table().
 */
abstract class TabbedTablePage extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $cluster = Assistant::class;

    protected string $view = 'assistant::filament.tabbed';

    #[Url]
    public string $tab = '';

    /**
     * De tabbladen: sleutel => [label, klasse met table(), omschrijving].
     *
     * @return array<string, array{0: string, 1: class-string, 2: ?string}>
     */
    abstract public static function tabs(): array;

    public function mount(): void
    {
        if (! array_key_exists($this->tab, static::tabs())) {
            $this->tab = array_key_first(static::tabs());
        }
    }

    public function updatedTab(): void
    {
        $this->resetTableSearch();
        $this->tableSort = null;
        $this->resetTable();
    }

    public function getSubheading(): ?string
    {
        return static::tabs()[$this->tab][2] ?? null;
    }

    /**
     * Het getal naast een tabblad, of null.
     */
    public function tabBadge(string $tab): ?string
    {
        return null;
    }

    public function table(Table $table): Table
    {
        $tabs = static::tabs();

        return ($tabs[$this->tab] ?? reset($tabs))[1]::table($table);
    }
}

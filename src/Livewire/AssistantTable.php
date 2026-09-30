<?php

namespace Shazzoo\Assistant\Livewire;

use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Shazzoo\Assistant\Filament\Sections\ConversationsSection;
use Shazzoo\Assistant\Filament\Sections\EmployeesSection;
use Shazzoo\Assistant\Filament\Sections\KnowledgeSection;
use Shazzoo\Assistant\Filament\Sections\ReferencesSection;
use Shazzoo\Assistant\Filament\Sections\UnansweredSection;

/**
 * Eén tabel uit het beheer, los te tonen, zodat een pagina er meerdere onder elkaar kan hebben.
 */
class AssistantTable extends Component implements HasActions, HasSchemas, HasTable
{
    use InteractsWithActions;
    use InteractsWithSchemas;
    use InteractsWithTable;

    /** De klassen die een tabel mogen leveren. */
    public const array SOURCES = [
        ConversationsSection::class,
        UnansweredSection::class,
        KnowledgeSection::class,
        EmployeesSection::class,
        ReferencesSection::class,
    ];

    /** @var class-string */
    #[Locked]
    public string $source;

    public function mount(string $source): void
    {
        abort_unless(in_array($source, self::SOURCES, true), 404);

        $this->source = $source;
    }

    public function table(Table $table): Table
    {
        return $this->source::table($table);
    }

    public function render(): View
    {
        return view('assistant::filament.table');
    }
}

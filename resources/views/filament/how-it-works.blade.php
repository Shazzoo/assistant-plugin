<x-filament-panels::page>
    @php($sources = $this->sources())

    <x-filament::section heading="Instellingen">
        <dl class="grid gap-x-8 gap-y-3 sm:grid-cols-2">
            @foreach ($this->settings() as $label => $value)
                <div wire:key="setting-{{ $loop->index }}">
                    <dt class="text-sm text-gray-500 dark:text-gray-400">{{ $label }}</dt>
                    <dd class="text-sm font-medium text-gray-950 dark:text-white">{{ $value }}</dd>
                </div>
            @endforeach
        </dl>
    </x-filament::section>

    <x-filament::section heading="Bronnen">
        <x-slot name="description">Alles wat de assistent over de organisatie zegt, moet hier in staan. Wat nergens staat, verzint hij niet.</x-slot>

        <dl class="grid gap-x-8 gap-y-3 sm:grid-cols-3">
            <div>
                <dt class="text-sm text-gray-500 dark:text-gray-400">Kennisbestand</dt>
                <dd class="text-sm font-medium text-gray-950 dark:text-white">
                    <a href="{{ \Shazzoo\Assistant\Filament\Resources\KnowledgeEntries\KnowledgeEntryResource::getUrl() }}" class="underline">{{ $sources['used'] }} van {{ $sources['entries'] }} regels in gebruik</a>
                </dd>
            </div>
            <div>
                <dt class="text-sm text-gray-500 dark:text-gray-400">Medewerkers</dt>
                <dd class="text-sm font-medium text-gray-950 dark:text-white">
                    <a href="{{ \Shazzoo\Assistant\Filament\Resources\Employees\EmployeeResource::getUrl() }}" class="underline">{{ $sources['namedEmployees'] }} van {{ $sources['employees'] }} mag hij noemen</a>
                </dd>
            </div>
            <div>
                <dt class="text-sm text-gray-500 dark:text-gray-400">Referenties</dt>
                <dd class="text-sm font-medium text-gray-950 dark:text-white">
                    <a href="{{ \Shazzoo\Assistant\Filament\Resources\ClientReferences\ClientReferenceResource::getUrl() }}" class="underline">{{ $sources['references'] }}</a>
                </dd>
            </div>
        </dl>

        <details class="mt-4">
            <summary class="cursor-pointer text-sm font-medium text-primary-600 dark:text-primary-400">{{ $sources['pages']->count() }} pagina's van de website</summary>
            <ul class="mt-2 columns-1 gap-8 text-sm text-gray-700 sm:columns-2 lg:columns-3 dark:text-gray-300">
                @foreach ($sources['pages'] as $page)
                    <li wire:key="page-{{ $loop->index }}" class="py-0.5">{{ $page }}</li>
                @endforeach
            </ul>
            <p class="mt-2 text-xs text-gray-500">Plaatshouders zoals [CIJFER] op deze pagina's behandelt de assistent als onbekend.</p>
        </details>
    </x-filament::section>

    <x-filament::section heading="Instructies" collapsible>
        <x-slot name="description">Precies zoals de assistent ze bij elke vraag krijgt, met naam en contactgegevens ingevuld.</x-slot>

        <div class="fi-prose max-w-none">
            {{ $this->instructions() }}
        </div>
    </x-filament::section>
</x-filament-panels::page>

<x-filament-panels::page>
    @foreach ($this::sections() as $key => [$heading, $source, $description])
        <x-filament::section
            :heading="$heading"
            :description="$description"
            collapsible
            :collapsed="! $loop->first"
            persist-collapsed
            :collapse-id="'assistant-'.$key"
        >
            @if (filled($badge = $this->sectionBadge($key)))
                <x-slot name="afterHeader">
                    <x-filament::badge color="danger">{{ $badge }}</x-filament::badge>
                </x-slot>
            @endif

            @livewire('assistant-table', ['source' => $source], key($key))
        </x-filament::section>
    @endforeach
</x-filament-panels::page>

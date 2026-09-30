<x-filament-panels::page>
    <x-filament::tabs>
        @foreach ($this::tabs() as $key => [$label])
            <x-filament::tabs.item
                :active="$tab === $key"
                wire:click="$set('tab', '{{ $key }}')"
                :badge="$this->tabBadge($key)"
                badge-color="danger"
            >
                {{ $label }}
            </x-filament::tabs.item>
        @endforeach
    </x-filament::tabs>

    {{ $this->table }}
</x-filament-panels::page>

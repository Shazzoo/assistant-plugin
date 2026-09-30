<x-filament-panels::page>
    <x-filament::tabs>
        @foreach ($this::TABS as $key => $label)
            <x-filament::tabs.item
                :active="$tab === $key"
                wire:click="$set('tab', '{{ $key }}')"
                :badge="$key === 'onbeantwoord' ? $this::getNavigationBadge() : null"
                badge-color="danger"
            >
                {{ $label }}
            </x-filament::tabs.item>
        @endforeach
    </x-filament::tabs>

    @if ($tab === 'instellingen')
        {{ $this->form }}
    @else
        {{ $this->table }}
    @endif
</x-filament-panels::page>

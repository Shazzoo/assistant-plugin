<x-filament-panels::page>
    @php($status = $this->status())

    <x-filament::section heading="Status">
        <dl class="grid gap-x-8 gap-y-3 sm:grid-cols-5">
            <div>
                <dt class="text-sm text-gray-500 dark:text-gray-400">Beschikbaar</dt>
                <dd class="text-sm font-medium {{ $status['available'] === null ? 'text-success-600' : 'text-danger-600' }}">
                    {{ $status['available'] === null ? ($status['sandbox'] ? 'Ja, in de sandbox' : 'Ja, de eigen avatar') : $status['available'] }}
                </dd>
            </div>
            <div>
                <dt class="text-sm text-gray-500 dark:text-gray-400">Deze maand</dt>
                <dd class="text-sm font-medium text-gray-950 dark:text-white">{{ $status['used'] }} van {{ $status['budget'] }} minuten</dd>
            </div>
            <div>
                <dt class="text-sm text-gray-500 dark:text-gray-400">Saldo bij LiveAvatar</dt>
                <dd class="text-sm font-medium {{ $status['credits'] !== null && $status['credits'] < \Shazzoo\Assistant\Avatar\AvatarSessions::CREDITS_PER_MINUTE ? 'text-danger-600' : 'text-gray-950 dark:text-white' }}">
                    @if ($status['credits'] === null)
                        Niet op te vragen
                    @else
                        {{ \Illuminate\Support\Number::format($status['credits'], locale: 'nl') }} credits
                        (± {{ \Illuminate\Support\Number::format(floor($status['credits'] / \Shazzoo\Assistant\Avatar\AvatarSessions::CREDITS_PER_MINUTE), locale: 'nl') }} minuten)
                    @endif
                </dd>
            </div>
            <div>
                <dt class="text-sm text-gray-500 dark:text-gray-400">Nu actief</dt>
                <dd class="text-sm font-medium text-gray-950 dark:text-white">{{ $status['running'] }}</dd>
            </div>
            <div>
                <dt class="text-sm text-gray-500 dark:text-gray-400">Kosten</dt>
                <dd class="text-sm font-medium text-gray-950 dark:text-white">ca. $ 0,18 per minuut (Essential)</dd>
            </div>
        </dl>

        @if ($status['recent']->isNotEmpty())
            <details class="mt-4">
                <summary class="cursor-pointer text-sm font-medium text-primary-600 dark:text-primary-400">Laatste sessies</summary>
                <table class="mt-2 w-full text-left text-sm">
                    <thead class="text-gray-500">
                        <tr><th class="py-1 font-medium">Gestart</th><th class="font-medium">Duur</th><th class="font-medium">Gestopt</th><th class="font-medium">Sandbox</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($status['recent'] as $session)
                            <tr wire:key="avatar-session-{{ $session->id }}" class="border-t border-gray-200 dark:border-white/10">
                                <td class="py-1">{{ $session->started_at->format('j M H:i') }}</td>
                                <td>{{ $session->ended_at ? $session->started_at->diffForHumans($session->ended_at, true) : 'loopt' }}</td>
                                <td>{{ $session->end_reason ?? '—' }}</td>
                                <td>{{ $session->sandbox ? 'ja' : 'nee' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </details>
        @endif
    </x-filament::section>

    <form wire:submit="save" class="flex flex-col gap-6">
        {{ $this->form }}

        <div>
            <x-filament::button type="submit">Opslaan</x-filament::button>
        </div>
    </form>
</x-filament-panels::page>

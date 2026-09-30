{{--
    De foto (of pratende avatar) met de chat eronder. Te gebruiken in het blok van
    de plugin, maar ook los in een thema, bijvoorbeeld in een hero:

    <x-assistant::panel :image="media_url($id)" alt="…" :suggestions="['Wat kost het?']" />
--}}
@props([
    'image' => null,
    'alt' => '',
    'suggestions' => [],
    'placeholder' => null,
    'button' => null,
    'disclaimer' => null,
])

@once
    <link rel="stylesheet" href="{{ route('assistant.css', 'chat.css') }}">
@endonce

<div {{ $attributes->class(['assistant-panel']) }}>
    @if (filled($image))
        <div class="assistant-block__stage" data-assistant-avatar-stage data-livekit-url="{{ config('assistant.avatar.livekit_url') }}">
            <img src="{{ $image }}" alt="{{ $alt }}" loading="lazy">

            {{-- Pratende avatar: verschijnt op de plek van de foto zodra de assistent live spreekt. --}}
            <video data-avatar-video hidden autoplay playsinline aria-label="{{ __('assistant::assistant.avatar_speaking', ['name' => app(\Shazzoo\Assistant\Models\AssistantSettings::class)->assistantName()]) }}"></video>

            <div class="assistant-block__controls" data-avatar-controls hidden>
                <button type="button" data-avatar-sound hidden>{{ __('assistant::assistant.avatar_sound_on') }}</button>
                <button type="button" data-avatar-mute aria-pressed="false" data-label-on="{{ __('assistant::assistant.avatar_sound_on') }}" data-label-off="{{ __('assistant::assistant.avatar_sound_off') }}">{{ __('assistant::assistant.avatar_sound_off') }}</button>
            </div>
        </div>

        @once
            <script type="module" src="{{ route('assistant.js', 'avatar.js') }}"></script>
        @endonce
    @endif

    <livewire:assistant-chat
        :suggestions="$suggestions"
        :placeholder="$placeholder"
        :button="$button"
        :disclaimer="$disclaimer"
    />
</div>

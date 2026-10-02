@php
    $image = filled($data['image'] ?? null) ? media_url($data['image']) : null;
    $avatarSettings = \Shazzoo\Assistant\Models\AvatarSettings::current();
    $hasAvatarStage = filled($image)
        || filled(media_url($avatarSettings->fallback_image_id))
        || $avatarSettings->enabled;
    $suggestions = collect($data['suggestions'] ?? [])->pluck('text')->filter()->values()->all();
@endphp

<section @class(['assistant-block', 'assistant-block--with-image' => $hasAvatarStage])>
    @if (filled($data['title'] ?? null) || filled($data['intro'] ?? null))
        <div class="assistant-block__head">
            @if (filled($data['title'] ?? null))
                <h2 class="assistant-block__title">{{ $data['title'] }}</h2>
            @endif
            @if (filled($data['intro'] ?? null))
                <p class="assistant-block__intro">{{ $data['intro'] }}</p>
            @endif
        </div>
    @endif

    <x-dynamic-component component="assistant::panel"
        :image="$image"
        :alt="$data['image_alt'] ?? ''"
        :suggestions="$suggestions"
        :placeholder="$data['placeholder'] ?? null"
        :button="$data['button'] ?? null"
        :disclaimer="$data['disclaimer'] ?? null"
    />
</section>

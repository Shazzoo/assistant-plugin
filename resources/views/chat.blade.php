{{-- Op laptop een vaste hoogte, zodat de foto ernaast blijft staan; op telefoon groeit de chat mee tot een maximum. Daarboven scrollen alleen de berichten. --}}
<div class="assistant-chat" data-assistant-chat data-avatar="{{ $avatarAvailable ? 'on' : 'off' }}">
    <div class="assistant-chat__header">
        <svg width="9" height="9" viewBox="0 0 9 9" aria-hidden="true"><circle cx="4.5" cy="4.5" r="3.6" fill="currentColor" /></svg>
        <span class="assistant-chat__name">{{ $this->assistantName() }}</span>
        <span class="assistant-chat__role">&middot; {{ __('assistant::assistant.role') }}</span>
    </div>

    <div
        class="assistant-chat__messages"
        aria-live="polite"
        {{-- Meescrollen met het antwoord, behalve als de bezoeker zelf terugscrolt om iets na te lezen. --}}
        x-data="{ follow: true }"
        x-on:scroll="follow = $el.scrollHeight - $el.scrollTop - $el.clientHeight < 40"
        x-on:submit.window="follow = true"
        x-init="new MutationObserver(() => { if (follow) $el.scrollTop = $el.scrollHeight }).observe($el, { childList: true, subtree: true, characterData: true })"
    >
        @if (empty($messages) && filled($this->greeting()))
            <div class="assistant-chat__answer">
                <p class="assistant-chat__text">{{ $this->greeting() }}</p>
            </div>
        @endif

        @foreach ($messages as $index => $message)
            @if ($message['role'] === 'user')
                <div wire:key="message-{{ $index }}" class="assistant-chat__question">
                    <p class="assistant-chat__text">{{ $message['content'] }}</p>
                </div>
            @else
                <div wire:key="message-{{ $index }}" class="assistant-chat__answer">
                    <div class="assistant-chat__text assistant-chat__markdown">
                        {!! Str::markdown($message['content'], ['html_input' => 'escape', 'allow_unsafe_links' => false]) !!}
                    </div>
                    @if ($message['source'] ?? null)
                        <p class="assistant-chat__source">{{ __('assistant::assistant.source') }}: {{ $message['source'] }}</p>
                    @endif
                </div>
            @endif
        @endforeach

        @if ($isAnswering)
            <div wire:key="answering" class="assistant-chat__answer">
                <p wire:ref="answer" class="assistant-chat__text assistant-streaming" data-thinking="{{ __('assistant::assistant.thinking') }}"></p>
            </div>
        @endif
    </div>

    @if ($isShared)
        <div class="assistant-chat__notice assistant-chat__notice--done" role="status">
            {{ __('assistant::assistant.share_done', ['contact' => $this->contactName()]) }}
        </div>
    @elseif ($isSharing)
        <form wire:submit="share" class="assistant-chat__share">
            <p>{{ __('assistant::assistant.share_intro', ['contact' => $this->contactName()]) }}</p>

            <div class="assistant-chat__fields">
                <label class="assistant-chat__field assistant-chat__field--wide">
                    {{ __('assistant::assistant.share_name') }}
                    <input type="text" wire:model="shareName" autocomplete="name" maxlength="100">
                </label>
                <label class="assistant-chat__field">
                    {{ __('assistant::assistant.share_email') }}
                    <input type="email" wire:model="shareEmail" autocomplete="email" maxlength="150">
                </label>
                <label class="assistant-chat__field">
                    {{ __('assistant::assistant.share_phone') }}
                    <input type="tel" wire:model="sharePhone" autocomplete="tel" maxlength="30">
                </label>
                <label class="assistant-chat__field assistant-chat__field--wide">
                    {{ __('assistant::assistant.share_note') }}
                    <textarea wire:model="shareNote" rows="2" maxlength="1000"></textarea>
                </label>
                <label class="assistant-chat__trap" aria-hidden="true">
                    Website
                    <input type="text" wire:model="shareWebsite" tabindex="-1" autocomplete="off">
                </label>
            </div>

            @if ($errors->hasAny(['shareName', 'shareEmail', 'sharePhone', 'shareNote']))
                <ul class="assistant-chat__errors">
                    @foreach (collect(['shareName', 'shareEmail', 'sharePhone', 'shareNote'])->map(fn ($field) => $errors->first($field))->filter()->unique() as $error)
                        <li wire:key="share-error-{{ $loop->index }}">{{ $error }}</li>
                    @endforeach
                </ul>
            @endif

            <div class="assistant-chat__actions">
                <button type="submit" class="assistant-chat__button" wire:loading.attr="disabled" wire:target="share">
                    {{ __('assistant::assistant.share_send', ['contact' => $this->contactName()]) }}
                </button>
                <button type="button" wire:click="cancelShare" class="assistant-chat__link">{{ __('assistant::assistant.share_cancel') }}</button>
            </div>
        </form>
    @elseif ($this->hasAnswer() && $this->canShare())
        <div class="assistant-chat__notice assistant-chat__notice--share">
            <span>{{ __('assistant::assistant.share_offer', ['contact' => $this->contactName()]) }}</span>
            <button type="button" wire:click="openShare" class="assistant-chat__button assistant-chat__button--small">{{ __('assistant::assistant.share_open') }}</button>
        </div>
    @endif

    @if ($this->hasReachedLimit())
        <p class="assistant-chat__notice">
            {{ __('assistant::assistant.limit_reached', ['contact' => $this->contactName()]) }}
        </p>
    @elseif (! $isSharing)
        {{-- Tijdens het deelformulier geen vraagveld: dat formulier heeft de ruimte nodig. --}}
        <form wire:submit="send" class="assistant-chat__form">
            <div class="assistant-chat__ask">
                <label class="assistant-chat__input">
                    <span class="assistant-chat__sr">{{ __('assistant::assistant.prompt_label', ['name' => $this->assistantName()]) }}</span>
                    <input
                        type="text"
                        wire:model="prompt"
                        maxlength="{{ $this->maxQuestionLength() }}"
                        placeholder="{{ $placeholder ?: __('assistant::assistant.placeholder', ['name' => $this->assistantName()]) }}"
                        autocomplete="off"
                        {{-- Readonly in plaats van disabled: zo houdt het veld de focus tijdens het antwoord. --}}
                        @readonly($isAnswering)
                        aria-busy="{{ $isAnswering ? 'true' : 'false' }}"
                        x-on:assistant-answered.window="if (! $el.contains(document.activeElement) && (document.activeElement === document.body || $el.closest('[data-assistant-chat]').contains(document.activeElement)) && ! matchMedia('(pointer: coarse)').matches) $el.focus({ preventScroll: true })"
                    >
                </label>
                <label class="assistant-chat__trap" aria-hidden="true">
                    Website
                    <input type="text" wire:model="website" tabindex="-1" autocomplete="off">
                </label>
                <button type="submit" class="assistant-chat__button" @disabled($isAnswering)>
                    {{ $button ?: __('assistant::assistant.ask', ['name' => $this->assistantName()]) }}
                </button>
            </div>
            @error('prompt')
                <p class="assistant-chat__error">{{ $message }}</p>
            @enderror
        </form>

        @if (empty($messages) && $suggestions !== [])
            <div class="assistant-chat__suggestions">
                @foreach ($suggestions as $suggestion)
                    <button
                        type="button"
                        wire:key="suggestion-{{ $loop->index }}"
                        wire:click="ask(@js($suggestion))"
                        class="assistant-chat__suggestion"
                        @disabled($isAnswering)
                    >{{ $suggestion }}</button>
                @endforeach
            </div>
        @endif
    @endif

    <p class="assistant-chat__disclaimer">
        @if (filled($disclaimer))
            {{ $disclaimer }}
        @endif
        {{ __('assistant::assistant.retention', ['days' => $this->retentionDays(), 'name' => $this->assistantName()]) }}
        @if ($avatarAvailable)
            {{ __('assistant::assistant.avatar_notice') }}
        @endif
    </p>
</div>

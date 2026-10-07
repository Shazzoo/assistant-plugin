/**
 * Pratende avatar voor de assistent (HeyGen LiveAvatar via LiveKit).
 *
 * - Start bij de eerste vraag van de bezoeker (een klik, dus geluid mag spelen).
 * - Stuurt alleen de antwoordtekst van de assistent naar de avatar, zin voor zin terwijl die binnenkomt.
 *   Wat de bezoeker typt en de microfoon worden nooit gebruikt.
 * - Stopt bij stilte of als de bezoeker de pagina verlaat; daarna staat de foto er weer.
 * - Lukt iets niet, dan blijft gewoon de foto staan: de chat werkt altijd.
 */
// LiveKit (enkele honderden kB) wordt pas geladen als de avatar echt start.
let livekit = null;

const encoder = new TextEncoder();
const decoder = new TextDecoder();

class AssistantAvatar {
    constructor(chatEl, stageEl) {
        this.chatEl = chatEl;
        this.stageEl = stageEl;
        this.videoEl = stageEl.querySelector('[data-avatar-video]');
        this.controlsEl = stageEl.querySelector('[data-avatar-controls]');
        this.muteButton = stageEl.querySelector('[data-avatar-mute]');
        this.soundButton = stageEl.querySelector('[data-avatar-sound]');

        this.room = null;
        this.session = null;
        this.starting = false;
        this.muted = false;
        this.audioEls = [];

        this.queue = [];
        this.speaking = false;
        this.speakTimer = null;
        this.idleTimer = null;

        this.streamEl = null;
        this.streamedLength = 0;
        this.pendingText = '';

        this.bindChat();
        this.bindControls();
        window.addEventListener('pagehide', () => this.leave());
    }

    get component() {
        return window.Livewire?.find(this.chatEl.getAttribute('wire:id'));
    }

    bindChat() {
        // Een vraag versturen of een voorbeeldvraag aanklikken: dat is het moment om te starten.
        this.chatEl.addEventListener('submit', (event) => {
            if (event.target.matches('[wire\\:submit="send"]')) {
                this.onQuestion();
            }
        }, true);

        this.chatEl.addEventListener('click', (event) => {
            if (event.target.closest('[wire\\:click^="ask("]')) {
                this.onQuestion();
            }
        }, true);

        // het antwoord van de assistent komt binnen in het element .assistant-streaming.
        new MutationObserver(() => this.readStream()).observe(this.chatEl, { childList: true, subtree: true, characterData: true });

        // De server meldt dat de vraag verwerkt is: dan pas starten (de vraag staat dan in het gesprek).
        // Pas een tik later: tegelijk aangeroepen methodes voegt Livewire samen tot één verzoek,
        // en dan zou de avatar wachten tot het hele antwoord gestreamd is. Nu gaat het antwoord
        // eerst de deur uit en loopt startAvatar (#[Async]) er in een eigen verzoek naast.
        window.Livewire.on('assistant-question-sent', () => {
            if (! this.room && ! this.starting) {
                setTimeout(() => this.start(), 50);
            }
        });

        // Livewire-foutmeldingen voor de avatar-aanroep niet als pop-up tonen: de foto blijft gewoon staan.
        window.Livewire.interceptRequest(({ request, onError }) => {
            if (! [...request.messages].some((message) => [...message.actions].some((action) => action.name === 'startAvatar'))) {
                return;
            }
            onError(({ preventDefault }) => preventDefault());
        });
    }

    bindControls() {
        this.muteButton?.addEventListener('click', () => this.setMuted(! this.muted));
        this.soundButton?.addEventListener('click', async () => {
            await this.room?.startAudio();
            this.soundButton.hidden = true;
        });
    }

    onQuestion() {
        this.resetIdleTimer();

        // Een nieuwe vraag: de assistent houdt op met het vorige antwoord.
        if (this.room) {
            this.queue = [];
            this.publish({ event_type: 'avatar.interrupt' });
            this.speakingDone();
        }
    }

    async start() {
        this.starting = true;

        try {
            this.session = await this.component?.startAvatar();

            if (! this.session) {
                return;
            }

            livekit ??= await import(this.stageEl.dataset.livekitUrl);
            const { Room, RoomEvent } = livekit;

            this.room = new Room({ adaptiveStream: true, dynacast: false });

            this.room.on(RoomEvent.TrackSubscribed, (track) => this.attachTrack(track));
            this.room.on(RoomEvent.DataReceived, (payload, participant, kind, topic) => this.onData(payload, topic));
            this.room.on(RoomEvent.AudioPlaybackStatusChanged, () => {
                if (this.soundButton) {
                    this.soundButton.hidden = this.room.canPlaybackAudio;
                }
            });
            this.room.on(RoomEvent.Disconnected, () => this.showPhoto());

            await this.room.connect(this.session.livekit_url, this.session.livekit_client_token, { autoSubscribe: true });
            await this.room.startAudio().catch(() => {});

            if (this.soundButton) {
                this.soundButton.hidden = this.room.canPlaybackAudio;
            }

            this.resetIdleTimer();
            this.speakNext();
        } catch (error) {
            console.warn('de assistent: pratende avatar niet beschikbaar', error);
            await this.stop('closed');
        } finally {
            this.starting = false;
        }
    }

    attachTrack(track) {
        if (track.kind === livekit.Track.Kind.Video) {
            // De foto blijft staan tot er echt beeld is; anders knippert de avatar even zwart.
            // De video zelf moet zichtbaar zijn (wel doorzichtig), anders stuurt LiveKit geen beeld.
            this.videoEl.addEventListener('playing', () => this.showVideo(), { once: true });
            this.videoEl.hidden = false;
            track.attach(this.videoEl);
        } else if (track.kind === livekit.Track.Kind.Audio) {
            const audioEl = track.attach();
            audioEl.muted = this.muted;
            audioEl.hidden = true;
            this.stageEl.appendChild(audioEl);
            this.audioEls.push(audioEl);
        }
    }

    onData(payload, topic) {
        let event;

        try {
            event = JSON.parse(decoder.decode(payload));
        } catch {
            return;
        }

        if (event.event_type === 'avatar.speak_ended') {
            this.speakingDone();
            this.speakNext();
            this.resetIdleTimer();
        }
    }

    /**
     * Leest de binnenkomende antwoordtekst en zet elke complete zin in de wachtrij.
     */
    readStream() {
        const current = this.chatEl.querySelector('.assistant-streaming');

        if (current !== this.streamEl) {
            // Het vorige antwoord is klaar (element verdwenen): de rest ook uitspreken.
            if (this.streamEl && ! current) {
                this.enqueue(this.pendingText);
                this.pendingText = '';
            }

            this.streamEl = current;
            this.streamedLength = 0;
        }

        if (! current) {
            return;
        }

        const text = current.textContent ?? '';
        this.pendingText += text.slice(this.streamedLength);
        this.streamedLength = text.length;

        // Alles tot en met de laatste zinsgrens is klaar om uit te spreken.
        const match = this.pendingText.match(/^[\s\S]*[.!?…](?=\s)/);

        if (match) {
            this.enqueue(match[0]);
            this.pendingText = this.pendingText.slice(match[0].length);
        }
    }

    enqueue(text) {
        const clean = spokenText(text);

        if (clean === '') {
            return;
        }

        this.queue.push(clean);
        this.speakNext();
    }

    /**
     * Eén opdracht tegelijk: de volgende pas als de vorige klaar is. Of LiveAvatar
     * opdrachten zelf in de rij zet, is niet gedocumenteerd.
     */
    speakNext() {
        if (this.speaking || ! this.room || this.queue.length === 0) {
            return;
        }

        // Voeg korte zinnen samen, zodat de assistent niet hakkelt.
        let text = this.queue.shift();
        while (this.queue.length && text.length < 160) {
            text += ' ' + this.queue.shift();
        }

        this.speaking = true;
        this.publish({ event_type: 'avatar.speak_text', event_id: eventId(), text });

        // Vangnet als het einde-signaal niet komt: ruim de spreektijd van de tekst.
        clearTimeout(this.speakTimer);
        this.speakTimer = setTimeout(() => {
            this.speakingDone();
            this.speakNext();
        }, 2000 + text.length * 90);
    }

    speakingDone() {
        this.speaking = false;
        clearTimeout(this.speakTimer);
    }

    publish(message) {
        if (! this.room) {
            return;
        }

        this.room.localParticipant
            .publishData(encoder.encode(JSON.stringify(message)), { reliable: true, topic: 'agent-control' })
            .catch((error) => console.warn('de assistent: kon niet naar de avatar sturen', error));
    }

    setMuted(muted) {
        this.muted = muted;
        this.audioEls.forEach((el) => (el.muted = muted));

        if (this.muteButton) {
            this.muteButton.textContent = muted ? this.muteButton.dataset.labelOn : this.muteButton.dataset.labelOff;
            this.muteButton.setAttribute('aria-pressed', String(muted));
        }
    }

    resetIdleTimer() {
        clearTimeout(this.idleTimer);

        const seconds = this.session?.idle_stop_seconds ?? 90;
        this.idleTimer = setTimeout(() => {
            if (! this.speaking && this.queue.length === 0) {
                this.stop('idle');
            } else {
                this.resetIdleTimer();
            }
        }, seconds * 1000);
    }

    async stop(reason) {
        clearTimeout(this.idleTimer);
        this.queue = [];
        this.speakingDone();

        const room = this.room;
        const stopUrl = this.session?.stop_url;
        this.room = null;
        this.session = null;
        await room?.disconnect();

        this.showPhoto();

        // Ondertekende URL: werkt zonder cookies en zonder Livewire-status.
        if (stopUrl) {
            await fetch(stopUrl, { method: 'POST', body: new URLSearchParams({ reason }), keepalive: true }).catch(() => {});
        }
    }

    leave() {
        // Pagina weg: Livewire is dan niet meer betrouwbaar, sendBeacon wel.
        if (this.session?.stop_url) {
            navigator.sendBeacon(this.session.stop_url, new URLSearchParams({ reason: 'page_left' }));
        }

        this.room?.disconnect();
    }

    showVideo() {
        this.stageEl.dataset.avatarLive = 'true';
        this.videoEl.hidden = false;
        if (this.controlsEl) {
            this.controlsEl.hidden = false;
        }
    }

    showPhoto() {
        delete this.stageEl.dataset.avatarLive;
        this.videoEl.hidden = true;
        if (this.controlsEl) {
            this.controlsEl.hidden = true;
        }
        this.audioEls.forEach((el) => el.remove());
        this.audioEls = [];
    }
}

/**
 * Wat de assistent uitspreekt: zonder opmaak, links of opsommingstekens.
 */
export function spokenText(text) {
    return text
        .replace(/\[([^\]]+)\]\([^)]+\)/g, '$1')
        .replace(/https?:\/\/\S+/g, '')
        .replace(/[*_`#>]/g, '')
        .replace(/^\s*[-•]\s+/gm, '')
        .replace(/\s+/g, ' ')
        .trim();
}

/**
 * LiveAvatar negeert opdrachten zonder UUID als event_id. crypto.randomUUID bestaat
 * alleen op https; lokaal (http) maken we de UUID zelf.
 */
function eventId() {
    if (typeof crypto.randomUUID === 'function') {
        return crypto.randomUUID();
    }

    const bytes = crypto.getRandomValues(new Uint8Array(16));
    bytes[6] = (bytes[6] & 0x0f) | 0x40;
    bytes[8] = (bytes[8] & 0x3f) | 0x80;
    const hex = [...bytes].map((byte) => byte.toString(16).padStart(2, '0')).join('');

    return `${hex.slice(0, 8)}-${hex.slice(8, 12)}-${hex.slice(12, 16)}-${hex.slice(16, 20)}-${hex.slice(20)}`;
}

function boot() {
    const chatEl = document.querySelector('[data-assistant-chat][data-avatar="on"]');
    const stageEl = document.querySelector('[data-assistant-avatar-stage]');

    if (chatEl && stageEl && ! stageEl.assistantAvatar) {
        stageEl.assistantAvatar = new AssistantAvatar(chatEl, stageEl);
    }
}

document.addEventListener('livewire:initialized', boot);

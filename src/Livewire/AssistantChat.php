<?php

namespace Shazzoo\Assistant\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Livewire\Attributes\Async;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Renderless;
use Livewire\Component;
use Shazzoo\Assistant\Assistant;
use Shazzoo\Assistant\Avatar\AvatarSessions;
use Shazzoo\Assistant\Mail\ConversationShared;
use Shazzoo\Assistant\Models\AssistantSettings;
use Shazzoo\Assistant\Models\DailyStatistic;
use Shazzoo\Assistant\TranscriptRecorder;
use Throwable;

class AssistantChat extends Component
{
    /** Hoeveel suggesties er hooguit onder het vraagveld staan. */
    public const int MAX_SUGGESTIONS = 3;

    public string $prompt = '';

    /** Onzichtbaar veld bij de vraag: alleen bots vullen het in. */
    public string $website = '';

    /**
     * @var list<array{role: 'user'|'assistant', content: string, source?: ?string, answered?: bool}>
     */
    #[Locked]
    public array $messages = [];

    #[Locked]
    public bool $isAnswering = false;

    /** Willekeurig per gesprek; nergens aan te koppelen, ook niet aan een eerder bezoek. */
    #[Locked]
    public string $sessionNumber = '';

    /** De pagina waar het gesprek begon. */
    #[Locked]
    public ?string $page = null;

    /** Of de pratende avatar voor dit gesprek aan kan. */
    #[Locked]
    public bool $avatarAvailable = false;

    /**
     * Voorbeeldvragen uit het blok. Vast per gesprek, zodat een bezoeker via ask() alleen
     * deze kan versturen.
     *
     * @var list<string>
     */
    #[Locked]
    public array $suggestions = [];

    #[Locked]
    public ?string $placeholder = null;

    #[Locked]
    public ?string $button = null;

    #[Locked]
    public ?string $disclaimer = null;

    /** Het formulier "Stuur dit gesprek mee" staat open. */
    public bool $isSharing = false;

    #[Locked]
    public bool $isShared = false;

    public string $shareName = '';

    public string $shareEmail = '';

    public string $sharePhone = '';

    public string $shareNote = '';

    /** Onzichtbaar veld: alleen bots vullen het in. */
    public string $shareWebsite = '';

    /**
     * @param  list<string>|null  $suggestions
     */
    public function mount(
        AvatarSessions $avatarSessions,
        ?string $page = null,
        ?array $suggestions = null,
        ?string $placeholder = null,
        ?string $button = null,
        ?string $disclaimer = null,
    ): void {
        $this->sessionNumber = (string) Str::uuid();
        $this->page = $page ?? '/'.ltrim(request()->path(), '/');
        $this->avatarAvailable = $avatarSessions->unavailableReason() === null;
        $this->suggestions = array_slice(array_values(array_filter(array_map('trim', $suggestions ?? []), filled(...))), 0, self::MAX_SUGGESTIONS);
        $this->placeholder = $placeholder;
        $this->button = $button;
        $this->disclaimer = $disclaimer;
    }

    public function send(): void
    {
        if ($this->isAnswering || $this->hasReachedLimit()) {
            return;
        }

        // Bots krijgen geen antwoord en kosten dus niets.
        if ($this->website !== '') {
            $this->reset('prompt');

            return;
        }

        $this->prompt = trim($this->prompt);

        $maxLength = $this->settings()->max_question_length;

        $this->validate(['prompt' => "required|string|max:{$maxLength}"], [
            'prompt.required' => __('assistant::assistant.prompt_required'),
            'prompt.max' => __('assistant::assistant.prompt_too_long', ['max' => $maxLength]),
        ]);

        if (! $this->withinRateLimits()) {
            $this->addError('prompt', __('assistant::assistant.too_many', ['phone' => (string) $this->settings()->contact_phone]));

            return;
        }

        $this->messages[] = ['role' => 'user', 'content' => $this->prompt];
        $this->reset('prompt');
        $this->isAnswering = true;

        $this->js('$wire.answer()');

        if ($this->avatarAvailable) {
            $this->dispatch('assistant-question-sent');
        }
    }

    public function ask(string $suggestion): void
    {
        if (! in_array($suggestion, $this->suggestions, true)) {
            return;
        }

        $this->prompt = $suggestion;
        $this->send();
    }

    public function answer(Assistant $assistant, TranscriptRecorder $recorder): void
    {
        if (! $this->isAnswering) {
            return;
        }

        // Nadenken plus streamen kan langer duren dan de standaardlimiet van PHP.
        set_time_limit(120);

        $conversation = array_map(
            fn (array $message): array => ['role' => $message['role'], 'content' => $message['content']],
            $this->messages,
        );

        $answer = $assistant->answer($conversation, function (string $delta): void {
            $this->stream($delta)->to(ref: 'answer');
        });

        $this->messages[] = [
            'role' => 'assistant',
            'content' => $answer->text,
            'source' => $answer->source,
            'answered' => $answer->isAnswered(),
        ];

        $this->isAnswering = false;

        // De browser zet de cursor terug in het vraagveld.
        $this->dispatch('assistant-answered');

        try {
            $recorder->record($this->sessionNumber, $this->page, end($conversation)['content'], $answer);
        } catch (Throwable $exception) {
            // Het gesprek gaat voor; een mislukte registratie mag de bezoeker niet raken.
            Log::error('Transcriptie van de assistent kon niet worden opgeslagen', ['exception' => $exception->getMessage()]);
        }
    }

    /**
     * Start de pratende avatar voor dit gesprek. De browser roept dit aan zodra een vraag
     * verwerkt is. Async: het loopt naast het streamen van het antwoord, niet erna.
     * Renderless en zonder componentstatus, zodat het niet botst met dat antwoord; stoppen
     * gaat via de ondertekende stop_url. Null betekent: de foto blijft staan.
     *
     * @return array{session_id: string, livekit_url: string, livekit_client_token: string, idle_stop_seconds: int, stop_url: string}|null
     */
    #[Async]
    #[Renderless]
    public function startAvatar(AvatarSessions $sessions): ?array
    {
        if (! $this->avatarAvailable || $this->messages === []) {
            return null;
        }

        // Eén start tegelijk per gesprek, ook als de browser twee keer vraagt.
        if (! Cache::add("assistant:avatar-start:{$this->sessionNumber}", true, 30)) {
            return null;
        }

        return $sessions->start();
    }

    public function openShare(): void
    {
        $this->isSharing = $this->canShare() && ! $this->isShared && $this->hasAnswer();
    }

    public function cancelShare(): void
    {
        $this->isSharing = false;
        $this->resetShareFields();
    }

    /**
     * De bezoeker kiest er zelf voor dat er een dossier ontstaat: het ongeschoonde
     * gesprek gaat met zijn contactgegevens naar het ingestelde adres, en nergens anders heen.
     */
    public function share(): void
    {
        if (! $this->canShare() || $this->isShared || ! $this->hasAnswer()) {
            return;
        }

        $this->validate([
            'shareName' => 'required|string|max:100',
            'shareEmail' => 'nullable|required_without:sharePhone|email|max:150',
            'sharePhone' => ['nullable', 'required_without:shareEmail', 'string', 'max:30', 'regex:/^[0-9+()\s-]{8,}$/'],
            'shareNote' => 'nullable|string|max:1000',
        ], [
            'shareName.required' => __('assistant::assistant.share_name_required'),
            'shareEmail.required_without' => __('assistant::assistant.share_reach_required'),
            'sharePhone.required_without' => __('assistant::assistant.share_reach_required'),
            'shareEmail.email' => __('assistant::assistant.share_email_invalid'),
            'sharePhone.regex' => __('assistant::assistant.share_phone_invalid'),
            '*.max' => __('assistant::assistant.share_too_long'),
        ]);

        // Bots krijgen hetzelfde antwoord, maar er wordt niets verstuurd.
        if ($this->shareWebsite !== '') {
            $this->completeShare();

            return;
        }

        if (RateLimiter::tooManyAttempts('assistant:share', 10)) {
            $this->addError('shareName', $this->shareFailedMessage('share_busy'));

            return;
        }

        RateLimiter::hit('assistant:share', 60);

        try {
            Mail::to($this->settings()->shareAddress())->send(new ConversationShared(
                visitor: [
                    'name' => $this->shareName,
                    'email' => $this->shareEmail !== '' ? $this->shareEmail : null,
                    'phone' => $this->sharePhone !== '' ? $this->sharePhone : null,
                    'note' => $this->shareNote !== '' ? $this->shareNote : null,
                ],
                messages: array_map(
                    fn (array $message): array => ['role' => $message['role'], 'content' => $message['content']],
                    $this->messages,
                ),
                page: $this->page,
                sessionNumber: $this->sessionNumber,
                assistantName: $this->settings()->assistantName(),
            ));
        } catch (Throwable $exception) {
            Log::error('Gesprek van de assistent kon niet worden doorgestuurd', ['exception' => $exception->getMessage()]);
            $this->addError('shareName', $this->shareFailedMessage('share_failed'));

            return;
        }

        DailyStatistic::recordShared();

        $this->completeShare();
    }

    public function hasAnswer(): bool
    {
        return collect($this->messages)->contains('role', 'assistant');
    }

    /**
     * Zonder adres om het gesprek naartoe te sturen, is er geen knop om het mee te sturen.
     */
    public function canShare(): bool
    {
        return filled($this->settings()->shareAddress());
    }

    public function assistantName(): string
    {
        return $this->settings()->assistantName();
    }

    public function greeting(): ?string
    {
        return $this->settings()->greeting;
    }

    public function contactName(): string
    {
        return $this->settings()->contactName();
    }

    public function hasReachedLimit(): bool
    {
        return collect($this->messages)->where('role', 'user')->count() >= $this->settings()->max_questions;
    }

    public function maxQuestionLength(): int
    {
        return $this->settings()->max_question_length;
    }

    public function retentionDays(): int
    {
        return (int) config('assistant.transcripts.retention_days');
    }

    public function render(): View
    {
        return view('assistant::chat');
    }

    private function settings(): AssistantSettings
    {
        return app(AssistantSettings::class);
    }

    private function shareFailedMessage(string $key): string
    {
        return __("assistant::assistant.{$key}", [
            'phone' => (string) $this->settings()->contact_phone,
            'email' => (string) $this->settings()->contact_email,
        ]);
    }

    private function completeShare(): void
    {
        $this->isShared = true;
        $this->isSharing = false;

        // Contactgegevens niet laten rondslingeren in de status van het component.
        $this->resetShareFields();
    }

    private function resetShareFields(): void
    {
        $this->reset('shareName', 'shareEmail', 'sharePhone', 'shareNote', 'shareWebsite');
        $this->resetValidation();
    }

    /**
     * Plafond voor alle bezoekers samen, dat het API-budget beschermt. Per gesprek geldt al
     * het maximum aantal vragen. Er wordt geen IP-adres gebruikt of bewaard.
     */
    private function withinRateLimits(): bool
    {
        $globalKey = 'assistant:global';

        if (RateLimiter::tooManyAttempts($globalKey, config('assistant.rate_limits.global_per_minute'))) {
            return false;
        }

        RateLimiter::hit($globalKey, 60);

        return true;
    }
}

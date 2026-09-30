<?php

use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Shazzoo\Assistant\Answer;
use Shazzoo\Assistant\Assistant;
use Shazzoo\Assistant\FakeAssistant;
use Shazzoo\Assistant\Livewire\AssistantChat;
use Shazzoo\Assistant\Models\Conversation;
use Shazzoo\Assistant\Models\ConversationMessage;
use Shazzoo\Assistant\Models\UnansweredQuestion;
use Shazzoo\Assistant\TranscriptRecorder;
use Shazzoo\Assistant\UnansweredReason;
use Shazzoo\Assistant\UnansweredStatus;

beforeEach(function () {
    $this->app->instance(Assistant::class, new FakeAssistant(delayInMicroseconds: 0));
});

function askAssistant(string $question, ?Testable $component = null): Testable
{
    return ($component ?? Livewire::test(AssistantChat::class, ['page' => '/']))
        ->set('prompt', $question)
        ->call('send')
        ->call('answer');
}

it('stores the conversation with a random session number and the page', function () {
    askAssistant('Wat doen jullie eigenlijk?');

    $conversation = Conversation::sole();

    expect($conversation->session_number)->toMatch('/^[0-9a-f-]{36}$/')
        ->and($conversation->page)->toBe('/')
        ->and($conversation->messages()->pluck('role')->all())->toBe(['user', 'assistant']);
});

it('keeps one conversation per chat, and a new one per chat', function () {
    $component = askAssistant('Wat doen jullie?');
    askAssistant('En onze data?', $component);
    askAssistant('Wat doen jullie?');

    expect(Conversation::count())->toBe(2)
        ->and(ConversationMessage::count())->toBe(6);
});

it('scrubs contact details before storing the question', function () {
    askAssistant('Kunnen jullie me terugbellen op 06-12345678 of mailen naar piet@bedrijf.nl?');

    $stored = ConversationMessage::where('role', 'user')->sole()->content;

    expect($stored)->toContain('[TELEFOON]')->toContain('[EMAIL]')
        ->not->toContain('12345678')->not->toContain('piet@');
});

it('never stores an ip address or user agent', function () {
    askAssistant('Wat doen jullie?');

    foreach (['conversations', 'conversation_messages', 'unanswered_questions'] as $table) {
        expect(Schema::getColumnListing($table))->not->toContain('ip_address')->not->toContain('user_agent');
    }
});

it('records the answer status and source', function () {
    askAssistant('En onze data?');

    $answer = ConversationMessage::where('role', 'assistant')->sole();

    expect($answer->status)->toBe('beantwoord')
        ->and($answer->source)->toBe('pagina Contact');
});

it('adds a question the assistant could not answer to its own list', function () {
    askAssistant('Hebben jullie ervaring met Exact Online koppelingen?');

    $question = UnansweredQuestion::sole();

    expect($question->question)->toBe('Hebben jullie ervaring met Exact Online koppelingen?')
        ->and($question->reason)->toBe(UnansweredReason::NoSource)
        ->and($question->status)->toBe(UnansweredStatus::New)
        ->and($question->times_asked)->toBe(1)
        ->and($question->page)->toBe('/');
});

it('counts the same question in other words as one row', function () {
    askAssistant('Hebben jullie ervaring met Exact Online koppelingen?');
    askAssistant('hebben jullie ervaring met exact online koppelingen');
    askAssistant('Ervaring met Exact Online koppelingen??');

    expect(UnansweredQuestion::sole()->times_asked)->toBe(3);
});

it('reopens a resolved question that is asked again', function () {
    $question = UnansweredQuestion::factory()->resolved()->create([
        'question' => 'Hebben jullie ervaring met Exact Online koppelingen?',
        'normalized_key' => UnansweredQuestion::normalize('Hebben jullie ervaring met Exact Online koppelingen?'),
    ]);

    askAssistant('Hebben jullie ervaring met Exact Online koppelingen?');

    expect($question->fresh())
        ->status->toBe(UnansweredStatus::New)
        ->times_asked->toBe(2)
        ->resolution->toBe('Regel toegevoegd aan het kennisbestand.');
});

it('does not keep a question that is unreadable after scrubbing', function () {
    app(TranscriptRecorder::class)->record(
        'b3c1f2de-0000-4000-8000-000000000000',
        '/',
        'jan@bedrijf.nl 06-12345678',
        new Answer('Dat weet ik niet.', unansweredReason: UnansweredReason::NoSource),
    );

    expect(UnansweredQuestion::count())->toBe(0);
});

it('does not count failed answers as unanswered questions', function () {
    app(TranscriptRecorder::class)->record(
        'b3c1f2de-0000-4000-8000-000000000001',
        '/',
        'Wat kost hosting eigenlijk?',
        new Answer('Er ging iets mis.', failed: true),
    );

    expect(UnansweredQuestion::count())->toBe(0)
        ->and(ConversationMessage::where('role', 'assistant')->sole()->status)->toBe('fout');
});

it('deletes transcripts after the retention period but keeps the unanswered list', function () {
    config(['assistant.transcripts.retention_days' => 90]);

    $old = Conversation::factory()->has(ConversationMessage::factory()->count(2), 'messages')->create(['created_at' => now()->subDays(91)]);
    $recent = Conversation::factory()->has(ConversationMessage::factory()->count(2), 'messages')->create(['created_at' => now()->subDays(89)]);
    $question = UnansweredQuestion::factory()->create(['created_at' => now()->subYear()]);

    $this->artisan('model:prune', ['--model' => [Conversation::class]])->assertSuccessful();

    expect(Conversation::pluck('id')->all())->toBe([$recent->id])
        ->and(ConversationMessage::where('conversation_id', $old->id)->count())->toBe(0)
        ->and(UnansweredQuestion::find($question->id))->not->toBeNull();
});

it('schedules the daily pruning of transcripts only', function () {
    $this->artisan('schedule:list')
        ->expectsOutputToContain('model:prune --model')
        ->assertSuccessful();
});

it('limits the number of questions for all visitors together', function () {
    config(['assistant.rate_limits.global_per_minute' => 3]);
    RateLimiter::clear('assistant:global');

    askAssistant('Wat doen jullie?');
    askAssistant('En onze data?');
    askAssistant('Wat kost het?');

    askAssistant('Nog een vraag van een vierde bezoeker')
        ->assertHasErrors('prompt')
        ->assertSee('heel veel vragen');
});

it('takes the page from the request by default', function () {
    expect(Livewire::withoutLazyLoading()->test(AssistantChat::class)->get('page'))->toStartWith('/');
});

it('shows the real retention period under the chat', function () {
    config(['assistant.transcripts.retention_days' => 60]);

    Livewire::test(AssistantChat::class)->assertSee('Dit gesprek wordt 60 dagen bewaard');
});

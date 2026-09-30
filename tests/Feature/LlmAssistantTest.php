<?php

use Illuminate\Support\Facades\Http;
use Shazzoo\Assistant\Assistant;
use Shazzoo\Assistant\Llm\RecordAnswerTool;
use Shazzoo\Assistant\Models\AssistantSettings;
use Shazzoo\Assistant\UnansweredReason;

beforeEach(function () {
    config([
        'assistant.driver' => 'llm',
        'assistant.provider' => 'anthropic',
        'assistant.model' => 'claude-sonnet-5',
        'ai.providers.anthropic.key' => 'test-key',
        'ai.providers.openai.key' => 'test-key',
    ]);

    AssistantSettings::current()->update([
        'name' => 'Joan',
        'company' => 'Voorbeeld B.V.',
        'contact_name' => 'Jasper',
        'contact_phone' => '010 123 4567',
        'contact_email' => 'info@voorbeeld.nl',
    ]);
});

/**
 * Een opgenomen SSE-stream van de Messages API van Anthropic.
 *
 * @param  list<string>  $textChunks
 * @param  array<string, mixed>|null  $record
 */
function claudeStream(array $textChunks, ?array $record, string $stopReason = 'tool_use'): string
{
    $events = [
        ['message_start', ['type' => 'message_start', 'message' => [
            'id' => 'msg_test', 'type' => 'message', 'role' => 'assistant', 'content' => [],
            'model' => 'claude-sonnet-5', 'stop_reason' => null, 'stop_sequence' => null,
            'usage' => ['input_tokens' => 10, 'output_tokens' => 1],
        ]]],
        ['content_block_start', ['type' => 'content_block_start', 'index' => 0, 'content_block' => ['type' => 'text', 'text' => '']]],
    ];

    foreach ($textChunks as $chunk) {
        $events[] = ['content_block_delta', ['type' => 'content_block_delta', 'index' => 0, 'delta' => ['type' => 'text_delta', 'text' => $chunk]]];
    }

    $events[] = ['content_block_stop', ['type' => 'content_block_stop', 'index' => 0]];

    if ($record !== null) {
        $events[] = ['content_block_start', ['type' => 'content_block_start', 'index' => 1, 'content_block' => [
            'type' => 'tool_use', 'id' => 'toolu_test', 'name' => RecordAnswerTool::NAME, 'input' => new stdClass,
        ]]];
        $events[] = ['content_block_delta', ['type' => 'content_block_delta', 'index' => 1, 'delta' => ['type' => 'input_json_delta', 'partial_json' => json_encode($record)]]];
        $events[] = ['content_block_stop', ['type' => 'content_block_stop', 'index' => 1]];
    }

    $events[] = ['message_delta', ['type' => 'message_delta', 'delta' => ['stop_reason' => $stopReason, 'stop_sequence' => null], 'usage' => ['output_tokens' => 20]]];
    $events[] = ['message_stop', ['type' => 'message_stop']];

    $body = collect($events)->map(fn (array $event): string => "event: {$event[0]}\ndata: ".json_encode($event[1])."\n\n")->implode('');

    return $body;
}

function fakeClaude(string $stream): void
{
    Http::fake(['api.anthropic.com/*' => Http::response($stream, 200, ['Content-Type' => 'text/event-stream'])]);
}

/**
 * @return array<string, mixed>
 */
function lastRequestBody(): array
{
    return Http::recorded()->last()[0]->data();
}

it('streams the text and records the source', function () {
    fakeClaude(claudeStream(['Wij bouwen ', 'op Laravel.'], ['bron' => 'pagina Laravel-ontwikkeling', 'status' => 'beantwoord', 'taal' => 'nl']));
    $deltas = [];

    $answer = app(Assistant::class)->answer(
        [['role' => 'user', 'content' => 'Waarop bouwen jullie?']],
        function (string $delta) use (&$deltas): void {
            $deltas[] = $delta;
        },
    );

    expect($deltas)->toBe(['Wij bouwen ', 'op Laravel.'])
        ->and($answer->text)->toBe('Wij bouwen op Laravel.')
        ->and($answer->source)->toBe('pagina Laravel-ontwikkeling')
        ->and($answer->language)->toBe('nl')
        ->and($answer->isAnswered())->toBeTrue()
        ->and($answer->meta['model'])->toBe('claude-sonnet-5');
});

it('sends the earlier turns of the conversation along', function () {
    fakeClaude(claudeStream(['Ja.'], ['bron' => null, 'status' => 'beantwoord', 'taal' => 'nl']));

    app(Assistant::class)->answer([
        ['role' => 'user', 'content' => 'Doen jullie hosting?'],
        ['role' => 'assistant', 'content' => 'Ja, voor WordPress.'],
        ['role' => 'user', 'content' => 'Ook voor Laravel?'],
    ], fn () => null);

    expect(collect(lastRequestBody()['messages'])->pluck('role')->all())->toBe(['user', 'assistant', 'user'])
        ->and(json_encode(lastRequestBody()['messages']))->toContain('Ja, voor WordPress.')->toContain('Ook voor Laravel?');
});

it('records why a question could not be answered', function () {
    fakeClaude(claudeStream(['Dat weet ik niet zeker.'], ['bron' => null, 'status' => 'geen_bron', 'taal' => 'nl']));

    $answer = app(Assistant::class)->answer([['role' => 'user', 'content' => 'Hoeveel omzet draaien jullie?']], fn () => null);

    expect($answer->unansweredReason)->toBe(UnansweredReason::NoSource)
        ->and($answer->source)->toBeNull()
        ->and($answer->isAnswered())->toBeFalse();
});

it('falls back to the not-sure text when the model gives no text', function () {
    fakeClaude(claudeStream([], ['bron' => null, 'status' => 'geen_bron', 'taal' => 'nl']));

    $answer = app(Assistant::class)->answer([['role' => 'user', 'content' => '?']], fn () => null);

    expect($answer->text)->toContain('ik ga het niet gokken')->toContain('Jasper');
});

it('handles a refusal as out of bounds', function () {
    fakeClaude(claudeStream([], null, stopReason: 'refusal'));

    $answer = app(Assistant::class)->answer([['role' => 'user', 'content' => '...']], fn () => null);

    expect($answer->unansweredReason)->toBe(UnansweredReason::OutOfBounds)
        ->and($answer->text)->toContain('010 123 4567');
});

it('shows a friendly message when the API fails', function () {
    Http::fake(['api.anthropic.com/*' => Http::response(['type' => 'error', 'error' => ['type' => 'api_error', 'message' => 'Internal server error']], 500)]);

    $answer = app(Assistant::class)->answer([['role' => 'user', 'content' => 'Hallo']], fn () => null);

    expect($answer->failed)->toBeTrue()
        ->and($answer->text)->toContain('Er ging iets mis');
});

it('sends the instructions and knowledge as a cached system prompt, with the record tool', function () {
    fakeClaude(claudeStream(['Hallo.'], ['bron' => null, 'status' => 'beantwoord', 'taal' => 'nl']));

    app(Assistant::class)->answer([['role' => 'user', 'content' => 'Hallo']], fn () => null);

    $body = lastRequestBody();
    $system = collect($body['system'])->pluck('text')->implode('
');

    expect($body['model'])->toBe('claude-sonnet-5')
        ->and($body['thinking'])->toBe(['type' => 'adaptive'])
        ->and($body['output_config'])->toBe(['effort' => 'medium'])
        ->and($system)->toContain('Je bent Joan, de AI-assistent op de website van Voorbeeld B.V.')->toContain('Jasper')->toContain('<website>')->toContain('<kennisbestand>')->not->toContain('{{')
        ->and(collect($body['system'])->last())->toHaveKey('cache_control')
        ->and(collect($body['tools'])->pluck('name')->all())->toBe([RecordAnswerTool::NAME]);
});

it('leaves out thinking and effort for haiku', function () {
    config(['assistant.model' => 'claude-haiku-4-5']);
    fakeClaude(claudeStream(['Hallo.'], ['bron' => null, 'status' => 'beantwoord', 'taal' => 'nl']));

    app(Assistant::class)->answer([['role' => 'user', 'content' => 'Hallo']], fn () => null);

    expect(lastRequestBody())
        ->not->toHaveKey('thinking')
        ->not->toHaveKey('output_config');
});

it('talks to another provider when configured, without the Claude-only options', function () {
    config(['assistant.provider' => 'openai', 'assistant.model' => null]);
    Http::fake(['api.openai.com/*' => Http::response(['error' => ['message' => 'Testfout']], 500)]);

    $answer = app(Assistant::class)->answer([['role' => 'user', 'content' => 'Hallo']], fn () => null);

    $request = Http::recorded()->last()[0];
    $body = json_encode($request->data());

    expect($request->url())->toStartWith('https://api.openai.com/')
        ->and($body)->toContain('Je bent Joan')->toContain(RecordAnswerTool::NAME)->not->toContain('adaptive')
        ->and($answer->failed)->toBeTrue();
});

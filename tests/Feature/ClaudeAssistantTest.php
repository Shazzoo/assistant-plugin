<?php

use Anthropic\Client;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Shazzoo\Assistant\ClaudeAssistant;
use Shazzoo\Assistant\Knowledge;
use Shazzoo\Assistant\Models\AssistantSettings;
use Shazzoo\Assistant\UnansweredReason;

/**
 * Speelt een vooraf opgenomen SSE-stream af en onthoudt het laatste verzoek.
 */
class FakeAnthropicTransport implements ClientInterface
{
    public ?array $lastRequestBody = null;

    public function __construct(private ResponseInterface $response) {}

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $this->lastRequestBody = json_decode((string) $request->getBody(), true);

        return $this->response;
    }
}

/**
 * @param  list<string>  $textChunks
 * @param  array<string, mixed>|null  $record
 */
function claudeStream(array $textChunks, ?array $record, string $stopReason = 'tool_use'): Response
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
            'type' => 'tool_use', 'id' => 'toolu_test', 'name' => ClaudeAssistant::RECORD_TOOL, 'input' => new stdClass,
        ]]];
        $events[] = ['content_block_delta', ['type' => 'content_block_delta', 'index' => 1, 'delta' => ['type' => 'input_json_delta', 'partial_json' => json_encode($record)]]];
        $events[] = ['content_block_stop', ['type' => 'content_block_stop', 'index' => 1]];
    }

    $events[] = ['message_delta', ['type' => 'message_delta', 'delta' => ['stop_reason' => $stopReason, 'stop_sequence' => null], 'usage' => ['output_tokens' => 20]]];
    $events[] = ['message_stop', ['type' => 'message_stop']];

    $body = collect($events)->map(fn (array $event): string => "event: {$event[0]}\ndata: ".json_encode($event[1])."\n\n")->implode('');

    return new Response(200, ['Content-Type' => 'text/event-stream'], $body);
}

function claudeAssistant(FakeAnthropicTransport $transport, string $model = 'claude-sonnet-5'): ClaudeAssistant
{
    return new ClaudeAssistant(
        client: new Client(apiKey: 'test-key', requestOptions: ['transporter' => $transport, 'streamingTransporter' => $transport, 'maxRetries' => 0]),
        knowledge: app(Knowledge::class),
        settings: AssistantSettings::current()->fill([
            'name' => 'Joan',
            'company' => 'Voorbeeld B.V.',
            'contact_name' => 'Jasper',
            'contact_phone' => '010 123 4567',
            'contact_email' => 'info@voorbeeld.nl',
        ]),
        model: $model,
        effort: 'medium',
        maxTokens: 8000,
    );
}

it('streams the text and records the source', function () {
    $transport = new FakeAnthropicTransport(claudeStream(['Wij bouwen ', 'op Laravel.'], ['bron' => 'pagina Laravel-ontwikkeling', 'status' => 'beantwoord']));
    $deltas = [];

    $answer = claudeAssistant($transport)->answer(
        [['role' => 'user', 'content' => 'Waarop bouwen jullie?']],
        function (string $delta) use (&$deltas): void {
            $deltas[] = $delta;
        },
    );

    expect($deltas)->toBe(['Wij bouwen ', 'op Laravel.'])
        ->and($answer->text)->toBe('Wij bouwen op Laravel.')
        ->and($answer->source)->toBe('pagina Laravel-ontwikkeling')
        ->and($answer->isAnswered())->toBeTrue();
});

it('records why a question could not be answered', function () {
    $transport = new FakeAnthropicTransport(claudeStream(['Dat weet ik niet zeker.'], ['bron' => null, 'status' => 'geen_bron']));

    $answer = claudeAssistant($transport)->answer([['role' => 'user', 'content' => 'Hoeveel omzet draaien jullie?']], fn () => null);

    expect($answer->unansweredReason)->toBe(UnansweredReason::NoSource)
        ->and($answer->source)->toBeNull()
        ->and($answer->isAnswered())->toBeFalse();
});

it('falls back to the not-sure text when the model gives no text', function () {
    $transport = new FakeAnthropicTransport(claudeStream([], ['bron' => null, 'status' => 'geen_bron']));

    $answer = claudeAssistant($transport)->answer([['role' => 'user', 'content' => '?']], fn () => null);

    expect($answer->text)->toContain('ik ga het niet gokken')->toContain('Jasper');
});

it('handles a refusal as out of bounds', function () {
    $transport = new FakeAnthropicTransport(claudeStream([], null, stopReason: 'refusal'));

    $answer = claudeAssistant($transport)->answer([['role' => 'user', 'content' => '...']], fn () => null);

    expect($answer->unansweredReason)->toBe(UnansweredReason::OutOfBounds)
        ->and($answer->text)->toContain('010 123 4567');
});

it('shows a friendly message when the API fails', function () {
    $transport = new FakeAnthropicTransport(new Response(500, ['Content-Type' => 'application/json'], json_encode([
        'type' => 'error', 'error' => ['type' => 'api_error', 'message' => 'Internal server error'],
    ])));

    $answer = claudeAssistant($transport)->answer([['role' => 'user', 'content' => 'Hallo']], fn () => null);

    expect($answer->failed)->toBeTrue()
        ->and($answer->text)->toContain('Er ging iets mis');
});

it('sends the instructions, the knowledge and the record tool', function () {
    $transport = new FakeAnthropicTransport(claudeStream(['Hallo.'], ['bron' => null, 'status' => 'beantwoord']));

    claudeAssistant($transport)->answer([['role' => 'user', 'content' => 'Hallo']], fn () => null);

    $body = $transport->lastRequestBody;

    expect($body['model'])->toBe('claude-sonnet-5')
        ->and($body['thinking'])->toBe(['type' => 'adaptive'])
        ->and($body['output_config'])->toBe(['effort' => 'medium'])
        ->and($body['system'][0]['text'])->toContain('Je bent Joan, de AI-assistent op de website van Voorbeeld B.V.')->toContain('Jasper')->not->toContain('{{')
        ->and($body['system'][1]['text'])->toContain('<website>')->toContain('<kennisbestand>')
        ->and($body['system'][1]['cache_control'])->toBe(['type' => 'ephemeral'])
        ->and($body['tools'][0]['name'])->toBe(ClaudeAssistant::RECORD_TOOL)
        ->and($body['tools'][0]['strict'])->toBeTrue();
});

it('leaves out thinking and effort for haiku', function () {
    $transport = new FakeAnthropicTransport(claudeStream(['Hallo.'], ['bron' => null, 'status' => 'beantwoord']));

    claudeAssistant($transport, model: 'claude-haiku-4-5')->answer([['role' => 'user', 'content' => 'Hallo']], fn () => null);

    expect($transport->lastRequestBody)
        ->not->toHaveKey('thinking')
        ->not->toHaveKey('output_config');
});

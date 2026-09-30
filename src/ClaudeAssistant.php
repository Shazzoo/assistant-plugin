<?php

namespace Shazzoo\Assistant;

use Anthropic\Client;
use Anthropic\Core\Exceptions\APIException;
use Anthropic\Messages\InputJSONDelta;
use Anthropic\Messages\RawContentBlockDeltaEvent;
use Anthropic\Messages\RawContentBlockStartEvent;
use Anthropic\Messages\RawMessageDeltaEvent;
use Anthropic\Messages\RawMessageStartEvent;
use Anthropic\Messages\TextDelta;
use Anthropic\Messages\ToolUseBlock;
use Illuminate\Support\Facades\Log;
use Shazzoo\Assistant\Models\AssistantSettings;

class ClaudeAssistant implements Assistant
{
    public const string RECORD_TOOL = 'antwoord_vastleggen';

    public function __construct(
        private Client $client,
        private Knowledge $knowledge,
        private AssistantSettings $settings,
        private string $model,
        private string $effort,
        private int $maxTokens,
    ) {}

    public function answer(array $conversation, callable $onDelta): Answer
    {
        $meta = ['model' => $this->model, 'stop_reason' => null, 'usage' => []];

        return $this->streamAnswer($conversation, $onDelta, $meta)->withMeta($meta);
    }

    /**
     * @param  list<array{role: 'user'|'assistant', content: string}>  $conversation
     * @param  callable(string): void  $onDelta
     * @param  array{model: string, stop_reason: ?string, usage: array<string, int>}  $meta  Wordt tijdens het streamen gevuld.
     */
    private function streamAnswer(array $conversation, callable $onDelta, array &$meta): Answer
    {
        $text = '';
        $recordJson = '';
        $isRecordBlock = false;
        $stopReason = null;

        try {
            $stream = $this->client->messages->createStream(...$this->requestParameters($conversation));

            foreach ($stream as $event) {
                if ($event instanceof RawMessageStartEvent) {
                    $meta['model'] = $event->message->model;
                    $meta['usage'] = [
                        'input_tokens' => $event->message->usage->inputTokens,
                        'cache_read_input_tokens' => $event->message->usage->cacheReadInputTokens ?? 0,
                        'cache_creation_input_tokens' => $event->message->usage->cacheCreationInputTokens ?? 0,
                        'output_tokens' => $event->message->usage->outputTokens,
                    ];
                } elseif ($event instanceof RawContentBlockStartEvent) {
                    $isRecordBlock = $event->contentBlock instanceof ToolUseBlock
                        && $event->contentBlock->name === self::RECORD_TOOL;
                } elseif ($event instanceof RawContentBlockDeltaEvent) {
                    if ($event->delta instanceof TextDelta) {
                        $text .= $event->delta->text;
                        $onDelta($event->delta->text);
                    } elseif ($event->delta instanceof InputJSONDelta && $isRecordBlock) {
                        $recordJson .= $event->delta->partialJSON;
                    }
                } elseif ($event instanceof RawMessageDeltaEvent) {
                    $stopReason = $event->delta->stopReason ?? $stopReason;
                    $meta['stop_reason'] = $stopReason;
                    $meta['usage']['output_tokens'] = $event->usage->outputTokens;
                }
            }
        } catch (APIException $exception) {
            Log::error('De assistent kon geen antwoord ophalen', ['exception' => $exception->getMessage()]);

            return $this->failure($text, $onDelta);
        }

        if ($stopReason === 'refusal') {
            return new Answer(
                text: $this->appendIfMissing($text, $this->outOfBoundsText(), $onDelta),
                unansweredReason: UnansweredReason::OutOfBounds,
            );
        }

        return $this->answerFromRecord(trim($text), $recordJson, $onDelta);
    }

    /**
     * @param  list<array{role: 'user'|'assistant', content: string}>  $conversation
     * @return array<string, mixed>
     */
    private function requestParameters(array $conversation): array
    {
        $parameters = [
            'model' => $this->model,
            'maxTokens' => $this->maxTokens,
            'system' => [
                ['type' => 'text', 'text' => $this->instructions()],
                // Groot en stabiel: hier zit het cachepunt.
                ['type' => 'text', 'text' => $this->knowledge->render(), 'cacheControl' => ['type' => 'ephemeral']],
            ],
            'messages' => $conversation,
            'tools' => [$this->recordTool()],
            'toolChoice' => ['type' => 'auto'],
        ];

        // Haiku 4.5 kent geen adaptive thinking en geen effort.
        if (! str_starts_with($this->model, 'claude-haiku')) {
            $parameters['thinking'] = ['type' => 'adaptive'];
            $parameters['outputConfig'] = ['effort' => $this->effort];
        }

        return $parameters;
    }

    /**
     * @return array<string, mixed>
     */
    private function recordTool(): array
    {
        return [
            'name' => self::RECORD_TOOL,
            'description' => 'Legt na elk antwoord vast welke bron is gebruikt en of de vraag beantwoord kon worden. De bezoeker ziet dit niet als tekst; de bron verschijnt onder het antwoord.',
            'strict' => true,
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'bron' => [
                        'type' => ['string', 'null'],
                        'description' => 'Alleen websitepagina\'s, bijvoorbeeld "pagina Contact". Null als het antwoord uit het kennisbestand komt of er geen bron is gebruikt.',
                    ],
                    'status' => [
                        'type' => 'string',
                        'enum' => ['beantwoord', 'geen_bron', 'bron_onduidelijk', 'buiten_de_kaders'],
                    ],
                    'taal' => [
                        'type' => 'string',
                        'enum' => ['nl', 'en', 'de', 'fr', 'anders'],
                        'description' => 'De taal waarin de bezoeker schrijft.',
                    ],
                ],
                'required' => ['bron', 'status', 'taal'],
                'additionalProperties' => false,
            ],
        ];
    }

    /**
     * @param  callable(string): void  $onDelta
     */
    private function answerFromRecord(string $text, string $recordJson, callable $onDelta): Answer
    {
        $record = json_decode($recordJson, true);
        $status = is_array($record) ? ($record['status'] ?? null) : null;
        $source = is_array($record) && is_string($record['bron'] ?? null) && trim($record['bron']) !== ''
            ? trim($record['bron'])
            : null;

        $reason = $status === 'beantwoord' ? null : UnansweredReason::tryFrom((string) $status);
        $language = is_array($record) && is_string($record['taal'] ?? null) ? $record['taal'] : null;

        if ($text === '') {
            return new Answer(
                text: $this->appendIfMissing('', $this->notSureText(), $onDelta),
                unansweredReason: $reason ?? UnansweredReason::NoSource,
                language: $language,
            );
        }

        return new Answer(text: $text, source: $reason === null ? $source : null, unansweredReason: $reason, language: $language);
    }

    /**
     * @param  callable(string): void  $onDelta
     */
    private function failure(string $text, callable $onDelta): Answer
    {
        $message = __('assistant::assistant.failed', ['phone' => (string) $this->settings->contact_phone]);

        return new Answer(text: $this->appendIfMissing($text, $message, $onDelta), failed: true);
    }

    /**
     * Voegt een vaste tekst toe (en streamt die) als het model zelf niets bruikbaars gaf.
     *
     * @param  callable(string): void  $onDelta
     */
    private function appendIfMissing(string $text, string $addition, callable $onDelta): string
    {
        $separator = trim($text) === '' ? '' : "\n\n";
        $onDelta($separator.$addition);

        return trim($text.$separator.$addition);
    }

    private function notSureText(): string
    {
        return __('assistant::assistant.not_sure', [
            'contact' => $this->settings->contactName(),
            'phone' => (string) $this->settings->contact_phone,
        ]);
    }

    private function outOfBoundsText(): string
    {
        return __('assistant::assistant.out_of_bounds', [
            'contact' => $this->settings->contactName(),
            'phone' => (string) $this->settings->contact_phone,
            'email' => (string) $this->settings->contact_email,
        ]);
    }

    private function instructions(): string
    {
        return (new Instructions($this->settings))->render();
    }
}

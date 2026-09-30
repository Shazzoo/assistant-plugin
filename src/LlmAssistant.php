<?php

namespace Shazzoo\Assistant;

use Illuminate\Support\Facades\Log;
use Laravel\Ai\Responses\Data\FinishReason;
use Laravel\Ai\Responses\Data\TextUsage;
use Laravel\Ai\Streaming\Events\StreamEnd;
use Laravel\Ai\Streaming\Events\StreamStart;
use Laravel\Ai\Streaming\Events\TextDelta;
use Laravel\Ai\Streaming\Events\ToolCall;
use Shazzoo\Assistant\Llm\AssistantAgent;
use Shazzoo\Assistant\Llm\RecordAnswerTool;
use Shazzoo\Assistant\Models\AssistantSettings;
use Throwable;

/**
 * De assistent via laravel/ai, dus met elke provider die dat ondersteunt: Anthropic (Claude),
 * OpenAI (ChatGPT), Gemini en meer. Welke, staat in config('assistant.provider').
 */
class LlmAssistant implements Assistant
{
    /**
     * @param  array<string, array<string, mixed>>  $providerOptions
     */
    public function __construct(
        private Knowledge $knowledge,
        private AssistantSettings $settings,
        private string $provider,
        private ?string $model,
        private int $maxTokens,
        private array $providerOptions = [],
        private int $timeout = 90,
    ) {}

    public function answer(array $conversation, callable $onDelta): Answer
    {
        $meta = ['model' => $this->model ?? $this->provider, 'stop_reason' => null, 'usage' => []];

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
        $record = null;
        $stopReason = null;

        $question = array_pop($conversation)['content'];

        $agent = new AssistantAgent(
            systemPrompt: $this->instructions()."\n\n".$this->knowledge->render(),
            history: $conversation,
            maxTokens: $this->maxTokens,
            providerOptions: $this->providerOptions,
        );

        try {
            foreach ($agent->stream($question, provider: $this->provider, model: $this->model, timeout: $this->timeout) as $event) {
                if ($event instanceof StreamStart) {
                    $meta['model'] = $event->model;
                } elseif ($event instanceof TextDelta) {
                    $text .= $event->delta;
                    $onDelta($event->delta);
                } elseif ($event instanceof ToolCall && $event->toolCall->name === RecordAnswerTool::NAME) {
                    $record = $event->toolCall->arguments;
                } elseif ($event instanceof StreamEnd) {
                    $stopReason = $event->reason;
                    $meta['stop_reason'] = $stopReason;
                    $meta['usage'] = self::usage($event->usage);
                }
            }
        } catch (Throwable $exception) {
            Log::error('De assistent kon geen antwoord ophalen', ['provider' => $this->provider, 'exception' => $exception->getMessage()]);

            return $this->failure($text, $onDelta);
        }

        // De provider weigerde (bij Claude de stop_reason "refusal").
        if ($stopReason === FinishReason::ContentFilter->value) {
            return new Answer(
                text: $this->appendIfMissing($text, $this->outOfBoundsText(), $onDelta),
                unansweredReason: UnansweredReason::OutOfBounds,
            );
        }

        return $this->answerFromRecord(trim($text), $record, $onDelta);
    }

    /**
     * @param  callable(string): void  $onDelta
     */
    private function answerFromRecord(string $text, ?array $record, callable $onDelta): Answer
    {
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

    /**
     * Tokens zoals de kostenberekening van assistant:eval ze verwacht: input zonder de
     * tokens uit (of naar) de cache, die apart en goedkoper tellen.
     *
     * @return array{input_tokens: int, cache_read_input_tokens: int, cache_creation_input_tokens: int, output_tokens: int}
     */
    public static function usage(TextUsage $usage): array
    {
        $cacheRead = $usage->cacheReadInputTokens ?? 0;
        $cacheWrite = $usage->cacheWriteInputTokens ?? 0;

        return [
            'input_tokens' => max(0, $usage->inputTokens - $cacheRead - $cacheWrite),
            'cache_read_input_tokens' => $cacheRead,
            'cache_creation_input_tokens' => $cacheWrite,
            'output_tokens' => $usage->outputTokens,
        ];
    }
}

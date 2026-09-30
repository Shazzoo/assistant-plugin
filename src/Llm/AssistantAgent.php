<?php

namespace Shazzoo\Assistant\Llm;

use Laravel\Ai\Attributes\CacheInstructions;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasProviderOptions;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Messages\AssistantMessage;
use Laravel\Ai\Messages\Message;
use Laravel\Ai\Messages\UserMessage;
use Laravel\Ai\Promptable;

/**
 * De assistent als agent van laravel/ai: instructies plus kennis als systeemprompt (gecachet,
 * want die is groot en verandert niet tussen vragen), het gesprek tot nu toe, en het
 * hulpmiddel om het antwoord vast te leggen.
 *
 * Eén stap: het model schrijft het antwoord en roept daarna het hulpmiddel aan. Er volgt geen
 * tweede beurt met het resultaat van het hulpmiddel.
 */
#[CacheInstructions]
final class AssistantAgent implements Agent, Conversational, HasProviderOptions, HasTools
{
    use Promptable;

    /**
     * @param  list<array{role: 'user'|'assistant', content: string}>  $history  Het gesprek zonder de laatste vraag.
     * @param  array<string, array<string, mixed>>  $providerOptions  Extra velden per provider, zoals config('assistant.provider_options').
     */
    public function __construct(
        private string $systemPrompt,
        private array $history,
        private int $maxTokens,
        private array $providerOptions = [],
    ) {}

    public function instructions(): string
    {
        return $this->systemPrompt;
    }

    /**
     * @return list<Message>
     */
    public function messages(): iterable
    {
        return array_map(
            fn (array $message): Message => $message['role'] === 'user'
                ? new UserMessage($message['content'])
                : new AssistantMessage($message['content']),
            $this->history,
        );
    }

    public function tools(): iterable
    {
        return [new RecordAnswerTool];
    }

    public function maxSteps(): int
    {
        return 1;
    }

    public function maxTokens(): int
    {
        return $this->maxTokens;
    }

    /**
     * @return array<string, mixed>
     */
    public function providerOptions(Lab|string $provider): array
    {
        $key = $provider instanceof Lab ? $provider->value : $provider;

        return $this->providerOptions[$key] ?? [];
    }
}

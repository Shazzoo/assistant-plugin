<?php

namespace Shazzoo\Assistant\Eval;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\CacheInstructions;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;

/**
 * Het tweede model dat bij assistant:eval een antwoord beoordeelt, met een vast oordeel als uitvoer.
 */
#[CacheInstructions]
final class JudgeAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    public function __construct(private string $systemPrompt) {}

    public function instructions(): string
    {
        return $this->systemPrompt;
    }

    public function maxTokens(): int
    {
        return 16000;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'verzinsels' => $schema->array()->items($schema->string())->required(),
            'geen_verzinsels' => $schema->boolean()->required(),
            'gedrag_ok' => $schema->boolean()->required(),
            'toelichting' => $schema->string()->required(),
        ];
    }
}

<?php

namespace Shazzoo\Assistant;

/**
 * Een volledig antwoord van de assistent, zoals het na het streamen wordt vastgelegd.
 */
final readonly class Answer
{
    /**
     * @param  ?string  $language  Taal van het gesprek (nl, en, ...), zoals de assistent die vastlegt.
     * @param  array{model?: string, stop_reason?: ?string, usage?: array<string, int>}  $meta  Gegevens van het model-antwoord (voor metingen en de testset).
     */
    public function __construct(
        public string $text,
        public ?string $source = null,
        public ?UnansweredReason $unansweredReason = null,
        public bool $failed = false,
        public ?string $language = null,
        public array $meta = [],
    ) {}

    public function isAnswered(): bool
    {
        return $this->unansweredReason === null && ! $this->failed;
    }

    /**
     * @param  array{model?: string, stop_reason?: ?string, usage?: array<string, int>}  $meta
     */
    public function withMeta(array $meta): self
    {
        return new self(
            text: $this->text,
            source: $this->source,
            unansweredReason: $this->unansweredReason,
            failed: $this->failed,
            language: $this->language,
            meta: $meta,
        );
    }
}

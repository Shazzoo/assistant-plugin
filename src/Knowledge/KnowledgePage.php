<?php

namespace Shazzoo\Assistant\Knowledge;

/**
 * Eén pagina (of ander stuk inhoud) zoals de assistent hem als bron krijgt.
 */
final readonly class KnowledgePage
{
    public function __construct(
        public string $title,
        public string $url,
        public string $body,
        public ?string $locale = null,
    ) {}
}

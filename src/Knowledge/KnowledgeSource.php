<?php

namespace Shazzoo\Assistant\Knowledge;

/**
 * Levert inhoud van de site die de assistent als bron gebruikt. Registreer een
 * eigen bron in config('assistant.sources'), bijvoorbeeld voor een plugin.
 *
 * Geef de pagina's steeds in dezelfde volgorde: de kennis moet tussen vragen
 * door gelijk blijven, anders werkt de prompt-cache van de API niet.
 */
interface KnowledgeSource
{
    /**
     * @return iterable<KnowledgePage>
     */
    public function pages(): iterable;
}

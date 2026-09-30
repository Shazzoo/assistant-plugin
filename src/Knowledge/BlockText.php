<?php

namespace Shazzoo\Assistant\Knowledge;

/**
 * Haalt de leesbare tekst uit de blokken van een pagina, zonder de blokken zelf
 * te kennen: alle tekstvelden, behalve technische velden zoals een achtergrond,
 * een link of een afbeelding.
 */
final class BlockText
{
    /** Velden die geen tekst voor bezoekers bevatten. */
    private const string TECHNICAL_KEY = '/(^|_)(type|background|url|href|link|target|anchor|icon|image|media|id|variant|style|position|aside|template|color|size|key)$/';

    /**
     * @param  array<mixed>  $blocks
     */
    public static function from(array $blocks): string
    {
        $lines = [];

        array_walk_recursive($blocks, function (mixed $value, int|string $key) use (&$lines): void {
            if (! is_string($value) || (is_string($key) && preg_match(self::TECHNICAL_KEY, $key) === 1)) {
                return;
            }

            $text = self::clean($value);

            if ($text === '' || preg_match('#^(https?:)?//|^[/\#]#', $text) === 1) {
                return;
            }

            $lines[] = $text;
        });

        return implode("\n", array_values(array_unique($lines)));
    }

    private static function clean(string $value): string
    {
        // Koppen en alinea's uit rich text op een eigen regel.
        $value = preg_replace('#<(br|/p|/h[1-6]|/li)[^>]*>#i', "\n", $value);
        $value = html_entity_decode(strip_tags((string) $value), ENT_QUOTES | ENT_HTML5);

        return trim((string) preg_replace(['/[ \t]+/', '/\s*\n\s*/'], [' ', "\n"], $value));
    }
}

<?php

namespace Shazzoo\Assistant;

/**
 * Haalt contactgegevens uit een tekst voordat die wordt opgeslagen.
 *
 * Vangt e-mailadressen, telefoonnummers, IBAN's, postcodes en straat met
 * huisnummer. Namen van personen en bedrijven herkent dit niet: die houdt
 * de korte bewaartermijn in toom. Beloof dus geen volledigheid.
 */
class PersonalDataScrubber
{
    /** @var array<string, string> Patroon => vervanging, in deze volgorde toegepast. */
    private const array PATTERNS = [
        '/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i' => '[EMAIL]',
        '/\b[A-Z]{2}\d{2}(?:\s?[A-Z0-9]{4}){3,7}(?:\s?[A-Z0-9]{1,3})?\b/i' => '[IBAN]',
        '/(?<![\w+])(?:\+31|0031)\s?(?:\(0\)\s?)?\d(?:[\s-]?\d){8}(?!\d)/' => '[TELEFOON]',
        '/(?<![\w+])0\d(?:[\s-]?\d){8}(?!\d)/' => '[TELEFOON]',
        // Hoofdletters of vast geschreven, zodat "2026 is" geen postcode wordt.
        '/\b\d{4}\s?[A-Z]{2}\b|\b\d{4}[a-z]{2}\b/' => '[POSTCODE]',
        '/\b[A-Z][a-zà-ÿ]+(?:straat|laan|weg|plein|singel|gracht|dijk|kade|hof|pad|steeg|dreef|markt)\s+\d+\s?[a-zA-Z]?\b/u' => '[ADRES]',
    ];

    /**
     * @param  list<string>  $publicDetails  Gegevens van de organisatie zelf die mogen blijven staan.
     */
    public function __construct(private array $publicDetails = []) {}

    public function scrub(string $text): string
    {
        $protected = [];

        foreach ($this->publicDetails as $index => $detail) {
            $token = "\u{2063}PUBLIEK{$index}\u{2063}";
            $protected[$token] = $detail;
            $text = str_ireplace($detail, $token, $text);
        }

        foreach (self::PATTERNS as $pattern => $replacement) {
            $text = preg_replace($pattern, $replacement, $text);
        }

        return strtr($text, $protected);
    }
}

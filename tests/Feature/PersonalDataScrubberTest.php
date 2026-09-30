<?php

use Shazzoo\Assistant\Models\AssistantSettings;
use Shazzoo\Assistant\PersonalDataScrubber;

function scrub(string $text): string
{
    return app(PersonalDataScrubber::class)->scrub($text);
}

it('removes contact details before anything is stored', function (string $input, string $expected) {
    expect(scrub($input))->toBe($expected);
})->with([
    'e-mail' => ['Mail me op jan.jansen@bedrijf.nl graag', 'Mail me op [EMAIL] graag'],
    'mobiel' => ['Bel me op 06-12345678', 'Bel me op [TELEFOON]'],
    'mobiel met spaties' => ['mijn nummer is 06 1234 5678.', 'mijn nummer is [TELEFOON].'],
    'internationaal' => ['+31 6 12345678 of 0031612345678', '[TELEFOON] of [TELEFOON]'],
    'met (0)' => ['+31 (0)40 1234567', '[TELEFOON]'],
    'vast nummer' => ['Ons nummer: 013-5551234', 'Ons nummer: [TELEFOON]'],
    'iban' => ['Overmaken naar NL91 ABNA 0417 1643 00 aub', 'Overmaken naar [IBAN] aub'],
    'iban vast' => ['NL91ABNA0417164300', '[IBAN]'],
    'postcode' => ['Wij zitten op 1234 AB in Tilburg', 'Wij zitten op [POSTCODE] in Tilburg'],
    'postcode vast geschreven' => ['postcode 1234ab', 'postcode [POSTCODE]'],
    'straat en huisnummer' => ['Kom langs op Hoofdstraat 12a', 'Kom langs op [ADRES]'],
]);

it('keeps the public details of the organisation itself', function () {
    AssistantSettings::current()->update([
        'contact_phone' => '010 123 4567',
        'contact_email' => 'info@voorbeeld.nl',
        'public_details' => ['service@voorbeeld.nl', 'Voorbeeldstraat 10', '1234 AB'],
    ]);

    $text = 'Bel 010 123 4567, mail info@voorbeeld.nl of service@voorbeeld.nl, of kom langs op Voorbeeldstraat 10, 1234 AB Rotterdam.';

    expect(scrub($text))->toBe($text);
});

it('leaves ordinary text alone', function (string $text) {
    expect(scrub($text))->toBe($text);
})->with([
    'jaartal' => ['In 2026 is Joan gebouwd.'],
    'bedrag' => ['Het kost € 1.500 per maand.'],
    'tijden' => ['Bereikbaar van 9.00 tot 17.00.'],
    'aantal' => ['We hebben 140 medewerkers en 25 jaar ervaring.'],
]);

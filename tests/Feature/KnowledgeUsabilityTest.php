<?php

use Shazzoo\Assistant\Models\ClientReference;
use Shazzoo\Assistant\Models\Employee;
use Shazzoo\Assistant\Models\KnowledgeEntry;

it('only uses entries that the assistant is allowed to answer', function () {
    $free = KnowledgeEntry::factory()->create();
    $conditional = KnowledgeEntry::factory()->conditional()->create();

    KnowledgeEntry::factory()->never()->create();
    KnowledgeEntry::factory()->withoutAnswer()->create();
    KnowledgeEntry::factory()->create(['answer' => '']);
    KnowledgeEntry::factory()->expired()->create();
    KnowledgeEntry::factory()->conditional()->expired()->create();
    KnowledgeEntry::factory()->conditional(validUntil: null)->create();

    expect(KnowledgeEntry::usable()->pluck('id')->all())
        ->toEqualCanonicalizing([$free->id, $conditional->id]);
});

it('still uses an entry on the last day it is valid', function () {
    $entry = KnowledgeEntry::factory()->conditional(validUntil: 'today')->create();

    expect(KnowledgeEntry::usable()->pluck('id')->all())->toBe([$entry->id]);
});

it('recognises unfinished placeholders in an answer', function (string $answer, bool $expected) {
    expect(KnowledgeEntry::factory()->make(['answer' => $answer])->hasPlaceholder())->toBe($expected);
})->with([
    'amount' => ['Ons uurtarief is [BEDRAG] per uur.', true],
    'name' => ['Vraag het aan [NAAM].', true],
    'finished' => ['Ons uurtarief is 95 euro per uur.', false],
    'markdown link' => ['Zie [de aanpakpagina](https://voorbeeld.nl/aanpak).', false],
]);

it('splits the variants on semicolons', function () {
    $entry = KnowledgeEntry::factory()->make(['variants' => 'uurtarief; wat kost een uur ;; tarief per uur']);

    expect($entry->variantList())->toBe(['uurtarief', 'wat kost een uur', 'tarief per uur']);
});

it('only names employees who consented', function () {
    $named = Employee::factory()->create();
    Employee::factory()->withoutConsent()->create();
    Employee::factory()->create(['consented_at' => null]);

    expect(Employee::namable()->pluck('id')->all())->toBe([$named->id]);
});

it('only names a client when the name was released', function () {
    $anonymous = ClientReference::factory()->make([
        'client' => 'Geheim B.V.',
        'sector' => 'Technische groothandel',
        'size' => '140 medewerkers',
    ]);

    expect($anonymous->publicName())
        ->toBe('een klant in de sector technische groothandel (140 medewerkers)')
        ->not->toContain('Geheim');

    $anonymous->name_released = true;

    expect($anonymous->publicName())->toBe('Geheim B.V.');
});

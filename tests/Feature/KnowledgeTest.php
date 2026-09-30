<?php

use Shazzoo\Assistant\Knowledge;
use Shazzoo\Assistant\Knowledge\CmsPagesSource;
use Shazzoo\Assistant\Knowledge\KnowledgePage;
use Shazzoo\Assistant\Knowledge\KnowledgeSource;
use Shazzoo\Assistant\Models\ClientReference;
use Shazzoo\Assistant\Models\Employee;
use Shazzoo\Assistant\Models\KnowledgeEntry;

beforeEach(function () {
    $this->knowledge = new Knowledge([new class implements KnowledgeSource
    {
        public function pages(): iterable
        {
            yield new KnowledgePage('Over ons', '/over-ons', "Wij zijn de mensen die het ook bouwen\n[N] specialisten, reactie binnen [1 WERKDAG].");
            yield new KnowledgePage('Cases', '/cases', '[KLANT, [N] medewerkers] — pakbonnen.');
        }
    }]);
});

it('includes the website pages with their title and url', function () {
    expect($this->knowledge->render())
        ->toContain('<pagina titel="Over ons" url="/over-ons">')
        ->toContain('Wij zijn de mensen die het ook bouwen');
});

it('replaces unfinished placeholders by a single unknown marker', function (string $input, string $expected) {
    expect(Knowledge::neutralizePlaceholders($input))->toBe($expected);
})->with([
    'simple' => ['Kosten [BEDRAG].', 'Kosten [ONBEKEND].'],
    'starts with digit' => ['Reactie binnen [1 WERKDAG].', 'Reactie binnen [ONBEKEND].'],
    'nested' => ['[KLANT, [N] medewerkers] — pakbonnen.', '[ONBEKEND] — pakbonnen.'],
    'markdown link untouched' => ['Zie [de aanpak](/aanpak).', 'Zie [de aanpak](/aanpak).'],
]);

it('only includes knowledge the assistant may use', function () {
    KnowledgeEntry::factory()->create(['question' => 'Bouwen jullie webshops?', 'answer' => 'Nee, geen webshops.']);
    KnowledgeEntry::factory()->create(['question' => 'Uurtarief?', 'answer' => 'Het is [BEDRAG] per uur.']);
    KnowledgeEntry::factory()->expired()->create(['question' => 'Verlopen vraag?']);
    KnowledgeEntry::factory()->never()->create(['question' => 'Wie is er ziek?']);

    Employee::factory()->create(['name' => 'Leon', 'role' => 'Softwareontwikkelaar', 'hobby' => 'Zeilen']);
    Employee::factory()->withoutConsent()->create(['name' => 'Geheim']);
    Employee::factory()->create(['name' => '[NAAM]']);

    ClientReference::factory()->create(['client' => 'Stille Klant B.V.', 'sector' => 'Logistiek', 'size' => '50 medewerkers']);

    $knowledge = $this->knowledge->render();

    expect($knowledge)
        ->toContain('Nee, geen webshops.')
        ->not->toContain('[BEDRAG]')
        ->not->toContain('Verlopen vraag?')
        ->toContain('Leon · Softwareontwikkelaar')
        ->toContain("hobby's: Zeilen")
        ->not->toContain('Geheim')
        ->not->toContain('[NAAM]')
        ->toContain('een klant in de sector logistiek (50 medewerkers)')
        ->not->toContain('Stille Klant');

    expect(str($knowledge)->between('<nooit>', '</nooit>')->toString())->toContain('Wie is er ziek?');
});

it('renders the same output twice so the prompt cache stays valid', function () {
    KnowledgeEntry::factory()->count(3)->create();

    expect($this->knowledge->render())->toBe($this->knowledge->render());
});

it('uses the text of active CMS pages as the website', function () {
    cmsPage([
        'title' => 'Diensten',
        'slug' => 'diensten',
        'locale' => 'nl',
        'is_active' => true,
        'content' => [
            ['type' => 'hero', 'data' => [
                'title' => 'Wat wij doen',
                'lead' => '<p>Wij bouwen <strong>software</strong> op maat.</p>',
                'background' => 'ground',
                'primary_url' => '/contact',
                'image_id' => '12',
            ]],
            ['type' => 'steps', 'data' => ['items' => [
                ['title' => 'Eerst luisteren', 'body' => 'Dan pas bouwen.'],
            ]]],
        ],
    ]);

    cmsPage([
        'title' => 'Concept',
        'slug' => 'concept',
        'locale' => 'nl',
        'is_active' => false,
        'content' => [['type' => 'text', 'data' => ['body' => 'Nog niet af']]],
    ]);

    $pages = collect((new CmsPagesSource)->pages());

    expect($pages)->toHaveCount(1)
        ->and($pages->first()->title)->toBe('Diensten')
        ->and($pages->first()->url)->toBe('/diensten')
        ->and($pages->first()->body)->toBe("Wat wij doen\nWij bouwen software op maat.\nEerst luisteren\nDan pas bouwen.");
});

it('gives the same knowledge twice in a row, so the prompt cache keeps working', function () {
    cmsPage(['title' => 'Contact', 'slug' => 'contact', 'locale' => 'nl', 'is_active' => true, 'content' => [['type' => 'text', 'data' => ['body' => 'Bel ons.']]]]);
    cmsPage(['title' => 'Over ons', 'slug' => 'over-ons', 'locale' => 'nl', 'is_active' => true, 'content' => [['type' => 'text', 'data' => ['body' => 'Wie wij zijn.']]]]);

    $knowledge = app(Knowledge::class);

    expect($knowledge->render())->toBe($knowledge->render())
        ->toContain('<pagina titel="Contact" url="/contact" taal="nl">');
});

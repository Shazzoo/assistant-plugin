<?php

use Livewire\Livewire;
use Shazzoo\Assistant\Filament\Resources\ClientReferences\Pages\ManageClientReferences;
use Shazzoo\Assistant\Filament\Resources\Employees\Pages\ManageEmployees;
use Shazzoo\Assistant\Filament\Resources\KnowledgeEntries\Pages\CreateKnowledgeEntry;
use Shazzoo\Assistant\Filament\Resources\KnowledgeEntries\Pages\EditKnowledgeEntry;
use Shazzoo\Assistant\Filament\Resources\KnowledgeEntries\Pages\ListKnowledgeEntries;
use Shazzoo\Assistant\Filament\Resources\UnansweredQuestions\Pages\EditUnansweredQuestion;
use Shazzoo\Assistant\Knowledge;
use Shazzoo\Assistant\KnowledgeStatus;
use Shazzoo\Assistant\Models\ClientReference;
use Shazzoo\Assistant\Models\Employee;
use Shazzoo\Assistant\Models\KnowledgeEntry;
use Shazzoo\Assistant\Models\UnansweredQuestion;
use Shazzoo\Assistant\UnansweredStatus;

beforeEach(function () {
    $this->actingAs(adminUser(['email' => 'beheer@voorbeeld.nl', 'name' => 'Beheerder']));
});

it('adds a question that the assistant uses immediately', function () {
    Livewire::test(CreateKnowledgeEntry::class)
        ->fillForm([
            'question' => 'Kan ik bij jullie parkeren?',
            'variants' => ['parkeren', 'parkeerplaats'],
            'answer' => 'Ja, er is gratis parkeren voor de deur.',
            'category' => 'Werkwijze',
            'status' => KnowledgeStatus::Free->value,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $entry = KnowledgeEntry::sole();

    expect($entry->variantList())->toBe(['parkeren', 'parkeerplaats'])
        ->and($entry->isUsedByAssistant())->toBeTrue()
        ->and($entry->updated_by)->toBe('Beheerder')
        ->and($entry->checked_at->isToday())->toBeTrue()
        ->and(app(Knowledge::class)->render())->toContain('Ja, er is gratis parkeren voor de deur.');
});

it('requires an end date for conditional answers and an answer unless never', function () {
    Livewire::test(CreateKnowledgeEntry::class)
        ->fillForm(['question' => 'Hoe snel kunnen jullie starten?', 'answer' => 'Binnen twee weken.', 'status' => KnowledgeStatus::Conditional->value])
        ->call('create')
        ->assertHasFormErrors(['valid_until' => 'required']);

    Livewire::test(CreateKnowledgeEntry::class)
        ->fillForm(['question' => 'Wat is het wifi-wachtwoord?', 'answer' => '', 'status' => KnowledgeStatus::Never->value])
        ->call('create')
        ->assertHasNoFormErrors();

    Livewire::test(CreateKnowledgeEntry::class)
        ->fillForm(['question' => 'Wat is jullie btw-nummer?', 'answer' => '', 'status' => KnowledgeStatus::Free->value])
        ->call('create')
        ->assertHasFormErrors(['answer' => 'required']);
});

it('shows why the assistant does not use a row', function () {
    $placeholder = KnowledgeEntry::factory()->create(['answer' => 'Ons btw-nummer is [VUL IN].']);
    $expired = KnowledgeEntry::factory()->conditional()->expired()->create();
    $fine = KnowledgeEntry::factory()->create();

    expect($placeholder->usageProblem())->toBe('Antwoord bevat nog een plaatshouder')
        ->and($expired->usageProblem())->toStartWith('Verlopen op')
        ->and($fine->usageProblem())->toBeNull();

    Livewire::test(ListKnowledgeEntries::class)
        ->assertCanSeeTableRecords([$placeholder, $expired, $fine])
        ->filterTable('used', false)
        ->assertCanSeeTableRecords([$placeholder, $expired])
        ->assertCanNotSeeTableRecords([$fine]);

    Livewire::test(EditKnowledgeEntry::class, ['record' => $placeholder->getRouteKey()])
        ->assertSee('De assistent gebruikt deze regel nu niet');
});

it('keeps the usage explanation in line with what the assistant really gets', function () {
    KnowledgeEntry::factory()->create();
    KnowledgeEntry::factory()->conditional()->create();
    KnowledgeEntry::factory()->create(['answer' => 'Het is [BEDRAG].']);
    KnowledgeEntry::factory()->expired()->create();
    KnowledgeEntry::factory()->conditional(validUntil: null)->create();
    KnowledgeEntry::factory()->never()->create();
    KnowledgeEntry::factory()->withoutAnswer()->create();

    $knowledge = app(Knowledge::class)->render();

    foreach (KnowledgeEntry::all() as $entry) {
        if ($entry->status === KnowledgeStatus::Never) {
            continue;
        }

        expect(str_contains($knowledge, "<regel id=\"{$entry->id}\""))
            ->toBe($entry->isUsedByAssistant(), "regel {$entry->id}: {$entry->usageProblem()}");
    }
});

it('turns an unanswered question into a knowledge row and resolves it', function () {
    $question = UnansweredQuestion::factory()->create(['question' => 'Kan ik bij jullie parkeren?']);

    Livewire::test(EditUnansweredQuestion::class, ['record' => $question->getRouteKey()])
        ->callAction('addToKnowledge', data: [
            'answer' => 'Ja, er is gratis parkeren voor de deur.',
            'category' => 'Werkwijze',
        ])
        ->assertHasNoActionErrors();

    $entry = KnowledgeEntry::sole();

    expect($entry->question)->toBe('Kan ik bij jullie parkeren?')
        ->and($entry->isUsedByAssistant())->toBeTrue()
        ->and($question->fresh())
        ->status->toBe(UnansweredStatus::Resolved)
        ->resolution->toBe("Regel {$entry->id} toegevoegd aan het kennisbestand.")
        ->resolved_at->not->toBeNull();
});

it('only lets the assistant name an employee with a consent date', function () {
    Livewire::test(ManageEmployees::class)
        ->callAction('create', data: ['name' => 'Leon', 'role' => 'Softwareontwikkelaar', 'may_be_named' => true])
        ->assertHasActionErrors(['consented_at' => 'required']);

    Livewire::test(ManageEmployees::class)
        ->callAction('create', data: ['name' => 'Leon', 'role' => 'Softwareontwikkelaar', 'may_be_named' => true, 'consented_at' => today()->toDateString()])
        ->assertHasNoActionErrors();

    expect(Employee::sole())->usageProblem()->toBeNull()->updated_by->toBe('Beheerder');
});

it('shows how the assistant names each client', function () {
    $anonymous = ClientReference::factory()->create(['client' => 'Stil B.V.', 'sector' => 'Logistiek', 'size' => '50 medewerkers']);

    Livewire::test(ManageClientReferences::class)
        ->assertCanSeeTableRecords([$anonymous])
        ->assertSee('een klant in de sector logistiek (50 medewerkers)');
});

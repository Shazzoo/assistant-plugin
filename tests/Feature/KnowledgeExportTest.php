<?php

use Shazzoo\Assistant\Models\AvatarSettings;
use Shazzoo\Assistant\Models\ClientReference;
use Shazzoo\Assistant\Models\Conversation;
use Shazzoo\Assistant\Models\Employee;
use Shazzoo\Assistant\Models\KnowledgeEntry;

beforeEach(function () {
    $this->path = storage_path('framework/testing/assistent-kennis.json');
});

afterEach(function () {
    @unlink($this->path);
});

it('exports the knowledge, employees and references to a JSON file', function () {
    KnowledgeEntry::factory()->create(['question' => 'Hebben jullie koffie?', 'answer' => 'Altijd.', 'updated_by' => 'beheer@voorbeeld.nl']);
    Employee::factory()->create(['name' => 'Robbert Nillessen', 'hobby' => 'Zeilen']);
    ClientReference::factory()->create(['client' => 'Voorbeeld BV']);

    $this->artisan('assistant:export-knowledge', ['path' => $this->path])->assertSuccessful();

    $export = json_decode(file_get_contents($this->path), true, flags: JSON_THROW_ON_ERROR);

    expect($export)
        ->format->toBe('assistent-kennis')
        ->version->toBe(1)
        ->and($export['knowledge_entries'])->toHaveCount(1)
        ->and($export['knowledge_entries'][0])->toHaveKey('used_by_assistant')->not->toHaveKey('updated_by')
        ->and($export['knowledge_entries'][0])->question->toBe('Hebben jullie koffie?')->answer->toBe('Altijd.')
        ->and($export['employees'][0])->name->toBe('Robbert Nillessen')->hobby->toBe('Zeilen')
        ->and($export['client_references'][0])->client->toBe('Voorbeeld BV');
});

it('leaves out conversations, accounts, settings and keys', function () {
    Conversation::factory()->create();
    adminUser(['email' => 'beheer@voorbeeld.nl']);
    AvatarSettings::current()->setApiKey('la-geheim-1234abcd');

    $this->artisan('assistant:export-knowledge', ['path' => $this->path])->assertSuccessful();

    expect(json_decode(file_get_contents($this->path), true))->toHaveKeys(['knowledge_entries', 'employees', 'client_references'])
        ->and(array_keys(json_decode(file_get_contents($this->path), true)))->toBe(['format', 'version', 'exported_at', 'knowledge_entries', 'employees', 'client_references'])
        ->and(file_get_contents($this->path))->not->toContain('la-geheim')->not->toContain('beheer@voorbeeld.nl');
});

it('writes to a folder that stays out of git by default', function () {
    $this->artisan('assistant:export-knowledge')->assertSuccessful();

    $default = storage_path('app/private/assistent-kennis.json');

    expect($default)->toBeFile();
    unlink($default);
});

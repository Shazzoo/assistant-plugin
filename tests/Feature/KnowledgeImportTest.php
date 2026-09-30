<?php

use Illuminate\Support\Facades\File;
use Shazzoo\Assistant\Models\ClientReference;
use Shazzoo\Assistant\Models\Employee;
use Shazzoo\Assistant\Models\KnowledgeEntry;

beforeEach(function () {
    $this->path = storage_path('framework/testing/kennis-import.json');
    File::ensureDirectoryExists(dirname($this->path));
});

afterEach(function () {
    File::delete($this->path);
});

/**
 * @param  array<string, mixed>  $overrides
 */
function writeExport(string $path, array $overrides = []): void
{
    File::put($path, json_encode([
        'format' => 'joan-kennis',
        'version' => 1,
        'exported_at' => '2026-09-29T16:20:13+02:00',
        'knowledge_entries' => [[
            'id' => 42,
            'question' => 'Wat is jullie uurtarief?',
            'variants' => 'tarief; prijs per uur',
            'answer' => 'Ons uurtarief is € 150 per uur, exclusief btw.',
            'category' => 'Joan',
            'status' => 'vrij',
            'source' => 'opgave directie',
            'valid_until' => null,
            'owner' => 'Jasper',
            'checked_at' => '2026-09-28',
            'used_by_joan' => true,
        ]],
        'employees' => [[
            'name' => 'Leon',
            'role' => 'Softwareontwikkelaar',
            'expertise' => 'Laravel',
            'hobby' => 'Zeilen',
            'years_of_experience' => 8,
            'may_be_named' => true,
            'consented_at' => '2026-09-01',
            'notes' => null,
            'used_by_joan' => true,
        ]],
        'client_references' => [[
            'client' => 'Stille Klant B.V.',
            'sector' => 'Logistiek',
            'size' => '50 medewerkers',
            'what_we_did' => 'Pakbonnen automatisch verwerken',
            'name_released' => false,
            'figures_released' => false,
            'released_at' => null,
            'recorded_in' => null,
        ]],
        ...$overrides,
    ]));
}

it('replaces the knowledge with an export from Joan, keeping the ids', function () {
    KnowledgeEntry::factory()->create(['question' => 'Oude vraag']);
    Employee::factory()->create(['name' => 'Oud']);
    writeExport($this->path);

    $this->artisan('assistant:import-knowledge', ['path' => $this->path, '--force' => true])->assertSuccessful();

    expect(KnowledgeEntry::sole())
        ->id->toBe(42)
        ->category->toBe('Joan')
        ->isUsedByAssistant()->toBeTrue()
        ->and(Employee::sole())->name->toBe('Leon')->hobby->toBe('Zeilen')
        ->and(ClientReference::sole())->publicName()->toBe('een klant in de sector logistiek (50 medewerkers)');
});

it('reads its own export back in', function () {
    KnowledgeEntry::factory()->create(['question' => 'Rondje heen en terug?']);

    $this->artisan('assistant:export-knowledge', ['path' => $this->path])->assertSuccessful();
    KnowledgeEntry::query()->delete();
    $this->artisan('assistant:import-knowledge', ['path' => $this->path, '--force' => true])->assertSuccessful();

    expect(KnowledgeEntry::sole()->question)->toBe('Rondje heen en terug?');
});

it('refuses a file it does not recognise and leaves the knowledge alone', function () {
    KnowledgeEntry::factory()->create(['question' => 'Blijft staan']);
    writeExport($this->path, ['format' => 'iets-anders']);

    $this->artisan('assistant:import-knowledge', ['path' => $this->path, '--force' => true])->assertFailed();

    expect(KnowledgeEntry::sole()->question)->toBe('Blijft staan');
});

it('only fills known columns', function () {
    writeExport($this->path, ['employees' => [['name' => 'Leon', 'role' => 'Ontwikkelaar', 'updated_by' => 'hacker', 'id' => 999]]]);

    $this->artisan('assistant:import-knowledge', ['path' => $this->path, '--force' => true])->assertSuccessful();

    expect(Employee::sole())->updated_by->toBeNull()->id->not->toBe(999);
});

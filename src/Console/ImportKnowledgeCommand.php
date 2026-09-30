<?php

namespace Shazzoo\Assistant\Console;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Shazzoo\Assistant\Models\ClientReference;
use Shazzoo\Assistant\Models\Employee;
use Shazzoo\Assistant\Models\KnowledgeEntry;

#[Signature('assistant:import-knowledge {path : Pad naar een kennisbestand (JSON)} {--force : Niet om bevestiging vragen}')]
#[Description('Vervang het kennisbestand, de medewerkers en de referenties door de inhoud van een export (van assistant:export-knowledge of joan:export-knowledge)')]
class ImportKnowledgeCommand extends Command
{
    /** De formaten die dit commando kan lezen; de vorm is gelijk. */
    public const array FORMATS = ['assistent-kennis', 'joan-kennis'];

    public const int FORMAT_VERSION = 1;

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $path = (string) $this->argument('path');

        if (! File::exists($path)) {
            $this->components->error("Bestand niet gevonden: {$path}");

            return self::FAILURE;
        }

        $export = json_decode(File::get($path), true);

        if (! is_array($export) || ! in_array($export['format'] ?? null, self::FORMATS, true) || ($export['version'] ?? null) !== self::FORMAT_VERSION) {
            $this->components->error('Dit is geen kennisbestand dat ik kan lezen (verwacht: '.implode(' of ', self::FORMATS).', versie '.self::FORMAT_VERSION.').');

            return self::FAILURE;
        }

        if (! $this->option('force') && ! $this->confirm('Dit vervangt het hele kennisbestand, alle medewerkers en alle referenties. Doorgaan?')) {
            return self::FAILURE;
        }

        DB::transaction(function () use ($export): void {
            $this->replace(new KnowledgeEntry, $export['knowledge_entries'] ?? []);
            $this->replace(new Employee, $export['employees'] ?? []);
            $this->replace(new ClientReference, $export['client_references'] ?? []);
        });

        $this->components->info('Kennis geïmporteerd uit '.$path);
        $this->components->twoColumnDetail('Vragen en antwoorden', (string) KnowledgeEntry::query()->count());
        $this->components->twoColumnDetail('Medewerkers', (string) Employee::query()->count());
        $this->components->twoColumnDetail('Referenties', (string) ClientReference::query()->count());

        return self::SUCCESS;
    }

    /**
     * Vervangt alle regels van een model. Alleen de invulbare velden, zodat een export geen
     * andere kolommen kan zetten; het id van een kennisregel blijft gelijk (herleidbaar).
     *
     * @param  list<array<string, mixed>>  $rows
     */
    private function replace(Model $model, array $rows): void
    {
        $model->newQuery()->delete();

        foreach ($rows as $row) {
            $model->newInstance()->forceFill(array_intersect_key($row, array_flip($model->getFillable())))->save();
        }
    }
}

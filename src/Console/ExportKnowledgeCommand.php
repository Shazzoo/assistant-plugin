<?php

namespace Shazzoo\Assistant\Console;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\File;
use Shazzoo\Assistant\Models\ClientReference;
use Shazzoo\Assistant\Models\Employee;
use Shazzoo\Assistant\Models\KnowledgeEntry;

#[Signature('assistant:export-knowledge {path? : Waar het bestand komt (standaard storage/app/private/assistent-kennis.json)}')]
#[Description('Schrijf de kennis van de assistent (vragen en antwoorden, medewerkers, referenties) naar een JSON-bestand')]
class ExportKnowledgeCommand extends Command
{
    /** Verhoog dit als de vorm van het bestand verandert, zodat een inlezer het kan herkennen. */
    public const int FORMAT_VERSION = 1;

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $path = $this->argument('path') ?? storage_path('app/private/assistent-kennis.json');

        $export = [
            'format' => 'assistent-kennis',
            'version' => self::FORMAT_VERSION,
            'exported_at' => now()->toIso8601String(),
            // Alleen de kennis zelf: geen gesprekken, accounts, instellingen of sleutels,
            // en niet wie iets als laatste heeft bewerkt.
            'knowledge_entries' => KnowledgeEntry::query()->orderBy('id')->get()
                ->map(fn (KnowledgeEntry $entry): array => [...$this->fields($entry), 'used_by_assistant' => $entry->isUsedByAssistant()])
                ->all(),
            'employees' => Employee::query()->orderBy('id')->get()
                ->map(fn (Employee $employee): array => [...$this->fields($employee), 'used_by_assistant' => $employee->usageProblem() === null])
                ->all(),
            'client_references' => ClientReference::query()->orderBy('id')->get()
                ->map(fn (ClientReference $reference): array => $this->fields($reference))
                ->all(),
        ];

        File::ensureDirectoryExists(dirname($path));
        File::put($path, json_encode($export, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

        $this->components->info("Kennis geëxporteerd naar {$path}");
        $this->components->twoColumnDetail('Vragen en antwoorden', (string) count($export['knowledge_entries']));
        $this->components->twoColumnDetail('Medewerkers', (string) count($export['employees']));
        $this->components->twoColumnDetail('Referenties', (string) count($export['client_references']));
        $this->components->warn('Dit bestand bevat gegevens van medewerkers. Deel het niet via git, maar rechtstreeks met de ontwikkelaar.');

        return self::SUCCESS;
    }

    /**
     * De invulbare velden van een regel, met datums als JJJJ-MM-DD en statussen als tekst.
     *
     * @return array<string, mixed>
     */
    private function fields(Model $model): array
    {
        return collect($model->getFillable())
            ->mapWithKeys(fn (string $field): array => [$field => $model->getAttribute($field)])
            ->map(fn (mixed $value): mixed => match (true) {
                $value instanceof \DateTimeInterface => $value->format('Y-m-d'),
                $value instanceof \BackedEnum => $value->value,
                default => $value,
            })
            ->all();
    }
}

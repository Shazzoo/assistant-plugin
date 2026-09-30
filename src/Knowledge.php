<?php

namespace Shazzoo\Assistant;

use Illuminate\Support\Collection;
use Shazzoo\Assistant\Knowledge\KnowledgeSource;
use Shazzoo\Assistant\Models\ClientReference;
use Shazzoo\Assistant\Models\Employee;
use Shazzoo\Assistant\Models\KnowledgeEntry;

/**
 * Stelt de kennis samen die de assistent bij elke vraag meekrijgt: de website en het kennisbestand.
 *
 * De uitvoer is deterministisch (vaste volgorde, geen tijdstempels), zodat de
 * prompt-cache van de API tussen vragen door geldig blijft.
 */
class Knowledge
{
    public const string UNKNOWN = '[ONBEKEND]';

    /**
     * @param  iterable<KnowledgeSource>  $sources
     */
    public function __construct(private iterable $sources) {}

    public function render(): string
    {
        return implode("\n\n", [
            $this->website(),
            $this->questions(),
            $this->employees(),
            $this->references(),
            $this->forbidden(),
        ]);
    }

    /**
     * Vervangt nog niet ingevulde plaatshouders zoals [CIJFER] of [KLANT, [N] medewerkers]
     * door één herkenbare markering, zodat de assistent ze als onbekend behandelt.
     */
    public static function neutralizePlaceholders(string $text): string
    {
        $marker = "\u{27E6}?\u{27E7}";

        $text = preg_replace('/\[[A-Z0-9][A-Z0-9 _]*\]/u', $marker, $text);
        $text = preg_replace('/\[[^\[\]]*'.$marker.'[^\[\]]*\]/u', $marker, $text);

        return str_replace($marker, self::UNKNOWN, $text);
    }

    private function website(): string
    {
        $pages = collect();

        foreach ($this->sources as $source) {
            foreach ($source->pages() as $page) {
                $pages->push(sprintf(
                    "<pagina titel=\"%s\" url=\"%s\"%s>\n%s\n</pagina>",
                    e($page->title),
                    e($page->url),
                    $page->locale ? ' taal="'.e($page->locale).'"' : '',
                    self::neutralizePlaceholders($page->body),
                ));
            }
        }

        return "<website>\n".$pages->implode("\n\n")."\n</website>";
    }

    private function questions(): string
    {
        $entries = KnowledgeEntry::query()->usable()->orderBy('id')->get()
            ->reject(fn (KnowledgeEntry $entry): bool => $entry->hasPlaceholder());

        return $this->section('kennisbestand', $entries->map(fn (KnowledgeEntry $entry): string => sprintf(
            "<regel id=\"%d\" categorie=\"%s\">\nVraag: %s%s\nAntwoord: %s\n</regel>",
            $entry->id,
            e((string) $entry->category),
            $entry->question,
            $entry->variantList() === [] ? '' : "\nOok gesteld als: ".implode('; ', $entry->variantList()),
            $entry->answer,
        )));
    }

    private function employees(): string
    {
        $employees = Employee::query()->namable()->orderBy('name')->get()
            ->reject(fn (Employee $employee): bool => preg_match(KnowledgeEntry::PLACEHOLDER_PATTERN, $employee->name) === 1);

        return $this->section('medewerkers', $employees->map(fn (Employee $employee): string => '- '.collect([
            $employee->name,
            $employee->role,
            $employee->expertise,
            $employee->years_of_experience ? "{$employee->years_of_experience} jaar ervaring" : null,
            $employee->hobby ? "hobby's: {$employee->hobby}" : null,
        ])->filter()->implode(' · ')));
    }

    private function references(): string
    {
        $references = ClientReference::query()->orderBy('id')->get();

        return $this->section('referenties', $references->map(fn (ClientReference $reference): string => sprintf(
            '- Noem als: %s. Wat we deden: %s. Cijfers noemen: %s.',
            self::neutralizePlaceholders($reference->publicName()),
            $reference->what_we_did ?? 'niet opgegeven',
            $reference->figures_released ? 'ja, zoals op de website' : 'nee',
        )));
    }

    private function forbidden(): string
    {
        $entries = KnowledgeEntry::query()->forbidden()->orderBy('id')->get();

        return $this->section('nooit', $entries->map(fn (KnowledgeEntry $entry): string => "- {$entry->question}"));
    }

    /**
     * @param  Collection<int, string>  $lines
     */
    private function section(string $name, Collection $lines): string
    {
        $body = $lines->isEmpty() ? '(leeg)' : $lines->implode("\n");

        return "<{$name}>\n{$body}\n</{$name}>";
    }
}

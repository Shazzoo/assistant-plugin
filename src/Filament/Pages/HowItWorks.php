<?php

namespace Shazzoo\Assistant\Filament\Pages;

use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Shazzoo\Assistant\Instructions;
use Shazzoo\Assistant\Knowledge\KnowledgePage;
use Shazzoo\Assistant\Models\AssistantSettings;
use Shazzoo\Assistant\Models\ClientReference;
use Shazzoo\Assistant\Models\Employee;
use Shazzoo\Assistant\Models\KnowledgeEntry;
use UnitEnum;

/**
 * Alleen lezen: met welke instellingen, bronnen en instructies de assistent werkt.
 */
class HowItWorks extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSparkles;

    protected static string|UnitEnum|null $navigationGroup = 'Assistent';

    protected static ?string $slug = 'assistent/hoe-het-werkt';

    protected static ?string $navigationLabel = 'Hoe de assistent werkt';

    protected static ?string $title = 'Hoe de assistent werkt';

    protected static ?int $navigationSort = 3;

    protected string $view = 'assistant::filament.how-it-works';

    public function getSubheading(): string
    {
        return 'Wat de assistent bij elke vraag meekrijgt. Naam, instructies en contactgegevens pas je aan bij de instellingen; laat een wijziging in de instructies eerst door de testset gaan (php artisan assistant:eval).';
    }

    /**
     * @return array<string, string>
     */
    public function settings(): array
    {
        $settings = app(AssistantSettings::class);

        return [
            'Naam' => $settings->assistantName().', voor '.$settings->companyName(),
            'Model' => config('assistant.model').(str_starts_with(config('assistant.model'), 'claude-haiku') ? '' : ' (effort '.config('assistant.effort').')'),
            'Verbindt door naar' => collect([$settings->contactName(), $settings->contact_phone, $settings->contact_email])->filter()->implode(', '),
            '"Stuur dit gesprek mee" gaat naar' => $settings->shareAddress() ?? 'niemand: de knop staat uit',
            'Instructies' => filled($settings->instructions) ? 'eigen tekst' : 'het algemene sjabloon van de plugin',
            'Transcripties bewaard' => config('assistant.transcripts.retention_days').' dagen, geschoond',
            'Maximaal per gesprek' => $settings->max_questions.' vragen van hooguit '.$settings->max_question_length.' tekens',
            'Maximaal voor alle bezoekers samen' => config('assistant.rate_limits.global_per_minute').' vragen per minuut',
        ];
    }

    /**
     * @return array{pages: Collection<int, string>, entries: int, used: int, employees: int, namedEmployees: int, references: int}
     */
    public function sources(): array
    {
        $entries = KnowledgeEntry::all();
        $employees = Employee::all();

        return [
            'pages' => collect(config('assistant.sources', []))
                ->flatMap(fn (string $source): array => iterator_to_array(app($source)->pages(), false))
                ->map(fn (KnowledgePage $page): string => $page->title.($page->locale ? ' ('.strtoupper($page->locale).')' : ''))
                ->values(),
            'entries' => $entries->count(),
            'used' => $entries->filter->isUsedByAssistant()->count(),
            'employees' => $employees->count(),
            'namedEmployees' => $employees->filter(fn (Employee $employee): bool => $employee->usageProblem() === null)->count(),
            'references' => ClientReference::count(),
        ];
    }

    public function instructions(): HtmlString
    {
        return new HtmlString(Str::markdown(app(Instructions::class)->render(), [
            'html_input' => 'escape',
            'allow_unsafe_links' => false,
        ]));
    }
}

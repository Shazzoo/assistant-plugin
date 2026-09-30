<?php

namespace Shazzoo\Assistant\Console;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Shazzoo\Assistant\Answer;
use Shazzoo\Assistant\Assistant;
use Shazzoo\Assistant\Eval\EvalGrader;
use Shazzoo\Assistant\Instructions;
use Shazzoo\Assistant\Knowledge;
use Shazzoo\Assistant\Llm\RecordAnswerTool;
use Shazzoo\Assistant\Models\AssistantSettings;
use Throwable;

#[Signature('assistant:eval
    {--variant=baseline : Naam van de run: baseline, v1, v2, ...}
    {--reps=2 : Hoe vaak elke vraag wordt gesteld}
    {--cases= : Alleen deze vragen (ids, komma-gescheiden)}
    {--provider= : Andere provider voor de assistent, bijvoorbeeld openai of gemini}
    {--model= : Ander model voor de assistent, bijvoorbeeld claude-haiku-4-5}
    {--effort= : Andere effort voor Claude (low, medium, high)}
    {--approve-harness : Keur de huidige versie van runner, beoordelaar en vragen goed}')]
#[Description('Draai de testset van de site via de echte assistent en beoordeel elk antwoord')]
class EvalCommand extends Command
{
    /** Prijzen per miljoen tokens: [input, output]. Cache schrijven 1,25x, lezen 0,1x input. */
    private const array PRICES = [
        'claude-sonnet-5' => [2.0, 10.0],
        'claude-opus-5' => [5.0, 25.0],
        'claude-haiku-4-5' => [1.0, 5.0],
    ];

    /**
     * De map met de testset van deze site (cases.json) en de resultaten per run.
     */
    private function flowPath(): string
    {
        return config('assistant.eval.path') ?? base_path('.claude/hillclimb/assistent-antwoorden');
    }

    /**
     * Bestanden waarvan een wijziging opnieuw goedkeuring vraagt.
     *
     * @return list<string>
     */
    private function harnessPaths(): array
    {
        return [
            __FILE__,
            dirname(__DIR__).'/Eval/EvalGrader.php',
            $this->flowPath().'/cases.json',
        ];
    }

    public function handle(): int
    {
        $variant = (string) $this->option('variant');

        if (! preg_match('/^(baseline|v\d+)$/', $variant)) {
            $this->components->error('Gebruik als variant "baseline" of v1, v2, ...');

            return self::FAILURE;
        }

        if (! $this->harnessIsApproved()) {
            return 2;
        }

        if ($this->option('provider')) {
            config(['assistant.provider' => $this->option('provider')]);
        }

        if ($this->option('model')) {
            config(['assistant.model' => $this->option('model')]);
        }

        if ($this->option('effort')) {
            config(['assistant.provider_options.anthropic.output_config.effort' => $this->option('effort')]);
        }

        config(['assistant.driver' => 'llm']);

        $assistant = app(Assistant::class);
        $grader = new EvalGrader(
            app(Knowledge::class),
            app(AssistantSettings::class),
            config('assistant.eval.provider'),
            config('assistant.eval.model'),
        );

        $cases = $this->cases();
        $reps = max(1, (int) $this->option('reps'));
        $dir = $this->flowPath()."/{$variant}";
        File::ensureDirectoryExists("{$dir}/traces");

        $done = $this->completedAttempts("{$dir}/results.jsonl");
        $todo = count($cases) * $reps - count($done);

        $this->components->info(sprintf('%d vragen × %d keer op %s (%s), %d te gaan.', count($cases), $reps, config('assistant.provider'), config('assistant.model') ?? 'standaardmodel', $todo));

        $bar = $this->output->createProgressBar($todo);
        $started = microtime(true);

        foreach ($cases as $case) {
            for ($rep = 0; $rep < $reps; $rep++) {
                if (isset($done["{$case['id']}#{$rep}"])) {
                    continue;
                }

                $this->runCase($case, $rep, $assistant, $grader, $dir);
                $bar->advance();
            }
        }

        $bar->finish();
        $this->newLine(2);

        $this->summarize("{$dir}/results.jsonl", "{$dir}/errors.jsonl", microtime(true) - $started);

        return self::SUCCESS;
    }

    /**
     * @param  array{id: string, categorie: string, beurten: list<string>, verwacht: string, checks: array<string, mixed>}  $case
     */
    private function runCase(array $case, int $rep, Assistant $assistant, EvalGrader $grader, string $dir): void
    {
        $conversation = [];
        $trace = [['role' => 'system', 'content' => app(Instructions::class)->render()."\n\n(+ de kennis: website en kennisbestand)"]];
        $usage = ['input_tokens' => 0, 'output_tokens' => 0, 'cache_read_input_tokens' => 0, 'cache_creation_input_tokens' => 0];
        $answer = null;
        $latency = 0.0;
        $firstToken = null;

        foreach ($case['beurten'] as $turn) {
            $conversation[] = ['role' => 'user', 'content' => $turn];
            $trace[] = ['role' => 'user', 'content' => $turn];

            $start = microtime(true);
            $firstToken = null;
            $answer = $assistant->answer($conversation, function () use (&$firstToken, $start): void {
                $firstToken ??= microtime(true) - $start;
            });
            $latency = microtime(true) - $start;

            foreach ($answer->meta['usage'] ?? [] as $key => $value) {
                $usage[$key] = ($usage[$key] ?? 0) + $value;
            }

            if ($answer->failed) {
                $this->logError($dir, $case, $rep, 'serving', 'De assistent gaf een foutmelding (API-fout of time-out).', $answer, $usage);

                return;
            }

            if (filled(config('assistant.model')) && ! str_starts_with((string) ($answer->meta['model'] ?? ''), config('assistant.model'))) {
                $this->logError($dir, $case, $rep, 'model_mismatch', "Antwoord kwam van {$answer->meta['model']}, gevraagd was ".config('assistant.model'), $answer, $usage);

                return;
            }

            $conversation[] = ['role' => 'assistant', 'content' => $answer->text];
            $trace[] = ['role' => 'assistant', 'content' => $answer->text];
            $trace[] = ['role' => 'tool_call', 'name' => RecordAnswerTool::NAME, 'content' => json_encode(
                ['bron' => $answer->source, 'status' => $grader->status($answer)],
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
            )];
        }

        try {
            $graded = $grader->grade($case, $conversation, $answer);
        } catch (Throwable $exception) {
            $this->logError($dir, $case, $rep, 'grader', $exception->getMessage(), $answer, $usage);

            return;
        }

        File::put("{$dir}/traces/{$case['id']}_rep{$rep}.json", json_encode($trace, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        $row = [
            'prompt_id' => $case['id'],
            'prompt' => implode("\n→ ", $case['beurten']),
            'tags' => [$case['categorie']],
            'rep' => $rep,
            'model' => $answer->meta['model'],
            'stop_reason' => $answer->meta['stop_reason'] ?? null,
            'status' => ($answer->meta['stop_reason'] ?? null) === 'max_tokens' ? 'truncated' : 'ok',
            'grade' => $graded['grade'],
            'explanation' => $graded['explanation'],
            'usage' => $usage,
            'latency_s' => round($latency, 2),
            'first_token_s' => $firstToken === null ? null : round($firstToken, 2),
            'sentences' => EvalGrader::sentenceCount($answer->text),
            'judge_model' => $graded['judge_model'],
            'judge_usage' => $graded['judge_usage'],
            'meta' => ['bron' => $answer->source, 'status_assistent' => $grader->status($answer), 'antwoord' => $answer->text],
        ];

        File::append("{$dir}/results.jsonl", json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n");
    }

    /**
     * @param  array{id: string}  $case
     * @param  array<string, int>  $usage
     */
    private function logError(string $dir, array $case, int $rep, string $class, string $message, ?Answer $answer, array $usage): void
    {
        File::append("{$dir}/errors.jsonl", json_encode([
            'prompt_id' => $case['id'],
            'rep' => $rep,
            'class' => $class,
            'message' => $message,
            'model' => $answer?->meta['model'] ?? null,
            'usage' => $usage,
            'at' => now()->toIso8601String(),
        ], JSON_UNESCAPED_UNICODE)."\n");
    }

    /**
     * @return list<array{id: string, categorie: string, beurten: list<string>, verwacht: string, checks: array<string, mixed>}>
     */
    private function cases(): array
    {
        $cases = json_decode(File::get($this->flowPath().'/cases.json'), true, flags: JSON_THROW_ON_ERROR);

        if ($only = $this->option('cases')) {
            $ids = array_map('trim', explode(',', $only));
            $cases = array_values(array_filter($cases, fn (array $case): bool => in_array($case['id'], $ids, true)));
        }

        return $cases;
    }

    /**
     * @return array<string, true> Sleutels "id#rep" van pogingen die al een resultaat hebben.
     */
    private function completedAttempts(string $resultsPath): array
    {
        if (! File::exists($resultsPath)) {
            return [];
        }

        return collect(File::lines($resultsPath))
            ->filter()
            ->mapWithKeys(function (string $line): array {
                $row = json_decode($line, true);

                return ["{$row['prompt_id']}#{$row['rep']}" => true];
            })
            ->all();
    }

    /**
     * Voorkomt dat een gewijzigde runner, beoordelaar of vragenlijst ongemerkt
     * scores oplevert die niet meer vergelijkbaar zijn met eerdere runs.
     */
    private function harnessIsApproved(): bool
    {
        $statePath = $this->flowPath().'/_state.json';
        $state = File::exists($statePath) ? json_decode(File::get($statePath), true) : [];

        $sha = hash('sha256', collect($this->harnessPaths())
            ->map(fn (string $path): string => basename($path).':'.hash_file('sha256', $path))
            ->implode("\n"));

        if (($state['harness_sha'] ?? null) === $sha) {
            return true;
        }

        if (! $this->option('approve-harness')) {
            $this->components->error('De runner, beoordelaar of vragenlijst is gewijzigd sinds de laatste goedkeuring. Controleer de wijziging en draai opnieuw met --approve-harness.');

            return false;
        }

        File::put($statePath, json_encode(array_merge($this->defaultState(), $state, [
            'harness_sha' => $sha,
            'harness_paths' => array_map(basename(...), $this->harnessPaths()),
            'harness_approved_at' => now()->toIso8601String(),
        ]), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        $this->components->info('Runner, beoordelaar en vragenlijst goedgekeurd.');

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    private function defaultState(): array
    {
        return [
            'metrics' => [
                ['id' => 'geslaagd', 'label' => 'Geslaagd', 'kind' => 'binary'],
                ['id' => 'controles', 'label' => 'Controles', 'kind' => 'float', 'scale' => 1],
                ['id' => 'gedrag', 'label' => 'Gedrag', 'kind' => 'binary'],
                ['id' => 'geen_verzinsels', 'label' => 'Niets verzonnen', 'kind' => 'binary'],
            ],
            'perf_fields' => [
                ['id' => 'latency_s', 'label' => 'Tijd', 'unit' => 's'],
                ['id' => 'first_token_s', 'label' => 'Eerste woord', 'unit' => 's'],
                ['id' => 'sentences', 'label' => 'Zinnen'],
                ['id' => 'cost_usd', 'label' => 'Kosten', 'unit' => '$'],
            ],
            'prices' => collect(self::PRICES)->map(fn (array $price): array => ['in' => $price[0], 'out' => $price[1]])->all(),
        ];
    }

    private function summarize(string $resultsPath, string $errorsPath, float $seconds): void
    {
        if (! File::exists($resultsPath)) {
            $this->components->warn('Nog geen resultaten.');

            return;
        }

        $rows = collect(File::lines($resultsPath))->filter()->map(fn (string $line): array => json_decode($line, true));
        $scored = $rows->where('status', 'ok');
        $n = max(1, $scored->count());
        $passRate = $scored->avg('grade.geslaagd') ?? 0;
        $margin = 1.96 * sqrt($passRate * (1 - $passRate) / $n);

        $this->components->twoColumnDetail('<fg=white;options=bold>Geslaagd</>', sprintf('%d%% ± %d (%d van %d)', round($passRate * 100), round($margin * 100), $scored->sum('grade.geslaagd'), $scored->count()));
        $this->components->twoColumnDetail('Vaste controles', sprintf('%d%%', round($scored->avg('grade.controles') * 100)));
        $this->components->twoColumnDetail('Gedrag zoals verwacht', sprintf('%d%%', round($scored->avg('grade.gedrag') * 100)));
        $this->components->twoColumnDetail('Niets verzonnen', sprintf('%d%%', round($scored->avg('grade.geen_verzinsels') * 100)));
        $this->components->twoColumnDetail('Tijd per antwoord (mediaan)', sprintf('%.1f s, eerste woord na %.1f s', $scored->median('latency_s'), $scored->median('first_token_s')));

        $assistantCost = $rows->sum(fn (array $row): float => $this->cost($row['model'], $row['usage']));
        $judgeCost = $rows->sum(fn (array $row): float => $this->cost($row['judge_model'], $row['judge_usage']));
        $this->components->twoColumnDetail('Kosten deze resultaten', sprintf('$%.2f assistent + $%.2f beoordelaar', $assistantCost, $judgeCost));

        $errors = File::exists($errorsPath) ? count(array_filter(File::lines($errorsPath)->all())) : 0;
        $truncated = $rows->where('status', 'truncated')->count();
        if ($errors > 0 || $truncated > 0) {
            $this->components->warn("{$errors} mislukte pogingen (errors.jsonl) en {$truncated} afgekapte antwoorden; die tellen niet mee.");
        }

        $this->newLine();
        $this->line('<options=bold>Per groep</>');
        $scored->groupBy(fn (array $row): string => $row['tags'][0])->each(function ($group, string $category): void {
            $this->components->twoColumnDetail($category, sprintf('%d%% (%d/%d)', round($group->avg('grade.geslaagd') * 100), $group->sum('grade.geslaagd'), $group->count()));
        });

        $failed = $scored->where('grade.geslaagd', 0.0);
        if ($failed->isNotEmpty()) {
            $this->newLine();
            $this->line('<options=bold>Niet geslaagd</>');
            $failed->each(fn (array $row) => $this->line(sprintf(
                '  <fg=red>%s</> (keer %d): %s',
                $row['prompt_id'],
                $row['rep'] + 1,
                collect($row['explanation'])->reject(fn (string $text): bool => str_starts_with($text, 'Alle vaste') || str_starts_with($text, 'Geen verzinsels'))->implode(' · '),
            )));
        }

        $this->newLine();
        $this->line(sprintf('Klaar in %d s. Resultaten: %s', round($seconds), str_replace(base_path().DIRECTORY_SEPARATOR, '', $resultsPath)));
    }

    /**
     * @param  array<string, int>  $usage
     */
    private function cost(string $model, array $usage): float
    {
        $price = collect(self::PRICES)->first(fn (array $price, string $id): bool => str_starts_with($model, $id));

        if ($price === null) {
            return 0.0;
        }

        [$in, $out] = $price;

        return (($usage['input_tokens'] ?? 0) * $in
            + ($usage['cache_creation_input_tokens'] ?? 0) * $in * 1.25
            + ($usage['cache_read_input_tokens'] ?? 0) * $in * 0.1
            + ($usage['output_tokens'] ?? 0) * $out) / 1_000_000;
    }
}

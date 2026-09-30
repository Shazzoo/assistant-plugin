<?php

namespace Shazzoo\Assistant\Filament\Widgets;

use Filament\Widgets\ChartWidget;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;
use Shazzoo\Assistant\Filament\Pages\HowItWorks;
use Shazzoo\Assistant\Models\DailyStatistic;

/**
 * Welke pagina's de assistent het vaakst als bron gebruikt: een fout antwoord is zo
 * terug te leiden naar de pagina die hem veroorzaakte.
 */
class SourcesChart extends ChartWidget
{
    protected static ?int $sort = 2;

    protected ?string $heading = 'Meest gebruikte bronnen';

    public function getDescription(): Htmlable
    {
        return new HtmlString(sprintf(
            'Laatste 30 dagen. Antwoorden uit het kennisbestand tellen hier niet mee. <a href="%s" class="underline">Alle bronnen van de assistent</a>',
            e(HowItWorks::getUrl()),
        ));
    }

    protected int|string|array $columnSpan = 'full';

    protected ?string $maxHeight = '320px';

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $sources = DailyStatistic::query()
            ->where('date', '>=', today()->subDays(29))
            ->pluck('sources')
            ->filter()
            ->reduce(function (array $totals, array $day): array {
                foreach ($day as $source => $count) {
                    $totals[$source] = ($totals[$source] ?? 0) + $count;
                }

                return $totals;
            }, []);

        arsort($sources);
        $top = array_slice($sources, 0, 10, true);

        return [
            'datasets' => [[
                'label' => 'Keer als bron gebruikt',
                'data' => array_values($top),
                'backgroundColor' => '#f28a2a',
                'borderColor' => '#d9701a',
            ]],
            'labels' => array_map(fn (string $source): string => preg_replace('/^pagina\s+/i', '', $source), array_keys($top)),
        ];
    }

    protected function getOptions(): array
    {
        return [
            'indexAxis' => 'y',
            'plugins' => ['legend' => ['display' => false]],
            'scales' => ['x' => ['ticks' => ['precision' => 0]]],
        ];
    }
}

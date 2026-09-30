<?php

namespace Shazzoo\Assistant\Filament\Widgets;

use Filament\Support\Enums\IconPosition;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Collection;
use Shazzoo\Assistant\Filament\Resources\Conversations\ConversationResource;
use Shazzoo\Assistant\Filament\Resources\KnowledgeEntries\KnowledgeEntryResource;
use Shazzoo\Assistant\Filament\Resources\UnansweredQuestions\UnansweredQuestionResource;
use Shazzoo\Assistant\Models\AssistantSettings;
use Shazzoo\Assistant\Models\DailyStatistic;
use Shazzoo\Assistant\Models\KnowledgeEntry;
use Shazzoo\Assistant\Models\UnansweredQuestion;
use Shazzoo\Assistant\UnansweredStatus;

/**
 * Samengevatte cijfers over de laatste 30 dagen, uit de dagtellingen.
 */
class StatsOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected ?string $heading = 'Assistent, laatste 30 dagen';

    protected function getStats(): array
    {
        $days = DailyStatistic::query()->where('date', '>=', today()->subDays(29))->orderBy('date')->get();

        $questions = $days->sum('questions');
        $unanswered = $days->sum(fn (DailyStatistic $day): int => $day->unanswered());
        $perDay = fn (string $column): array => $this->perDay($days, $column);

        $openQuestions = UnansweredQuestion::query()->where('status', '!=', UnansweredStatus::Resolved)->count();

        $knowledge = KnowledgeEntry::all();
        $knowledgeUnused = $knowledge->reject->isUsedByAssistant()->count();

        return [
            Stat::make('Gesprekken', number_format($days->sum('conversations'), 0, ',', '.'))
                ->description(number_format($questions, 0, ',', '.').' vragen gesteld')
                ->descriptionIcon(Heroicon::ArrowRight, IconPosition::After)
                ->chart($perDay('conversations'))
                ->url(ConversationResource::getUrl('index')),
            Stat::make('Onbeantwoord', $questions > 0 ? round($unanswered / $questions * 100).'%' : '—')
                ->description(sprintf('%d geen bron · %d onduidelijk · %d buiten de kaders', $days->sum('no_source'), $days->sum('unclear_source'), $days->sum('out_of_bounds')))
                ->descriptionIcon(Heroicon::ArrowRight, IconPosition::After)
                ->color($questions > 0 && $unanswered / $questions > 0.25 ? 'danger' : 'gray')
                ->chart($this->perDay($days, fn (DailyStatistic $day): int => $day->unanswered()))
                ->url(ConversationResource::getUrl('index', ['filters' => ['met_onbeantwoord' => ['isActive' => true]]])),
            Stat::make('Doorgestuurd naar '.app(AssistantSettings::class)->contactName(), number_format($days->sum('shared'), 0, ',', '.'))
                // Wat bezoekers meestuurden, staat alleen in de mailbox; de plugin bewaart het niet.
                ->description('staan in de mailbox van '.(app(AssistantSettings::class)->shareAddress() ?? '(geen adres ingesteld)'))
                ->color('success')
                ->chart($perDay('shared')),
            Stat::make('Werkvoorraad', (string) $openQuestions)
                ->description('onbeantwoorde vragen nog niet afgehandeld')
                ->descriptionIcon(Heroicon::ArrowRight, IconPosition::After)
                ->color($openQuestions > 0 ? 'warning' : 'success')
                ->url(UnansweredQuestionResource::getUrl('index')),
            Stat::make('Kennisbestand', $knowledge->count() - $knowledgeUnused.' van '.$knowledge->count())
                ->description($knowledgeUnused > 0 ? "regels in gebruik, {$knowledgeUnused} nog niet" : 'regels in gebruik')
                ->descriptionIcon(Heroicon::ArrowRight, IconPosition::After)
                ->color($knowledgeUnused > 0 ? 'warning' : 'success')
                ->url($knowledgeUnused > 0
                    ? KnowledgeEntryResource::getUrl('index', ['filters' => ['used' => ['value' => false]]])
                    : KnowledgeEntryResource::getUrl('index')),
        ];
    }

    protected function getColumns(): int
    {
        return 5;
    }

    /**
     * @param  Collection<int, DailyStatistic>  $days
     * @return list<int>
     */
    private function perDay($days, string|callable $value): array
    {
        $byDate = $days->keyBy(fn (DailyStatistic $day): string => $day->date->toDateString());

        return collect(range(29, 0))
            ->map(function (int $daysAgo) use ($byDate, $value): int {
                $day = $byDate->get(today()->subDays($daysAgo)->toDateString());

                return $day === null ? 0 : (is_callable($value) ? $value($day) : (int) $day->{$value});
            })
            ->all();
    }
}

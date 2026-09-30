<?php

namespace Shazzoo\Assistant\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Shazzoo\Assistant\Answer;
use Shazzoo\Assistant\Database\Factories\DailyStatisticFactory;
use Shazzoo\Assistant\UnansweredReason;

/**
 * Tellingen per dag. Blijven staan als de transcripties al zijn verwijderd.
 */
#[UseFactory(DailyStatisticFactory::class)]
#[Fillable(['date', 'conversations', 'questions', 'answered', 'no_source', 'unclear_source', 'out_of_bounds', 'failed', 'shared', 'sources'])]
class DailyStatistic extends Model
{
    /** @use HasFactory<DailyStatisticFactory> */
    use HasFactory;

    protected $table = 'assistant_daily_statistics';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'sources' => 'array',
        ];
    }

    public static function recordAnswer(Answer $answer, bool $isNewConversation): void
    {
        DB::transaction(function () use ($answer, $isNewConversation): void {
            $today = self::today();

            $today->questions++;
            $today->conversations += $isNewConversation ? 1 : 0;

            match (true) {
                $answer->failed => $today->failed++,
                $answer->unansweredReason === UnansweredReason::NoSource => $today->no_source++,
                $answer->unansweredReason === UnansweredReason::UnclearSource => $today->unclear_source++,
                $answer->unansweredReason === UnansweredReason::OutOfBounds => $today->out_of_bounds++,
                default => $today->answered++,
            };

            if ($answer->source !== null) {
                $sources = $today->sources ?? [];

                foreach (array_map('trim', explode(',', $answer->source)) as $source) {
                    $sources[$source] = ($sources[$source] ?? 0) + 1;
                }

                ksort($sources);
                $today->sources = $sources;
            }

            $today->save();
        });
    }

    public static function recordShared(): void
    {
        self::today()->increment('shared');
    }

    public function unanswered(): int
    {
        return $this->no_source + $this->unclear_source + $this->out_of_bounds;
    }

    private static function today(): self
    {
        // Als Carbon meegeven: de date-cast slaat "Y-m-d H:i:s" op, en zo matcht de zoekvraag daarop.
        return self::query()->lockForUpdate()->firstOrCreate(['date' => today()]);
    }
}

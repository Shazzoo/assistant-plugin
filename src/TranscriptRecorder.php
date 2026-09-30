<?php

namespace Shazzoo\Assistant;

use Illuminate\Support\Facades\DB;
use Shazzoo\Assistant\Models\Conversation;
use Shazzoo\Assistant\Models\DailyStatistic;
use Shazzoo\Assistant\Models\UnansweredQuestion;

/**
 * Legt een vraag en het antwoord van de assistent geschoond vast in de transcriptie, en zet
 * een vraag die ze niet kon beantwoorden óók los in de lijst onbeantwoorde vragen.
 */
class TranscriptRecorder
{
    public function __construct(private PersonalDataScrubber $scrubber) {}

    public function record(string $sessionNumber, ?string $page, string $question, Answer $answer): void
    {
        $question = $this->scrubber->scrub($question);

        DB::transaction(function () use ($sessionNumber, $page, $question, $answer): void {
            $conversation = Conversation::query()->firstOrCreate(
                ['session_number' => $sessionNumber],
                ['page' => $page, 'language' => $answer->language],
            );

            if ($conversation->language === null && $answer->language !== null) {
                $conversation->update(['language' => $answer->language]);
            }

            $conversation->messages()->create(['role' => 'user', 'content' => $question]);
            $conversation->messages()->create([
                'role' => 'assistant',
                'content' => $this->scrubber->scrub($answer->text),
                'source' => $answer->source,
                'status' => $answer->failed ? 'fout' : ($answer->unansweredReason?->value ?? 'beantwoord'),
            ]);

            if ($answer->unansweredReason !== null) {
                UnansweredQuestion::recordAsked($question, $answer->unansweredReason, $page);
            }

            DailyStatistic::recordAnswer($answer, $conversation->wasRecentlyCreated);
        });
    }
}

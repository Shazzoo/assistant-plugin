<?php

namespace Shazzoo\Assistant\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Shazzoo\Assistant\Database\Factories\UnansweredQuestionFactory;
use Shazzoo\Assistant\UnansweredReason;
use Shazzoo\Assistant\UnansweredStatus;

/**
 * Een vraag waarop de assistent het antwoord schuldig bleef, gegroepeerd op genormaliseerde vraag.
 */
#[UseFactory(UnansweredQuestionFactory::class)]
#[Fillable(['normalized_key', 'question', 'times_asked', 'first_seen_at', 'last_seen_at', 'page', 'reason', 'status', 'assignee', 'resolution', 'resolved_at'])]
class UnansweredQuestion extends Model
{
    /** @use HasFactory<UnansweredQuestionFactory> */
    use HasFactory;

    protected $table = 'assistant_unanswered_questions';

    /** Woorden die niets over de vraag zeggen en dus niet in de sleutel horen. */
    private const array STOPWORDS = [
        'de', 'het', 'een', 'en', 'of', 'is', 'zijn', 'ben', 'bent', 'jullie', 'je', 'jij', 'u', 'uw', 'ik', 'we', 'wij', 'mijn', 'ons', 'onze',
        'er', 'ook', 'nog', 'wel', 'niet', 'dan', 'dat', 'die', 'dit', 'deze', 'van', 'voor', 'in', 'op', 'aan', 'met', 'bij', 'naar', 'om', 'te',
        'kan', 'kun', 'kunnen', 'hebben', 'heb', 'hebt', 'heeft', 'wat', 'hoe', 'waar', 'wie', 'eigenlijk', 'even', 'graag', 'misschien',
        'the', 'a', 'an', 'do', 'you', 'your', 'is', 'are', 'can', 'what', 'how',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'reason' => UnansweredReason::class,
            'status' => UnansweredStatus::class,
            'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'resolved_at' => 'datetime',
            'times_asked' => 'integer',
        ];
    }

    /**
     * Sleutel waarop dezelfde vraag in andere woorden samenvalt: kleine letters,
     * zonder leestekens, plaatshouders en stopwoorden. Null als er te weinig overblijft.
     */
    public static function normalize(string $question): ?string
    {
        $text = Str::of($question)
            ->replaceMatches('/\[[A-Z]+\]/', ' ')
            ->lower()
            ->ascii()
            ->replaceMatches('/[^a-z0-9\s]/', ' ')
            ->toString();

        $words = array_values(array_filter(
            preg_split('/\s+/', $text),
            fn (string $word): bool => $word !== '' && ! in_array($word, self::STOPWORDS, true),
        ));

        return count($words) < 2 ? null : implode(' ', $words);
    }

    /**
     * Telt een onbeantwoorde vraag: nieuw in de lijst, of een teller erbij.
     * Een afgehandelde vraag die opnieuw gesteld wordt, gaat terug naar "nieuw".
     */
    public static function recordAsked(string $question, UnansweredReason $reason, ?string $page): ?self
    {
        $key = self::normalize($question);

        // Na schoning onleesbaar geworden: levert niets op, dus niet bewaren.
        if ($key === null) {
            return null;
        }

        $existing = self::query()->where('normalized_key', $key)->first();

        if ($existing === null) {
            return self::query()->create([
                'normalized_key' => $key,
                'question' => $question,
                'times_asked' => 1,
                'first_seen_at' => now(),
                'last_seen_at' => now(),
                'page' => $page,
                'reason' => $reason,
                'status' => UnansweredStatus::New,
            ]);
        }

        $existing->times_asked++;
        $existing->last_seen_at = now();
        $existing->reason = $reason;

        if ($existing->status === UnansweredStatus::Resolved) {
            $existing->status = UnansweredStatus::New;
        }

        $existing->save();

        return $existing;
    }
}

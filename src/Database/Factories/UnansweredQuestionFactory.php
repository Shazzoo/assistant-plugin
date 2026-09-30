<?php

namespace Shazzoo\Assistant\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Shazzoo\Assistant\Models\UnansweredQuestion;
use Shazzoo\Assistant\UnansweredReason;
use Shazzoo\Assistant\UnansweredStatus;

/**
 * @extends Factory<UnansweredQuestion>
 */
class UnansweredQuestionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $question = 'Hebben jullie ervaring met '.fake()->unique()->word().' koppelingen?';

        return [
            'normalized_key' => UnansweredQuestion::normalize($question),
            'question' => $question,
            'times_asked' => 1,
            'first_seen_at' => now(),
            'last_seen_at' => now(),
            'page' => '/',
            'reason' => UnansweredReason::NoSource,
            'status' => UnansweredStatus::New,
        ];
    }

    public function resolved(): static
    {
        return $this->state(fn (): array => [
            'status' => UnansweredStatus::Resolved,
            'resolution' => 'Regel toegevoegd aan het kennisbestand.',
            'resolved_at' => now(),
        ]);
    }
}

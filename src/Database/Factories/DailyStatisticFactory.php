<?php

namespace Shazzoo\Assistant\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Shazzoo\Assistant\Models\DailyStatistic;

/**
 * @extends Factory<DailyStatistic>
 */
class DailyStatisticFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'date' => today(),
            'conversations' => 4,
            'questions' => 10,
            'answered' => 7,
            'no_source' => 2,
            'unclear_source' => 0,
            'out_of_bounds' => 1,
            'failed' => 0,
            'shared' => 1,
            'sources' => ['pagina Diensten' => 3, 'pagina Contact' => 1],
        ];
    }
}

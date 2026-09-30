<?php

namespace Shazzoo\Assistant\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Shazzoo\Assistant\Models\AvatarSession;

/**
 * @extends Factory<AvatarSession>
 */
class AvatarSessionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'session_id' => (string) Str::uuid(),
            'sandbox' => false,
            'started_at' => now()->subMinutes(3),
            'ended_at' => now(),
            'end_reason' => 'idle',
        ];
    }

    public function running(): static
    {
        return $this->state(fn (): array => ['started_at' => now()->subMinute(), 'ended_at' => null, 'end_reason' => null]);
    }
}

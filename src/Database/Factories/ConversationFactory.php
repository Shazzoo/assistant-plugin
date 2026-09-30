<?php

namespace Shazzoo\Assistant\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Shazzoo\Assistant\Models\Conversation;

/**
 * @extends Factory<Conversation>
 */
class ConversationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'session_number' => (string) Str::uuid(),
            'page' => '/',
            'language' => 'nl',
        ];
    }
}

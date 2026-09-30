<?php

namespace Shazzoo\Assistant\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Shazzoo\Assistant\Models\Conversation;
use Shazzoo\Assistant\Models\ConversationMessage;

/**
 * @extends Factory<ConversationMessage>
 */
class ConversationMessageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'conversation_id' => Conversation::factory(),
            'role' => 'user',
            'content' => rtrim(fake()->sentence(), '.').'?',
            'source' => null,
            'status' => null,
        ];
    }
}

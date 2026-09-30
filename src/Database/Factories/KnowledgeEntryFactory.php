<?php

namespace Shazzoo\Assistant\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Shazzoo\Assistant\KnowledgeStatus;
use Shazzoo\Assistant\Models\KnowledgeEntry;

/**
 * @extends Factory<KnowledgeEntry>
 */
class KnowledgeEntryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'question' => rtrim(fake()->sentence(), '.').'?',
            'variants' => implode('; ', fake()->words(3)),
            'answer' => fake()->paragraph(),
            'category' => fake()->randomElement(['Diensten', 'Tarieven', 'Mensen', 'Referenties', 'Techniek', 'Werkwijze', 'Juridisch']),
            'status' => KnowledgeStatus::Free,
            'source' => 'pagina Aanpak',
            'valid_until' => null,
            'owner' => fake()->firstName(),
            'checked_at' => today(),
        ];
    }

    public function conditional(?string $validUntil = '+3 months'): static
    {
        return $this->state(fn (): array => [
            'status' => KnowledgeStatus::Conditional,
            'valid_until' => $validUntil ? today()->modify($validUntil) : null,
        ]);
    }

    public function never(): static
    {
        return $this->state(fn (): array => ['status' => KnowledgeStatus::Never]);
    }

    public function expired(): static
    {
        return $this->state(fn (): array => ['valid_until' => today()->subDay()]);
    }

    public function withoutAnswer(): static
    {
        return $this->state(fn (): array => ['answer' => null]);
    }
}

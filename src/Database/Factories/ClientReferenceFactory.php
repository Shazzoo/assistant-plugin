<?php

namespace Shazzoo\Assistant\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Shazzoo\Assistant\Models\ClientReference;

/**
 * @extends Factory<ClientReference>
 */
class ClientReferenceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'client' => fake()->company(),
            'sector' => 'Technische groothandel',
            'size' => fake()->numberBetween(20, 500).' medewerkers',
            'what_we_did' => 'Pakbonnen automatisch inboeken',
            'name_released' => false,
            'figures_released' => true,
            'released_at' => today(),
            'recorded_in' => 'mail van contactpersoon',
        ];
    }

    public function nameReleased(): static
    {
        return $this->state(fn (): array => ['name_released' => true]);
    }
}

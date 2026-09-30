<?php

namespace Shazzoo\Assistant\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Shazzoo\Assistant\Models\Employee;

/**
 * @extends Factory<Employee>
 */
class EmployeeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'role' => 'Softwareontwikkelaar',
            'expertise' => 'Laravel, koppelingen met ERP',
            'hobby' => 'Padel',
            'years_of_experience' => fake()->numberBetween(1, 25),
            'may_be_named' => true,
            'consented_at' => today(),
            'notes' => null,
        ];
    }

    public function withoutConsent(): static
    {
        return $this->state(fn (): array => ['may_be_named' => false, 'consented_at' => null]);
    }
}

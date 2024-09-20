<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Child>
 */
class ChildFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
                'first_name'    => $this->faker->firstName(),
                'last_name'     => $this->faker->lastName(),
                'date_of_birth' => $this->faker->date('Y_m_d'),
                'country'       => $this->faker->country(),
                'city'          => $this->faker->city(),
                'description'   => $this->faker->paragraph(1),
                'team_id'       => $this->faker->numberBetween(1, 3),
        ];
    }
}

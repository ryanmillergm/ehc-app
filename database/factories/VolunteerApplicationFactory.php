<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\VolunteerApplication;
use App\Models\VolunteerNeed;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\VolunteerApplication>
 */
class VolunteerApplicationFactory extends Factory
{
    protected $model = VolunteerApplication::class;
    
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'volunteer_need_id' => VolunteerNeed::factory(),
            'status' => VolunteerApplication::STATUS_SUBMITTED,
            'message' => $this->faker->sentence(16),
            'interests' => ['food'],
            'availability' => ['thursday'],
        ];
    }
}

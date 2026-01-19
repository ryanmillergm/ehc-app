<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\VolunteerApplication;
use App\Models\VolunteerNeed;
use Illuminate\Database\Eloquent\Factories\Factory;

class VolunteerApplicationFactory extends Factory
{
    protected $model = VolunteerApplication::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'volunteer_need_id' => VolunteerNeed::factory(),
            'status' => VolunteerApplication::STATUS_SUBMITTED,
            'answers' => [
                'message' => $this->faker->sentence(16),
            ],
            'interests' => [$this->faker->randomElement(['food', 'prayer', 'kids', 'tech'])],
            'availability' => [
                'sun' => ['am' => (bool) random_int(0, 1), 'pm' => (bool) random_int(0, 1)],
                'mon' => ['am' => false, 'pm' => false],
                'tue' => ['am' => false, 'pm' => false],
                'wed' => ['am' => false, 'pm' => false],
                'thu' => ['am' => false, 'pm' => false],
                'fri' => ['am' => false, 'pm' => false],
                'sat' => ['am' => false, 'pm' => false],
            ],
        ];
    }
}

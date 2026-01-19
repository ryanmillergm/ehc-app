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
                'interests' => ['food'],
                'availability' => [
                    'mon' => ['am' => true, 'pm' => false],
                ],
            ],
        ];
    }
}

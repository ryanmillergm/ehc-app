<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\VolunteerApplication;
use App\Models\VolunteerNeed;
use Illuminate\Database\Seeder;

class VolunteerApplicationSeeder extends Seeder
{
    public function run(): void
    {
        $need = VolunteerNeed::query()->where('slug', 'general')->first();

        if (! $need) {
            return;
        }

        $user = User::factory()->create();

        VolunteerApplication::query()->firstOrCreate(
            ['user_id' => $user->id, 'volunteer_need_id' => $need->id],
            [
                'status' => VolunteerApplication::STATUS_SUBMITTED,
                'answers' => [
                    'message' => 'Happy to serve wherever needed.',
                    'interests' => ['food', 'cleanup'],
                    'availability' => [
                        'mon' => ['am' => true, 'pm' => false],
                        'wed' => ['am' => false, 'pm' => true],
                    ],
                ],
            ]
        );
    }
}

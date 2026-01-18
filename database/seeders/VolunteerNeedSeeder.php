<?php

namespace Database\Seeders;

use App\Models\VolunteerNeed;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class VolunteerNeedSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        VolunteerNeed::query()->firstOrCreate(
            ['slug' => 'general'],
            [
                'title' => 'General Volunteer',
                'description' => 'Volunteer in any area as needs arise.',
                'is_active' => true,
            ]
        );
    }
}

<?php

namespace Database\Seeders;

use App\Models\ApplicationForm;
use Illuminate\Database\Seeder;

class ApplicationFormSeeder extends Seeder
{
    public function run(): void
    {
        ApplicationForm::query()->firstOrCreate(
            ['slug' => 'default-volunteer-form'],
            [
                'name' => 'Default Volunteer Form',
                'description' => 'Default volunteer application questions.',
                'is_active' => true,
                'use_availability' => true,
            ],
        );
    }
}

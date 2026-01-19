<?php

namespace Database\Seeders;

use App\Models\ApplicationForm;
use App\Models\VolunteerNeed;
use Illuminate\Database\Seeder;

class VolunteerNeedSeeder extends Seeder
{
    public function run(): void
    {
        $form = ApplicationForm::query()->where('slug', 'default-volunteer-form')->first();

        if (! $form) {
            $form = ApplicationForm::query()->create([
                'name' => 'Default Volunteer Form',
                'slug' => 'default-volunteer-form',
                'description' => 'Default volunteer application questions.',
                'is_active' => true,
                'use_availability' => true,
            ]);
        }

        VolunteerNeed::query()->firstOrCreate(
            ['slug' => 'general'],
            [
                'title' => 'General Volunteer',
                'description' => 'Volunteer in any area as needs arise.',
                'is_active' => true,
                'application_form_id' => $form->id,
            ]
        );
    }
}

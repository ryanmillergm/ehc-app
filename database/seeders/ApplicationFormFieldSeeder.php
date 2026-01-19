<?php

namespace Database\Seeders;

use App\Models\ApplicationForm;
use Illuminate\Database\Seeder;

class ApplicationFormFieldSeeder extends Seeder
{
    public function run(): void
    {
        $form = ApplicationForm::query()->firstOrCreate(
            ['slug' => 'volunteer-default'],
            [
                'name' => 'Volunteer Default Form',
                'description' => 'Default volunteer application form.',
                'is_active' => true,
                'use_availability' => true,
            ]
        );

        $form->fields()->firstOrCreate(
            ['key' => 'interests'],
            [
                'type' => 'checkbox_group',
                'label' => 'Areas of interest',
                'help_text' => 'Pick one or more.',
                'is_required' => false,
                'is_active' => true,
                'sort' => 20,
                'config' => [
                    'options' => [
                        'food' => 'Food service',
                        'cleanup' => 'Setup / cleanup',
                        'prayer' => 'Prayer + conversation',
                        'logistics' => 'Logistics',
                        'followup' => 'Discipleship follow-up',
                        'admin' => 'Admin help',
                    ],
                ],
            ]
        );
    }
}

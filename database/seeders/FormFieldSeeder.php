<?php

namespace Database\Seeders;

use App\Models\ApplicationForm;
use App\Models\FormField;
use App\Models\FormFieldPlacement;
use Illuminate\Database\Seeder;

class FormFieldSeeder extends Seeder
{
    public function run(): void
    {
        // 1) Ensure the volunteer default form exists
        $form = ApplicationForm::query()->firstOrCreate(
            ['slug' => 'volunteer-default'],
            [
                'name'             => 'Volunteer Default Form',
                'description'      => 'Default volunteer application form.',
                'is_active'        => true,
                'use_availability' => true,
            ]
        );

        // 2) Ensure global reusable fields exist (question library)

        // Message (required)
        $messageField = FormField::query()->updateOrCreate(
            ['key' => 'message'],
            [
                'type'      => 'textarea',
                'label'     => 'Why do you want to volunteer?',
                'help_text' => null,
                'config'    => [
                    'rows'        => 5,
                    'min'         => 10,
                    'max'         => 5000,
                    'placeholder' => 'Share a bit about your heart to serve...',
                ],
            ]
        );

        // Interests (checkbox group)
        $interestsField = FormField::query()->updateOrCreate(
            ['key' => 'interests'],
            [
                'type'      => 'checkbox_group',
                'label'     => 'Areas of interest',
                'help_text' => 'Pick one or more.',
                'config'    => [
                    'options' => [
                        'food'      => 'Food service',
                        'cleanup'   => 'Setup / cleanup',
                        'prayer'    => 'Prayer + conversation',
                        'logistics' => 'Logistics',
                        'followup'  => 'Discipleship follow-up',
                        'admin'     => 'Admin help',
                    ],
                ],
            ]
        );

        // 3) Attach fields to this ApplicationForm via polymorphic placements

        $this->attachField(
            form: $form,
            field: $messageField,
            sort: 10,
            required: true,
        );

        $this->attachField(
            form: $form,
            field: $interestsField,
            sort: 20,
            required: false,
        );
    }

    protected function attachField(
        ApplicationForm $form,
        FormField $field,
        int $sort,
        bool $required = false,
        bool $active = true,
        ?string $labelOverride = null,
        ?string $helpTextOverride = null,
        ?array $configOverride = null,
    ): void {
        FormFieldPlacement::query()->updateOrCreate(
            [
                'fieldable_type' => ApplicationForm::class,
                'fieldable_id'   => $form->id,
                'form_field_id'  => $field->id,
            ],
            [
                'sort'               => $sort,
                'is_required'        => $required,
                'is_active'          => $active,
                'label_override'     => $labelOverride,
                'help_text_override' => $helpTextOverride,
                'config_override'    => $configOverride,
            ]
        );
    }
}

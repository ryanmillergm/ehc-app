<?php

namespace Database\Seeders;

use App\Models\ApplicationForm;
use App\Models\FormField;
use App\Models\FormFieldPlacement;
use Illuminate\Database\Seeder;

class FormFieldPlacementSeeder extends Seeder
{
    public function run(): void
    {
        // Pick your canonical default form slug
        $form = ApplicationForm::query()->firstOrCreate(
            ['slug' => 'volunteer-default'],
            [
                'name'             => 'Volunteer Default Form',
                'description'      => 'Default volunteer application form.',
                'is_active'        => true,
                'use_availability' => true,
                'thank_you_format' => ApplicationForm::THANK_YOU_TEXT,
                'thank_you_content'=> "Thanks! Your application has been received.\nWe’ll be in touch soon.",
            ]
        );

        // Define the global fields you want in the library + how they should be placed on THIS form.
        // (Global config lives on FormField; per-form differences go in placement overrides.)
        $definitions = [
            [
                'field' => [
                    'key'       => 'message',
                    'type'      => 'textarea',
                    'label'     => 'Why do you want to volunteer?',
                    'help_text' => null,
                    'config'    => [
                        'rows'        => 5,
                        'min'         => 10,
                        'max'         => 5000,
                        'placeholder' => 'Share a bit about your heart to serve...',
                    ],
                ],
                'placement' => [
                    'is_required' => true,
                    'is_active'   => true,
                    'sort'        => 10,
                ],
            ],

            [
                'field' => [
                    'key'       => 'phone',
                    'type'      => 'text',
                    'label'     => 'Phone number',
                    'help_text' => 'Optional, but helpful if we need to reach you quickly.',
                    'config'    => [
                        'placeholder' => '(555) 555-5555',
                        'max'         => 50,
                    ],
                ],
                'placement' => [
                    'is_required' => false,
                    'is_active'   => true,
                    'sort'        => 20,
                ],
            ],

            [
                'field' => [
                    'key'       => 'city',
                    'type'      => 'text',
                    'label'     => 'City',
                    'help_text' => null,
                    'config'    => [
                        'placeholder' => 'Denver',
                        'max'         => 80,
                    ],
                ],
                'placement' => [
                    'is_required' => false,
                    'is_active'   => true,
                    'sort'        => 30,
                ],
            ],

            [
                'field' => [
                    'key'       => 'interests',
                    'type'      => 'checkbox_group',
                    'label'     => 'Areas of interest',
                    'help_text' => 'Pick one or more.',
                    // normalized options shape
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
                ],
                'placement' => [
                    'is_required' => false,
                    'is_active'   => true,
                    'sort'        => 40,
                ],
            ],

            [
                'field' => [
                    'key'       => 'tshirt_size',
                    'type'      => 'select',
                    'label'     => 'T-shirt size',
                    'help_text' => null,
                    'config'    => [
                        'options' => [
                            'S' => 'Small',
                            'M' => 'Medium',
                            'L' => 'Large',
                            'XL'=> 'XL',
                        ],
                    ],
                ],
                'placement' => [
                    'is_required' => false,
                    'is_active'   => true,
                    'sort'        => 50,
                ],
            ],

            [
                'field' => [
                    'key'       => 'background_check',
                    'type'      => 'toggle',
                    'label'     => 'Background check OK?',
                    'help_text' => null,
                    'config'    => [],
                ],
                'placement' => [
                    'is_required' => false,
                    'is_active'   => true,
                    'sort'        => 60,
                ],
            ],
        ];

        foreach ($definitions as $def) {
            $fieldData = $def['field'];
            $placementData = $def['placement'];

            // Normalize "flat key/value options" into config['options'] for option fields.
            $fieldData['config'] = $this->normalizeFieldConfig(
                type: (string) $fieldData['type'],
                config: (array) ($fieldData['config'] ?? []),
            );

            // 1) Ensure global field exists
            /** @var FormField $field */
            $field = FormField::query()->updateOrCreate(
                ['key' => $fieldData['key']],
                [
                    'type'      => $fieldData['type'],
                    'label'     => $fieldData['label'],
                    'help_text' => $fieldData['help_text'] ?? null,
                    'config'    => $fieldData['config'] ?? [],
                ]
            );

            // 2) Ensure placement exists for this form (polymorphic)
            FormFieldPlacement::query()->updateOrCreate(
                [
                    'fieldable_type' => ApplicationForm::class,
                    'fieldable_id'   => $form->getKey(),
                    'form_field_id'  => $field->getKey(),
                ],
                [
                    'is_required'      => (bool) ($placementData['is_required'] ?? false),
                    'is_active'        => (bool) ($placementData['is_active'] ?? true),
                    'sort'             => (int) ($placementData['sort'] ?? 100),
                    'label_override'   => $placementData['label_override'] ?? null,
                    'help_text_override'=> $placementData['help_text_override'] ?? null,
                    'config_override'  => $this->normalizePlacementConfigOverride(
                        type: (string) $field->type,
                        override: (array) ($placementData['config_override'] ?? []),
                    ),
                ]
            );
        }
    }

    /**
     * Make sure option-based fields always end up with config['options'].
     * Supports both:
     *  - normalized: ['options' => ['a' => 'A']]
     *  - legacy/flat: ['a' => 'A', 'b' => 'B']
     */
    private function normalizeFieldConfig(string $type, array $config): array
    {
        if (! in_array($type, ['radio', 'select', 'checkbox_group'], true)) {
            return $config;
        }

        // If already normalized, keep it.
        if (array_key_exists('options', $config) && is_array($config['options'])) {
            return $config;
        }

        // Otherwise treat the config as flat key/value options.
        return ['options' => $config];
    }

    /**
     * Placement overrides can also be option-based; normalize the same way.
     */
    private function normalizePlacementConfigOverride(string $type, array $override): array
    {
        if (! in_array($type, ['radio', 'select', 'checkbox_group'], true)) {
            return $override;
        }

        if (array_key_exists('options', $override) && is_array($override['options'])) {
            return $override;
        }

        // If override is empty, keep empty (don't force options => [])
        if ($override === []) {
            return [];
        }

        return ['options' => $override];
    }
}

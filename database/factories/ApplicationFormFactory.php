<?php

namespace Database\Factories;

use App\Models\ApplicationForm;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ApplicationForm>
 */
class ApplicationFormFactory extends Factory
{
    protected $model = ApplicationForm::class;

    public function definition(): array
    {
        $name = $this->faker->words(3, true);

        return [
            'name' => Str::title($name),
            'slug' => Str::slug($name) . '-' . $this->faker->unique()->numberBetween(1000, 9999),
            'description' => $this->faker->optional()->sentence(12),
            'is_active' => true,
            'use_availability' => true,

            // thank you settings
            'thank_you_format' => 'text', // text|html
            'thank_you_content' => "Thanks! Your volunteer application has been submitted.",
        ];
    }

    public function inactive(): self
    {
        return $this->state(['is_active' => false]);
    }

    public function withoutAvailability(): self
    {
        return $this->state(['use_availability' => false]);
    }

    public function thankYouText(string $text): self
    {
        return $this->state([
            'thank_you_format' => 'text',
            'thank_you_content' => $text,
        ]);
    }

    public function thankYouHtml(string $html): self
    {
        return $this->state([
            'thank_you_format' => 'html',
            'thank_you_content' => $html,
        ]);
    }

    /**
     * A good "default volunteer form" starting point:
     * - message is auto-created by ApplicationForm::booted()
     * - we also add an "interests" checkbox group field by default
     */
    public function volunteerDefault(): self
    {
        return $this->afterCreating(function (ApplicationForm $form) {
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
                ],
            );
        });
    }
}

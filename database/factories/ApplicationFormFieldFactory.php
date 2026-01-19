<?php

namespace Database\Factories;

use App\Models\ApplicationForm;
use App\Models\ApplicationFormField;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ApplicationFormFieldFactory extends Factory
{
    protected $model = ApplicationFormField::class;

    public function definition(): array
    {
        $key = Str::slug($this->faker->unique()->words(2, true), '_');

        return [
            'application_form_id' => ApplicationForm::factory(),
            'type' => 'text',
            'key' => $key,
            'label' => Str::title(str_replace('_', ' ', $key)),
            'help_text' => null,
            'is_required' => false,
            'is_active' => true,
            'sort' => 100,
            'config' => [],
        ];
    }

    public function required(): self
    {
        return $this->state(['is_required' => true]);
    }

    public function textarea(): self
    {
        return $this->state([
            'type' => 'textarea',
            'config' => ['rows' => 5],
        ]);
    }

    public function checkboxGroup(array $options = ['a' => 'Option A']): self
    {
        return $this->state([
            'type' => 'checkbox_group',
            'config' => ['options' => $options],
        ]);
    }
}

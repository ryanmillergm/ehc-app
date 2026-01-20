<?php

namespace Database\Factories;

use App\Models\ApplicationForm;
use App\Models\FormField;
use App\Models\FormFieldPlacement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FormFieldPlacement>
 */
class FormFieldPlacementFactory extends Factory
{
    protected $model = FormFieldPlacement::class;

    public function definition(): array
    {
        return [
            // default “fieldable” is ApplicationForm
            'fieldable_type'    => ApplicationForm::class,
            'fieldable_id'      => ApplicationForm::factory(),

            'form_field_id'     => FormField::factory(),

            'is_required'       => false,
            'is_active'         => true,
            'sort'              => 100,

            'label_override'    => null,
            'help_text_override'=> null,
            'config_override'   => [],
        ];
    }

    public function forForm(ApplicationForm $form): static
    {
        return $this->state(fn () => [
            'fieldable_type' => ApplicationForm::class,
            'fieldable_id'   => $form->getKey(),
        ]);
    }

    public function forField(FormField $field): static
    {
        return $this->state(fn () => [
            'form_field_id' => $field->getKey(),
        ]);
    }

    public function required(bool $required = true): static
    {
        return $this->state(fn () => [
            'is_required' => $required,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => [
            'is_active' => false,
        ]);
    }

    public function sort(int $sort): static
    {
        return $this->state(fn () => [
            'sort' => $sort,
        ]);
    }
}

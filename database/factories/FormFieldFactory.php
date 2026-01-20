<?php

namespace Database\Factories;

use App\Models\FormField;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<FormField>
 */
class FormFieldFactory extends Factory
{
    protected $model = FormField::class;

    public function definition(): array
    {
        return [
            'key'       => 'field_' . Str::lower(Str::random(8)),
            'type'      => 'text',
            'label'     => $this->faker->sentence(4),
            'help_text' => $this->faker->optional()->sentence(),
            'config'    => [],
        ];
    }

    /**
     * Normalized options config: ['options' => [...]]
     */
    public function withOptions(array $options = ['option1' => 'option1', 'option2' => 'option2']): static
    {
        return $this->state(fn () => [
            'config' => ['options' => $options],
        ]);
    }

    /**
     * Flat/legacy config: ['option1' => 'option1', ...]
     * This matches the “bad old shape” you want to support in rendering/tests.
     */
    public function withFlatOptions(array $options = ['option1' => 'option1', 'option2' => 'option2']): static
    {
        return $this->state(fn () => [
            'config' => $options,
        ]);
    }

    public function radio(array $options = ['option1' => 'option1', 'option2' => 'option2']): static
    {
        return $this->state(fn () => [
            'type'   => 'radio',
            'config' => ['options' => $options],
        ]);
    }

    public function select(array $options = ['option1' => 'option1', 'option2' => 'option2']): static
    {
        return $this->state(fn () => [
            'type'   => 'select',
            'config' => ['options' => $options],
        ]);
    }

    public function checkboxGroup(array $options = ['option1' => 'option1', 'option2' => 'option2']): static
    {
        return $this->state(fn () => [
            'type'   => 'checkbox_group',
            'config' => ['options' => $options],
        ]);
    }

    public function textarea(int $rows = 5): static
    {
        return $this->state(fn () => [
            'type'   => 'textarea',
            'config' => ['rows' => $rows],
        ]);
    }
}

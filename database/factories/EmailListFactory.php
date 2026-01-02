<?php

namespace Database\Factories;

use App\Models\EmailList;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<\App\Models\EmailList>
 */
class EmailListFactory extends Factory
{
    protected $model = EmailList::class;

    public function definition(): array
    {
        $label = $this->faker->words(2, true);

        return [
            'key' => Str::slug($label) . '-' . Str::lower(Str::random(6)),
            'label' => Str::title($label),
            'description' => $this->faker->optional()->sentence(),
            'purpose' => 'marketing',
            'is_default' => false,
            'is_opt_outable' => true,
        ];
    }

    public function marketing(): static
    {
        return $this->state(fn () => ['purpose' => 'marketing']);
    }

    public function transactional(): static
    {
        return $this->state(fn () => ['purpose' => 'transactional']);
    }

    public function default(): static
    {
        return $this->state(fn () => ['is_default' => true]);
    }

    public function notOptOutable(): static
    {
        return $this->state(fn () => ['is_opt_outable' => false]);
    }
}

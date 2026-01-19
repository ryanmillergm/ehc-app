<?php

namespace Database\Factories;

use App\Models\ApplicationForm;
use App\Models\VolunteerNeed;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class VolunteerNeedFactory extends Factory
{
    protected $model = VolunteerNeed::class;

    public function definition(): array
    {
        $title = $this->faker->words(2, true);

        return [
            'title' => Str::title($title),
            'slug' => Str::slug($title) . '-' . $this->faker->unique()->numberBetween(1000, 9999),
            'description' => $this->faker->sentence(12),
            'is_active' => true,
            'event_id' => null,
            'capacity' => null,
            'application_form_id' => ApplicationForm::factory(),
        ];
    }

    public function general(): self
    {
        return $this->state([
            'title' => 'General Volunteer',
            'slug' => 'general',
            'description' => 'Volunteer in any area as needs arise.',
            'is_active' => true,
        ]);
    }
}

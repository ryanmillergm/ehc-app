<?php

namespace Database\Factories;

use App\Models\VolunteerNeed;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\VolunteerNeed>
 */
class VolunteerNeedFactory extends Factory
{
    protected $model = VolunteerNeed::class;
    
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = $this->faker->words(2, true);

        return [
            'title' => Str::title($title),
            'slug' => Str::slug($title),
            'description' => $this->faker->sentence(12),
            'is_active' => true,
            'event_id' => null,
            'capacity' => null,
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

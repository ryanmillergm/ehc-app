<?php

namespace Database\Factories;

use App\Models\PageTranslation;
use App\Models\Video;
use App\Models\Videoable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Videoable>
 */
class VideoableFactory extends Factory
{
    protected $model = Videoable::class;

    public function definition(): array
    {
        return [
            'video_id' => Video::factory(),
            'videoable_type' => PageTranslation::class,
            'videoable_id' => PageTranslation::factory(),
            'role' => $this->faker->randomElement(['hero_video', 'featured_video']),
            'sort_order' => $this->faker->numberBetween(0, 20),
            'is_active' => true,
        ];
    }
}

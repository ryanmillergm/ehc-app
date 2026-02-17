<?php

namespace Database\Factories;

use App\Models\ImageGroup;
use App\Models\ImageGroupable;
use App\Models\PageTranslation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ImageGroupable>
 */
class ImageGroupableFactory extends Factory
{
    protected $model = ImageGroupable::class;

    public function definition(): array
    {
        return [
            'image_group_id' => ImageGroup::factory(),
            'image_groupable_type' => PageTranslation::class,
            'image_groupable_id' => PageTranslation::factory(),
            'role' => $this->faker->randomElement(['gallery', 'carousel']),
            'sort_order' => $this->faker->numberBetween(0, 20),
            'is_active' => true,
        ];
    }
}

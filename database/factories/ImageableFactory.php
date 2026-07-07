<?php

namespace Database\Factories;

use App\Models\Image;
use App\Models\Imageable;
use App\Models\PageTranslation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Imageable>
 */
class ImageableFactory extends Factory
{
    protected $model = Imageable::class;

    public function definition(): array
    {
        return [
            'image_id' => Image::factory(),
            'imageable_type' => PageTranslation::class,
            'imageable_id' => PageTranslation::factory(),
            'role' => $this->faker->randomElement(['header', 'featured', 'og', 'thumbnail']),
            'sort_order' => $this->faker->numberBetween(0, 20),
            'is_active' => true,
        ];
    }
}

<?php

namespace Database\Factories;

use App\Models\Image;
use App\Models\SiteMediaDefault;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SiteMediaDefault>
 */
class SiteMediaDefaultFactory extends Factory
{
    protected $model = SiteMediaDefault::class;

    public function definition(): array
    {
        return [
            'role' => $this->faker->unique()->randomElement(['header', 'featured', 'og', 'thumbnail']),
            'image_id' => Image::factory(),
        ];
    }
}

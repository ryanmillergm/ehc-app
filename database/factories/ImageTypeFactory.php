<?php

namespace Database\Factories;

use App\Models\ImageType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ImageType>
 */
class ImageTypeFactory extends Factory
{
    protected $model = ImageType::class;

    public function definition(): array
    {
        $name = ucfirst($this->faker->word());

        return [
            'name' => $name,
            'slug' => Str::slug($name) . '-' . $this->faker->unique()->numberBetween(100, 999),
            'description' => $this->faker->sentence(),
            'is_active' => true,
        ];
    }
}

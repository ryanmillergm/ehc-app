<?php

namespace Database\Factories;

use App\Models\ImageGroup;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ImageGroup>
 */
class ImageGroupFactory extends Factory
{
    protected $model = ImageGroup::class;

    public function definition(): array
    {
        $name = $this->faker->words(2, true);

        return [
            'name' => ucfirst($name),
            'slug' => Str::slug($name) . '-' . $this->faker->unique()->numberBetween(100, 999),
            'description' => $this->faker->sentence(),
            'is_active' => true,
        ];
    }
}

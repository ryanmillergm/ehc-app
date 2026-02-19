<?php

namespace Database\Factories;

use App\Models\Language;
use App\Models\RouteSeo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RouteSeo>
 */
class RouteSeoFactory extends Factory
{
    protected $model = RouteSeo::class;

    public function definition(): array
    {
        return [
            'route_key' => fake()->randomElement(array_keys(RouteSeo::routeOptions())),
            'language_id' => Language::factory(),
            'seo_title' => fake()->sentence(6),
            'seo_description' => fake()->sentence(16),
            'seo_og_image' => 'https://cdn.example.org/seo-' . fake()->slug() . '.jpg',
            'canonical_path' => '/example-path',
            'is_active' => true,
        ];
    }
}

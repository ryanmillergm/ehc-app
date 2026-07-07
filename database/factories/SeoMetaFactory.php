<?php

namespace Database\Factories;

use App\Models\Language;
use App\Models\SeoMeta;
use App\Support\Seo\RouteSeoTarget;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SeoMeta>
 */
class SeoMetaFactory extends Factory
{
    protected $model = SeoMeta::class;

    public function definition(): array
    {
        return [
            'seoable_type' => 'route',
            'seoable_id' => 0,
            'target_key' => fake()->randomElement(array_keys(RouteSeoTarget::options())),
            'language_id' => Language::factory(),
            'seo_title' => fake()->sentence(6),
            'seo_description' => fake()->sentence(16),
            'seo_og_image' => 'https://cdn.example.org/seo-' . fake()->slug() . '.jpg',
            'canonical_path' => '/example-path',
            'robots' => 'index,follow',
            'is_active' => true,
        ];
    }

    public function routeTarget(string $routeKey): self
    {
        return $this->state([
            'seoable_type' => 'route',
            'seoable_id' => 0,
            'target_key' => $routeKey,
        ]);
    }
}

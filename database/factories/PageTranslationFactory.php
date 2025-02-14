<?php

namespace Database\Factories;

use App\Models\Language;
use App\Models\Page;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PageTranslation>
 */
class PageTranslationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $page = Page::inRandomOrder()->first();
        if (empty($page)) {
            $page = Page::factory()->create();
        }

        $language = Language::inRandomOrder()->first();
        if (empty($language)) {
            $language = Language::factory()->create();
        }

        return [
            'title'         => $this->faker->text(30),
            'is_active'     => $this->faker->boolean(),

            'page_id'       => $page->id,
            'language_id'   => $language->id,
            'title'         => $this->faker->sentence(3),
            'slug'          => $this->faker->slug(),
            'description'   => $this->faker->paragraph(3),
            'content'       => $this->faker->randomHtml(),
            'is_active'     => $this->faker->boolean(),
        ];
    }
}

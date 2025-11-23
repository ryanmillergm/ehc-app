<?php

namespace Database\Factories;

use App\Models\Language;
use App\Models\Page;
use App\Models\PageTranslation;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PageTranslation>
 */
class PageTranslationFactory extends Factory
{
    protected $model = PageTranslation::class;

    public function definition(): array
    {
        $title = $this->faker->sentence(3);
        $base  = Str::slug($title);

        return [
            // relations: default to factories, no DB lookups / no side-effects
            'page_id'     => Page::factory(),
            'language_id' => Language::factory(),

            'title'       => $title,
            'slug'        => $base . '-' . $this->faker->unique()->numberBetween(1000, 9999),
            'description' => $this->faker->paragraph(2),
            'content'     => '<p>' . $this->faker->paragraph(4) . '</p>',
            'is_active'   => true,
        ];
    }

    /**
     * Handy state for inactive translations.
     */
    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }

    /**
     * Explicit active state (sometimes nice for readability).
     */
    public function active(): static
    {
        return $this->state(fn () => ['is_active' => true]);
    }

    /**
     * Force a specific language easily:
     * PageTranslation::factory()->forLanguage($lang)->create();
     */
    public function forLanguage(Language $language): static
    {
        return $this->state(fn () => ['language_id' => $language->id]);
    }

    /**
     * Force a specific locale easily:
     * PageTranslation::factory()->forLocale('es')->create();
     *
     * This DOES query the DB, but only when you opt-in.
     */
    public function forLocale(string $locale): static
    {
        return $this->state(function () use ($locale) {
            $lang = Language::where('locale', $locale)->first();

            return [
                'language_id' => $lang?->id ?? Language::factory()->state([
                    'title' => ucfirst($locale),
                    'iso_code' => $locale,
                    'locale' => $locale,
                    'right_to_left' => in_array($locale, ['ar', 'he', 'fa', 'ur']),
                ]),
            ];
        });
    }

    /**
     * Force a specific page easily:
     * PageTranslation::factory()->forPage($page)->create();
     */
    public function forPage(Page $page): static
    {
        return $this->state(fn () => ['page_id' => $page->id]);
    }

    /**
     * Convenience: ensure slug matches a given base.
     * Example: ->slugBase('about-us')
     */
    public function slugBase(string $base): static
    {
        return $this->state(fn () => [
            'slug' => Str::slug($base) . '-' . $this->faker->unique()->numberBetween(1000, 9999),
        ]);
    }
}

<?php

namespace Database\Factories;

use App\Models\Language;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Language>
 */
class LanguageFactory extends Factory
{
    protected $model = Language::class;

    public function definition(): array
    {
        // Faker languageCode is ISO 639-1 (en, es, fr, ar, etc.)
        // We make it unique *against the DB*, not just Faker's internal unique list.
        $code = $this->uniqueLanguageCode();

        return [
            'title'         => ucfirst($this->faker->word()),
            'iso_code'      => $code,
            'locale'        => $code,
            'right_to_left' => in_array($code, ['ar', 'he', 'fa', 'ur']),
        ];
    }

    /**
     * Generate a language code that doesn't already exist in the DB.
     * This prevents random factory languages from colliding with seeded ones.
     */
    protected function uniqueLanguageCode(): string
    {
        do {
            $code = $this->faker->languageCode();
        } while (Language::where('iso_code', $code)->exists());

        return $code;
    }

    // --- States that mirror LanguageSeeder ---

    public function english(): static
    {
        return $this->state(fn () => [
            'title'         => 'English',
            'iso_code'      => 'en',
            'locale'        => 'en',
            'right_to_left' => false,
        ]);
    }

    public function spanish(): static
    {
        return $this->state(fn () => [
            'title'         => 'Spanish',
            'iso_code'      => 'es',
            'locale'        => 'es',
            'right_to_left' => false,
        ]);
    }

    public function french(): static
    {
        return $this->state(fn () => [
            'title'         => 'French',
            'iso_code'      => 'fr',
            'locale'        => 'fr',
            'right_to_left' => false,
        ]);
    }

    public function arabic(): static
    {
        return $this->state(fn () => [
            'title'         => 'Arabic',
            'iso_code'      => 'ar',
            'locale'        => 'ar',
            'right_to_left' => true,
        ]);
    }

    // Optional: force RTL on any random language
    public function rtl(): static
    {
        return $this->state(fn () => ['right_to_left' => true]);
    }
}

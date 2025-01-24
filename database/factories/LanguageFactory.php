<?php

namespace Database\Factories;

use App\Models\Language;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Language>
 */
class LanguageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $code = $this->getLanguageCode();

        return [
            'title'         => $this->faker->word(),
            'iso_code'      => $code,
            'locale'        => $code,
            'right_to_left' => $this->faker->boolean(),
        ];
    }

    public function getLanguageCode()
    {
        $code = $this->faker->languageCode();
        $language = Language::where('iso_code')->get()->first();
        if ($language) {
            return $this->getLanguageCode();
        } else {
            return $code;
        }
    }
}

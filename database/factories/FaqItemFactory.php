<?php

namespace Database\Factories;

use App\Models\FaqItem;
use App\Models\Language;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FaqItem>
 */
class FaqItemFactory extends Factory
{
    protected $model = FaqItem::class;

    public function definition(): array
    {
        return [
            'context' => 'home',
            'language_id' => Language::factory(),
            'question' => rtrim($this->faker->sentence(8), '.') . '?',
            'answer' => $this->faker->paragraph(2),
            'sort_order' => $this->faker->numberBetween(0, 20),
            'is_active' => true,
        ];
    }
}

<?php

namespace Database\Factories;

use App\Models\HomeSection;
use App\Models\HomeSectionItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HomeSectionItem>
 */
class HomeSectionItemFactory extends Factory
{
    protected $model = HomeSectionItem::class;

    public function definition(): array
    {
        return [
            'home_section_id' => HomeSection::factory(),
            'item_key' => 'default',
            'label' => $this->faker->word(),
            'title' => $this->faker->sentence(4),
            'description' => $this->faker->sentence(12),
            'value' => null,
            'url' => null,
            'sort_order' => 0,
            'is_active' => true,
        ];
    }
}

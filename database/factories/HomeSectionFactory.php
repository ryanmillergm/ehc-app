<?php

namespace Database\Factories;

use App\Enums\HomeSectionKey;
use App\Models\HomeSection;
use App\Models\Language;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HomeSection>
 */
class HomeSectionFactory extends Factory
{
    protected $model = HomeSection::class;

    public function definition(): array
    {
        return [
            'language_id' => Language::factory(),
            'section_key' => HomeSectionKey::Hero->value,
            'eyebrow' => $this->faker->sentence(4),
            'heading' => $this->faker->sentence(6),
            'subheading' => $this->faker->sentence(8),
            'body' => $this->faker->paragraph(),
            'note' => $this->faker->sentence(12),
            'cta_primary_label' => 'Primary CTA',
            'cta_primary_url' => '#',
            'sort_order' => 0,
            'is_active' => true,
        ];
    }
}

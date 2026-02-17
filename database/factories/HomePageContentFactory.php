<?php

namespace Database\Factories;

use App\Models\HomePageContent;
use App\Models\Image;
use App\Models\Language;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HomePageContent>
 */
class HomePageContentFactory extends Factory
{
    protected $model = HomePageContent::class;

    public function definition(): array
    {
        return [
            'language_id' => Language::factory(),
            'seo_title' => 'Homeless Ministry in Sacramento, CA | Bread of Grace Ministries',
            'seo_description' => 'Bread of Grace Ministries serves Sacramento through homeless outreach, hot meals, housing pathways, discipleship, and practical support.',
            'hero_intro' => $this->faker->sentence(18),
            'meeting_schedule' => 'Thursday + Sunday • 11:00am',
            'meeting_location' => 'Township 9 Park • Sacramento',
            'hero_image_id' => Image::factory(),
            'featured_image_id' => Image::factory(),
            'og_image_id' => Image::factory(),
            'is_active' => true,
        ];
    }
}

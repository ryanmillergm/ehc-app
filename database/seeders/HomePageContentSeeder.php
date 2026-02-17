<?php

namespace Database\Seeders;

use App\Models\HomePageContent;
use App\Models\Image;
use App\Models\Language;
use Illuminate\Database\Seeder;

class HomePageContentSeeder extends Seeder
{
    public function run(): void
    {
        $english = Language::query()->firstOrCreate(
            ['iso_code' => 'en'],
            [
                'title' => 'English',
                'iso_code' => 'en',
                'locale' => 'en',
                'right_to_left' => false,
            ]
        );

        $hero = Image::query()->where('disk', 'public')->where('path', 'cms/legacy/sm/the-mayor.jpg')->first();
        $featured = Image::query()->where('disk', 'public')->where('path', 'cms/legacy/sm/lisa-hug.jpg')->first();
        $og = Image::query()->where('disk', 'public')->where('path', 'cms/legacy/sm/the-mayor.jpg')->first();

        HomePageContent::query()->updateOrCreate(
            ['language_id' => $english->id],
            [
                'seo_title' => 'Homeless Ministry in Sacramento, CA | Bread of Grace Ministries',
                'seo_description' => 'Bread of Grace Ministries serves Sacramento through homeless outreach, hot meals, housing pathways, discipleship, and practical support. Give to help feed the hungry and support the needy.',
                'hero_intro' => 'As a homeless ministry in Sacramento, California, we feed the hungry, help the needy, and walk with people through Christ-centered mentorship, practical support, and pathways to stable housing.',
                'meeting_schedule' => 'Thursday + Sunday • 11:00am',
                'meeting_location' => 'Township 9 Park • Sacramento',
                'hero_image_id' => $hero?->id,
                'featured_image_id' => $featured?->id,
                'og_image_id' => $og?->id,
                'is_active' => true,
            ]
        );
    }
}

<?php

namespace Database\Seeders;

use App\Models\Image;
use App\Models\Imageable;
use App\Models\Language;
use App\Models\Page;
use App\Models\PageTranslation;
use Illuminate\Database\Seeder;

class HomelessMinistrySacramentoPageSeeder extends Seeder
{
    public function run(): void
    {
        $english = Language::query()->firstOrCreate(
            ['locale' => 'en'],
            ['title' => 'English', 'iso_code' => 'en', 'right_to_left' => false]
        );

        $page = Page::query()->firstOrCreate(
            ['title' => 'Homeless Ministry Sacramento'],
            ['is_active' => true]
        );

        if (! $page->is_active) {
            $page->forceFill(['is_active' => true])->save();
        }

        $translation = PageTranslation::query()->updateOrCreate(
            [
                'page_id' => $page->id,
                'language_id' => $english->id,
            ],
            [
                'title' => 'Homeless Ministry in Sacramento',
                'slug' => 'homeless-ministry-sacramento',
                'description' => 'A Sacramento homeless ministry serving people through hot meals, outreach, discipleship, and practical pathways toward stability.',
                'content' => <<<HTML
                    <h2>Helping the homeless in Sacramento with consistency and dignity</h2>
                    <p>Bread of Grace Ministries is a homeless ministry in Sacramento focused on both immediate needs and long-term restoration. We serve meals, provide essentials, pray with people, and build trusted relationships over time.</p>
                    <p>Our approach is practical and personal: show up weekly, listen well, meet urgent needs, and walk with people toward stability through mentorship, discipleship, and community connection.</p>

                    <h2>Where outreach happens</h2>
                    <p>We gather at Township 9 Park in Sacramento every Thursday and Sunday at 11:00am. Outreach includes food service, prayer, encouragement, and follow-up support for people navigating homelessness.</p>

                    <h2>What your donation makes possible</h2>
                    <ul>
                        <li>Hot meals and hydration served with dignity</li>
                        <li>Hygiene and survival essentials for daily stability</li>
                        <li>Faith-based mentorship, prayer, and discipleship support</li>
                        <li>Guidance toward housing and employment pathways</li>
                    </ul>

                    <h2>How to help now</h2>
                    <p>If you want to help the homeless in Sacramento, you can give today, join outreach in person, or subscribe for ministry updates and prayer needs.</p>
                    HTML,
                'template' => 'campaign',
                'theme' => 'warm',
                'hero_mode' => 'image',
                'hero_title' => 'Homeless Ministry in Sacramento, CA',
                'hero_subtitle' => 'Serving people experiencing homelessness through outreach, meals, discipleship, and practical support every week.',
                'hero_cta_text' => 'Give to Support Outreach',
                'hero_cta_url' => '/give',
                'layout_data' => [
                    'eyebrow' => 'Bread of Grace Ministries - Sacramento',
                    'trust_badges' => ['Serving since 2010', 'Weekly outreach', 'Christ-centered care'],
                    'impact_stats' => [
                        ['label' => 'Weekly Outreach', 'value' => '2 Days'],
                        ['label' => 'Location', 'value' => 'Township 9 Park'],
                        ['label' => 'Focus', 'value' => 'Meals + Mentorship'],
                    ],
                    'quick_facts' => [
                        'Thursday + Sunday • 11:00am',
                        'Sacramento, California',
                        'Volunteer-friendly team',
                    ],
                    'cta_secondary_text' => 'Volunteer With Us',
                    'cta_secondary_url' => '/#serve',
                    'faq_teaser_title' => 'Questions about helping the homeless in Sacramento?',
                    'faq_teaser_body' => 'Visit our homepage FAQ section for how donations are used, where outreach happens, and how to get involved.',
                ],
                'seo_title' => 'Homeless Ministry in Sacramento, CA | Bread of Grace Ministries',
                'seo_description' => 'Homeless ministry in Sacramento providing meals, outreach, discipleship, and practical support. Give to help Bread of Grace Ministries serve weekly.',
                'seo_og_image' => '/images/sm/the-mayor.jpg',
                'is_active' => true,
                'published_at' => now(),
            ]
        );

        $hero = Image::query()
            ->where('disk', 'public')
            ->where('path', 'cms/legacy/sm/the-mayor.jpg')
            ->first();

        if ($hero) {
            Imageable::query()->updateOrCreate(
                [
                    'imageable_type' => PageTranslation::class,
                    'imageable_id' => $translation->id,
                    'role' => 'header',
                ],
                [
                    'image_id' => $hero->id,
                    'sort_order' => 1,
                    'is_active' => true,
                ]
            );
        }
    }
}

<?php

namespace Database\Seeders;

use App\Enums\HomeSectionKey;
use App\Models\HomeSection;
use App\Models\Image;
use App\Models\Language;
use Illuminate\Database\Seeder;

class HomeSectionSeeder extends Seeder
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

        $heroImage = Image::query()->where('disk', 'public')->where('path', 'cms/legacy/sm/the-mayor.jpg')->first();
        $featuredImage = Image::query()->where('disk', 'public')->where('path', 'cms/legacy/sm/lisa-hug.jpg')->first();
        $parallaxImage = Image::query()->where('disk', 'public')->where('path', 'cms/legacy/sm/bible-scriptures.jpg')->first();
        $serveImage = Image::query()->where('disk', 'public')->where('path', 'cms/legacy/sm/bike-path-road.jpg')->first();
        $giveImage = Image::query()->where('disk', 'public')->where('path', 'cms/legacy/sm/group-joseph-peace.jpg')->first();

        $hero = $this->upsertSection($english->id, HomeSectionKey::Hero->value, [
            'eyebrow' => 'Bread of Grace Ministries',
            'heading' => 'Help restore lives through God\'s Word and practical support.',
            'subheading' => 'Serving since 2010',
            'body' => 'As a homeless ministry in Sacramento, California, we feed the hungry, help the needy, and walk with people through Christ-centered mentorship, practical support, and pathways to stable housing.',
            'note' => 'Church Without Walls • Thu + Sun 11am',
            'cta_primary_label' => 'Give today',
            'cta_primary_url' => '#give-form',
            'cta_secondary_label' => 'Volunteer with us',
            'cta_secondary_url' => '#serve',
            'cta_tertiary_label' => 'Visit Thursday/Sunday →',
            'cta_tertiary_url' => '#visit',
            'image_id' => $heroImage?->id,
            'sort_order' => 10,
            'is_active' => true,
            'meta' => [
                'location' => 'Sacramento, CA',
                'meeting_schedule' => 'Thursday + Sunday • 11:00am',
                'meeting_location' => 'Township 9 Park • Sacramento',
                'scripture_text' => '“And whoever gives one of these little ones only a cup of cold water... shall by no means lose his reward.”',
                'scripture_reference' => 'Matthew 10:42',
                'secondary_image_id' => $featuredImage?->id,
            ],
        ]);

        $this->syncItems($hero, [
            ['item_key' => 'quick_choice', 'label' => 'Give', 'title' => 'Fuel outreach', 'description' => 'Meals, supplies, mentorship', 'url' => '#give-form', 'sort_order' => 10],
            ['item_key' => 'quick_choice', 'label' => 'Serve', 'title' => 'Join the team', 'description' => 'Hands + hearts welcome', 'url' => route('volunteer.apply', ['need' => 'general'], false), 'sort_order' => 20],
            ['item_key' => 'quick_choice', 'label' => 'Learn', 'title' => 'How it works', 'description' => 'Our 3-phase pathway', 'url' => '#about', 'sort_order' => 30],
        ]);

        $impact = $this->upsertSection($english->id, HomeSectionKey::ImpactStats->value, [
            'heading' => 'Impact at a glance',
            'sort_order' => 20,
            'is_active' => true,
        ]);
        $this->syncItems($impact, [
            ['item_key' => 'stat', 'title' => 'Weekly', 'description' => 'Street outreach + church service', 'sort_order' => 10],
            ['item_key' => 'stat', 'title' => 'Meals', 'description' => 'Food + supplies distributed regularly', 'sort_order' => 20],
            ['item_key' => 'stat', 'title' => 'Mentorship', 'description' => 'Discipleship + life coaching', 'sort_order' => 30],
        ]);

        $about = $this->upsertSection($english->id, HomeSectionKey::About->value, [
            'heading' => 'A simple path to restoration.',
            'body' => 'We believe transformation is spiritual and practical. So we combine consistent discipleship with tangible steps that rebuild stability and dignity.',
            'note' => 'What you\'ll see in our outreach',
            'sort_order' => 30,
            'is_active' => true,
        ]);
        $this->syncItems($about, [
            ['item_key' => 'bullet', 'title' => 'Bible teaching + prayer', 'sort_order' => 10],
            ['item_key' => 'bullet', 'title' => 'Hot meals + supplies', 'sort_order' => 20],
            ['item_key' => 'bullet', 'title' => 'Mentorship + coaching', 'sort_order' => 30],
            ['item_key' => 'bullet', 'title' => 'Job/housing direction', 'sort_order' => 40],
        ]);

        $pathway = $this->upsertSection($english->id, HomeSectionKey::Pathway->value, [
            'heading' => '3 phases to rehabilitation',
            'subheading' => 'Built for real life: spiritual foundation + next practical step.',
            'sort_order' => 40,
            'is_active' => true,
        ]);
        $this->syncItems($pathway, [
            ['item_key' => 'phase', 'label' => '01', 'title' => 'Rehabilitation + community housing', 'description' => 'Counseling, mentorship, discipleship, and stabilization.', 'sort_order' => 10],
            ['item_key' => 'phase', 'label' => '02', 'title' => 'Education + job training', 'description' => 'Skills, readiness, and ongoing Christ-centered coaching.', 'sort_order' => 20],
            ['item_key' => 'phase', 'label' => '03', 'title' => 'Permanent housing + career placement', 'description' => 'Long-term stability with continued community support.', 'sort_order' => 30],
        ]);

        $this->upsertSection($english->id, HomeSectionKey::Parallax->value, [
            'eyebrow' => 'Mentoring • Coaching • Discipleship',
            'heading' => 'Nobody rebuilds alone.',
            'body' => 'We walk alongside people with consistent spiritual guidance, practical life coaching, and Christ-centered community - helping restore identity, purpose, and momentum.',
            'image_id' => $parallaxImage?->id,
            'sort_order' => 50,
            'is_active' => true,
        ]);

        $serve = $this->upsertSection($english->id, HomeSectionKey::Serve->value, [
            'eyebrow' => 'Serve • Outreach Team',
            'heading' => 'Serve with Bread of Grace.',
            'body' => 'Some people give. Some people show up. Some do both. There\'s a place for you - prayer, food service, conversations, discipleship, logistics.',
            'cta_primary_label' => 'Sign up to Volunteer',
            'cta_primary_url' => route('volunteer.apply', ['need' => 'general'], false),
            'cta_secondary_label' => 'Support the work',
            'cta_secondary_url' => '#give-form',
            'cta_tertiary_label' => 'Come this Thursday/Sunday',
            'cta_tertiary_url' => '#visit',
            'image_id' => $serveImage?->id,
            'sort_order' => 60,
            'is_active' => true,
            'meta' => ['location' => 'Sacramento'],
        ]);

        $serveSupport = $this->upsertSection($english->id, HomeSectionKey::ServeSupport->value, [
            'heading' => 'A few "easy yes" ways to help',
            'body' => 'People remember warmth and consistency more than speeches. Just showing up matters.',
            'sort_order' => 70,
            'is_active' => true,
        ]);
        $this->syncItems($serveSupport, [
            ['item_key' => 'easy_yes', 'title' => 'Bring water / hygiene kits / socks', 'sort_order' => 10],
            ['item_key' => 'easy_yes', 'title' => 'Help serve meals + cleanup', 'sort_order' => 20],
            ['item_key' => 'easy_yes', 'title' => 'Prayer + conversation + encouragement', 'sort_order' => 30],
            ['item_key' => 'easy_yes', 'title' => 'Mentorship / discipleship follow-up', 'sort_order' => 40],
        ]);

        $this->upsertSection($english->id, HomeSectionKey::PreGiveCta->value, [
            'eyebrow' => 'Next step',
            'heading' => 'Ready to make a real difference today?',
            'body' => 'Your gift helps meals, supplies, and consistent discipleship happen every week.',
            'cta_primary_label' => 'Jump to donation form →',
            'cta_primary_url' => '#give-form',
            'sort_order' => 75,
            'is_active' => true,
        ]);

        $give = $this->upsertSection($english->id, HomeSectionKey::Give->value, [
            'eyebrow' => 'Give • One-time or Monthly',
            'heading' => 'Make outreach possible this week.',
            'body' => 'Your gift helps feed the hungry and help those in need through meals, survival supplies, discipleship, and practical next steps toward stability. Monthly giving helps us plan with confidence.',
            'note' => 'Our heart',
            'subheading' => '“For I was hungry and you gave Me food; I was thirsty and you gave Me drink; I was a stranger and you took Me in...”',
            'cta_primary_label' => 'Give now',
            'image_id' => $giveImage?->id,
            'sort_order' => 80,
            'is_active' => true,
            'meta' => ['scripture_reference' => 'Matthew 25:35'],
        ]);
        $this->syncItems($give, [
            ['item_key' => 'impact_card', 'title' => 'Meals', 'description' => 'Hot food served with dignity', 'sort_order' => 10],
            ['item_key' => 'impact_card', 'title' => 'Supplies', 'description' => 'Hygiene, clothing, essentials', 'sort_order' => 20],
            ['item_key' => 'impact_card', 'title' => 'Mentorship', 'description' => 'Discipleship + coaching', 'sort_order' => 30],
        ]);

        $this->upsertSection($english->id, HomeSectionKey::Visit->value, [
            'heading' => 'Visit us',
            'body' => 'Township 9 Park • Sacramento',
            'cta_primary_label' => 'Get Directions',
            'cta_primary_url' => 'https://goo.gl/maps/uD7kDihreYD3nXjcA',
            'sort_order' => 90,
            'is_active' => true,
            'meta' => [
                'meeting_schedule' => 'Thursday + Sunday • 11:00am',
                'meeting_location' => 'Township 9 Park • Sacramento',
                'map_embed_url' => 'https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3118.0671267815537!2d-121.49328698440546!3d38.60132517194608!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x809ad710c885a0bd%3A0x82f6d5b85e40b630!2sBread%20of%20Grace%20Ministries!5e0!3m2!1sen!2sus!4v1668623874529!5m2!1sen!2sus',
            ],
        ]);

        $this->upsertSection($english->id, HomeSectionKey::FinalCta->value, [
            'eyebrow' => 'Next step',
            'heading' => 'Ready to make a real difference today?',
            'body' => 'Your gift helps meals, supplies, and consistent discipleship happen every week.',
            'cta_primary_label' => 'Jump to donation form →',
            'cta_primary_url' => '#give-form',
            'sort_order' => 100,
            'is_active' => true,
        ]);
    }

    private function upsertSection(int $languageId, string $sectionKey, array $data): HomeSection
    {
        return HomeSection::query()->updateOrCreate(
            [
                'language_id' => $languageId,
                'section_key' => $sectionKey,
            ],
            $data
        );
    }

    private function syncItems(HomeSection $section, array $items): void
    {
        $section->items()->delete();

        foreach ($items as $item) {
            $section->items()->create($item + ['is_active' => true]);
        }
    }
}

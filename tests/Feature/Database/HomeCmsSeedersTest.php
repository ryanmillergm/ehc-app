<?php

namespace Tests\Feature\Database;

use App\Models\FaqItem;
use App\Models\HomePageContent;
use App\Models\HomeSection;
use App\Models\Image;
use App\Models\Language;
use App\Models\SiteMediaDefault;
use Database\Seeders\FaqItemSeeder;
use Database\Seeders\HomePageContentSeeder;
use Database\Seeders\HomeSectionSeeder;
use Database\Seeders\ImageSeeder;
use Database\Seeders\SiteMediaDefaultSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class HomeCmsSeedersTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function home_seeders_create_expected_english_home_content_row(): void
    {
        $this->seed([
            ImageSeeder::class,
            HomePageContentSeeder::class,
            HomeSectionSeeder::class,
        ]);

        $english = Language::query()->where('iso_code', 'en')->firstOrFail();
        $content = HomePageContent::query()->where('language_id', $english->id)->firstOrFail();

        $this->assertSame('Homeless Ministry in Sacramento, CA | Bread of Grace Ministries', $content->seo_title);
        $this->assertSame(
            'As a homeless ministry in Sacramento, California, we feed the hungry, help the needy, and walk with people through Christ-centered mentorship, practical support, and pathways to stable housing.',
            $content->hero_intro
        );
        $this->assertSame('Thursday + Sunday • 11:00am', $content->meeting_schedule);
        $this->assertSame('Township 9 Park • Sacramento', $content->meeting_location);
        $this->assertNotNull($content->hero_image_id);
        $this->assertNotNull($content->featured_image_id);
        $this->assertNotNull($content->og_image_id);

        $heroSection = HomeSection::query()
            ->where('language_id', $english->id)
            ->where('section_key', 'hero')
            ->firstOrFail();

        $this->assertSame('Bread of Grace Ministries', $heroSection->eyebrow);
        $this->assertSame('Help restore lives through God\'s Word and practical support.', $heroSection->heading);
        $this->assertNotEmpty($heroSection->items);

        $preGiveCta = HomeSection::query()
            ->where('language_id', $english->id)
            ->where('section_key', 'pre_give_cta')
            ->firstOrFail();

        $this->assertSame('Next step', $preGiveCta->eyebrow);
        $this->assertSame('Ready to make a real difference today?', $preGiveCta->heading);
        $this->assertSame('Jump to donation form →', $preGiveCta->cta_primary_label);
        $this->assertSame('#give-form', $preGiveCta->cta_primary_url);

        $finalCta = HomeSection::query()
            ->where('language_id', $english->id)
            ->where('section_key', 'final_cta')
            ->firstOrFail();

        $this->assertSame('Next step', $finalCta->eyebrow);
        $this->assertSame('Ready to make a real difference today?', $finalCta->heading);
        $this->assertSame('Jump to donation form →', $finalCta->cta_primary_label);
        $this->assertSame('#give-form', $finalCta->cta_primary_url);
    }

    #[Test]
    public function faq_seeder_creates_expected_home_questions_in_order(): void
    {
        $this->seed([FaqItemSeeder::class]);

        $questions = FaqItem::query()
            ->where('context', 'home')
            ->orderBy('sort_order')
            ->pluck('question')
            ->all();

        $this->assertSame([
            'How are donations used?',
            'Where does outreach happen?',
            'Can I volunteer if I am new?',
            'Can I give monthly to support long-term impact?',
        ], $questions);
    }

    #[Test]
    public function image_and_site_default_seeders_create_resolver_compatible_rows(): void
    {
        $this->seed([
            ImageSeeder::class,
            SiteMediaDefaultSeeder::class,
        ]);

        $this->assertGreaterThanOrEqual(6, Image::query()->count());
        $this->assertTrue(Image::query()->where('path', 'cms/legacy/sm/the-mayor.jpg')->exists());
        $this->assertTrue(Image::query()->where('path', 'cms/legacy/sm/the-mayor.jpg')->whereNotNull('title')->exists());
        $this->assertTrue(Image::query()->where('path', 'cms/legacy/sm/bible-scriptures.jpg')->where('is_decorative', true)->exists());

        $defaults = SiteMediaDefault::query()->pluck('role')->all();
        sort($defaults);

        $this->assertSame(['featured', 'header', 'og', 'thumbnail'], $defaults);
    }

    #[Test]
    public function seeders_are_idempotent_when_run_twice(): void
    {
        $this->seed([
            ImageSeeder::class,
            SiteMediaDefaultSeeder::class,
            HomePageContentSeeder::class,
            HomeSectionSeeder::class,
            FaqItemSeeder::class,
        ]);

        $countsAfterFirst = [
            'images' => Image::query()->count(),
            'defaults' => SiteMediaDefault::query()->count(),
            'home' => HomePageContent::query()->count(),
            'home_sections' => HomeSection::query()->count(),
            'faq' => FaqItem::query()->where('context', 'home')->count(),
        ];

        $this->seed([
            ImageSeeder::class,
            SiteMediaDefaultSeeder::class,
            HomePageContentSeeder::class,
            HomeSectionSeeder::class,
            FaqItemSeeder::class,
        ]);

        $countsAfterSecond = [
            'images' => Image::query()->count(),
            'defaults' => SiteMediaDefault::query()->count(),
            'home' => HomePageContent::query()->count(),
            'home_sections' => HomeSection::query()->count(),
            'faq' => FaqItem::query()->where('context', 'home')->count(),
        ];

        $this->assertSame($countsAfterFirst, $countsAfterSecond);
    }
}

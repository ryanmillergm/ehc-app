<?php

namespace Tests\Unit\Media;

use App\Enums\Media\ImageAttachableType;
use App\Models\HomePageContent;
use App\Models\Language;
use App\Models\PageTranslation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ImageAttachableTypeTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_exposes_expected_model_class_options(): void
    {
        $this->assertSame([
            PageTranslation::class => 'Page Translation',
            HomePageContent::class => 'Home Page Content',
        ], ImageAttachableType::options());
    }

    #[Test]
    public function it_returns_label_for_known_model_class_and_raw_value_for_unknown(): void
    {
        $this->assertSame('Page Translation', ImageAttachableType::labelFor(PageTranslation::class));
        $this->assertSame('Home Page Content', ImageAttachableType::labelFor(HomePageContent::class));
        $this->assertSame('App\\Models\\UnknownModel', ImageAttachableType::labelFor('App\\Models\\UnknownModel'));
    }

    #[Test]
    public function it_checks_target_existence_by_attachable_type(): void
    {
        $pageTranslation = PageTranslation::factory()->create();
        $homeContent = HomePageContent::factory()->create();
        $missingPageTranslationId = PageTranslation::query()->max('id') + 1000;
        $missingHomeContentId = HomePageContent::query()->max('id') + 1000;

        $this->assertTrue(ImageAttachableType::targetExists(PageTranslation::class, $pageTranslation->id));
        $this->assertFalse(ImageAttachableType::targetExists(PageTranslation::class, $missingPageTranslationId));
        $this->assertTrue(ImageAttachableType::targetExists(HomePageContent::class, $homeContent->id));
        $this->assertFalse(ImageAttachableType::targetExists(HomePageContent::class, $missingHomeContentId));
        $this->assertFalse(ImageAttachableType::targetExists(HomePageContent::class, -1));
        $this->assertFalse(ImageAttachableType::targetExists('App\\Models\\UnknownModel', 1));
    }

    #[Test]
    public function it_formats_related_record_options_for_each_attachable_type(): void
    {
        $translation = PageTranslation::factory()->create([
            'title' => 'About Us',
            'slug' => 'about-us',
        ]);

        $english = Language::factory()->english()->create();
        $home = HomePageContent::factory()->create([
            'language_id' => $english->id,
            'seo_title' => 'Home SEO Title',
        ]);

        $translationOptions = ImageAttachableType::relatedRecordOptions(PageTranslation::class);
        $homeOptions = ImageAttachableType::relatedRecordOptions(HomePageContent::class);

        $this->assertArrayHasKey($translation->id, $translationOptions);
        $this->assertStringContainsString('#' . $translation->id, $translationOptions[$translation->id]);
        $this->assertStringContainsString('About Us', $translationOptions[$translation->id]);
        $this->assertStringContainsString('(about-us)', $translationOptions[$translation->id]);

        $this->assertArrayHasKey($home->id, $homeOptions);
        $this->assertStringContainsString('#' . $home->id, $homeOptions[$home->id]);
        $this->assertStringContainsString('English', $homeOptions[$home->id]);
        $this->assertStringContainsString('Home SEO Title', $homeOptions[$home->id]);
    }
}

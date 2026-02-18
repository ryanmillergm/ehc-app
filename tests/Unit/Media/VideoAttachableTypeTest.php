<?php

namespace Tests\Unit\Media;

use App\Enums\Media\VideoAttachableType;
use App\Models\HomePageContent;
use App\Models\Language;
use App\Models\PageTranslation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class VideoAttachableTypeTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_exposes_expected_model_class_options(): void
    {
        $this->assertSame([
            PageTranslation::class => 'Page Translation',
            HomePageContent::class => 'Home Page Content',
        ], VideoAttachableType::options());
    }

    #[Test]
    public function it_checks_target_existence_by_attachable_type(): void
    {
        $pageTranslation = PageTranslation::factory()->create();
        $homeContent = HomePageContent::factory()->create();
        $missingPageTranslationId = PageTranslation::query()->max('id') + 1000;
        $missingHomeContentId = HomePageContent::query()->max('id') + 1000;

        $this->assertTrue(VideoAttachableType::targetExists(PageTranslation::class, $pageTranslation->id));
        $this->assertFalse(VideoAttachableType::targetExists(PageTranslation::class, $missingPageTranslationId));
        $this->assertTrue(VideoAttachableType::targetExists(HomePageContent::class, $homeContent->id));
        $this->assertFalse(VideoAttachableType::targetExists(HomePageContent::class, $missingHomeContentId));
        $this->assertFalse(VideoAttachableType::targetExists(HomePageContent::class, -1));
        $this->assertFalse(VideoAttachableType::targetExists('App\\Models\\UnknownModel', 1));
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

        $translationOptions = VideoAttachableType::relatedRecordOptions(PageTranslation::class);
        $homeOptions = VideoAttachableType::relatedRecordOptions(HomePageContent::class);

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

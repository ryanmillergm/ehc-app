<?php

namespace Tests\Feature\Seo;

use App\Models\HomePageContent;
use App\Models\Language;
use App\Models\Page;
use App\Models\PageTranslation;
use App\Models\SeoMeta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SeoMetaSyncTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function page_translation_seo_fields_sync_to_seo_meta_row(): void
    {
        $language = Language::factory()->english()->create();
        $page = Page::factory()->create(['is_active' => true]);

        $translation = PageTranslation::factory()->create([
            'page_id' => $page->id,
            'language_id' => $language->id,
            'title' => 'Page Title',
            'description' => 'Page Description',
            'is_active' => true,
        ]);

        $translation->seo_title = 'SEO Title';
        $translation->seo_description = 'SEO Description';
        $translation->seo_og_image = 'https://cdn.example.org/page-og.jpg';
        $translation->save();

        $this->assertDatabaseHas('seo_meta', [
            'seoable_type' => $translation->getMorphClass(),
            'seoable_id' => $translation->id,
            'target_key' => '',
            'language_id' => $language->id,
            'seo_title' => 'SEO Title',
            'seo_description' => 'SEO Description',
            'seo_og_image' => 'https://cdn.example.org/page-og.jpg',
            'is_active' => true,
        ]);
    }

    #[Test]
    public function page_translation_seo_row_is_removed_when_fields_are_cleared(): void
    {
        $language = Language::factory()->english()->create();
        $page = Page::factory()->create(['is_active' => true]);

        $translation = PageTranslation::factory()->create([
            'page_id' => $page->id,
            'language_id' => $language->id,
            'title' => 'Page Title',
            'description' => 'Page Description',
            'is_active' => true,
        ]);

        SeoMeta::query()->create([
            'seoable_type' => $translation->getMorphClass(),
            'seoable_id' => $translation->id,
            'target_key' => '',
            'language_id' => $language->id,
            'seo_title' => 'SEO Title',
            'seo_description' => 'SEO Description',
            'seo_og_image' => 'https://cdn.example.org/page-og.jpg',
            'is_active' => true,
        ]);

        $translation->seo_title = null;
        $translation->seo_description = null;
        $translation->seo_og_image = null;
        $translation->save();

        $this->assertDatabaseMissing('seo_meta', [
            'seoable_type' => $translation->getMorphClass(),
            'seoable_id' => $translation->id,
            'target_key' => '',
            'language_id' => $language->id,
        ]);
    }

    #[Test]
    public function home_page_content_seo_fields_sync_to_seo_meta_row(): void
    {
        $language = Language::factory()->english()->create();

        $home = HomePageContent::factory()->create([
            'language_id' => $language->id,
        ]);

        $home->seo_title = 'Home SEO Title';
        $home->seo_description = 'Home SEO Description';
        $home->seo_og_image = 'https://cdn.example.org/home-og.jpg';
        $home->save();

        $this->assertDatabaseHas('seo_meta', [
            'seoable_type' => $home->getMorphClass(),
            'seoable_id' => $home->id,
            'target_key' => '',
            'language_id' => $language->id,
            'seo_title' => 'Home SEO Title',
            'seo_description' => 'Home SEO Description',
            'seo_og_image' => 'https://cdn.example.org/home-og.jpg',
            'is_active' => true,
        ]);
    }
}

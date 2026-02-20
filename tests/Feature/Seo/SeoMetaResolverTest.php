<?php

namespace Tests\Feature\Seo;

use App\Models\Language;
use App\Models\Page;
use App\Models\PageTranslation;
use App\Models\SeoMeta;
use App\Services\Seo\SeoMetaResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SeoMetaResolverTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_prefers_current_language_row_for_model_targets(): void
    {
        $en = Language::factory()->english()->create();
        $es = Language::factory()->spanish()->create();
        $page = Page::factory()->create(['is_active' => true]);
        $translation = PageTranslation::factory()->create([
            'page_id' => $page->id,
            'language_id' => $en->id,
            'title' => 'Base Title',
            'description' => 'Base Description',
            'is_active' => true,
        ]);

        SeoMeta::query()->create([
            'seoable_type' => $translation->getMorphClass(),
            'seoable_id' => $translation->id,
            'target_key' => '',
            'language_id' => $en->id,
            'seo_title' => 'English SEO',
            'seo_description' => 'English Description',
            'is_active' => true,
        ]);

        SeoMeta::query()->create([
            'seoable_type' => $translation->getMorphClass(),
            'seoable_id' => $translation->id,
            'target_key' => '',
            'language_id' => $es->id,
            'seo_title' => 'Spanish SEO',
            'seo_description' => 'Spanish Description',
            'is_active' => true,
        ]);

        session(['language_id' => $es->id, 'locale' => 'es']);
        app()->setLocale('es');

        $resolved = app(SeoMetaResolver::class)->forModel($translation, null, [
            'title' => $translation->title,
            'description' => $translation->description,
        ]);

        $this->assertSame('Spanish SEO', $resolved['metaTitle']);
        $this->assertSame('Spanish Description', $resolved['metaDescription']);
    }

    #[Test]
    public function it_falls_back_to_model_values_when_no_seo_row_exists(): void
    {
        $language = Language::factory()->english()->create();
        $page = Page::factory()->create(['is_active' => true]);
        $translation = PageTranslation::factory()->create([
            'page_id' => $page->id,
            'language_id' => $language->id,
            'title' => 'Fallback Title',
            'description' => 'Fallback Description',
            'is_active' => true,
        ]);

        $resolved = app(SeoMetaResolver::class)->forModel($translation, $language->id, [
            'title' => $translation->title,
            'description' => $translation->description,
        ]);

        $this->assertSame('Fallback Title', $resolved['metaTitle']);
        $this->assertSame('Fallback Description', $resolved['metaDescription']);
    }

    #[Test]
    public function it_skips_inactive_current_language_row_and_falls_back_to_default_language(): void
    {
        $en = Language::factory()->english()->create();
        $es = Language::factory()->spanish()->create();
        $page = Page::factory()->create(['is_active' => true]);
        $translation = PageTranslation::factory()->create([
            'page_id' => $page->id,
            'language_id' => $en->id,
            'title' => 'Fallback Title',
            'description' => 'Fallback Description',
            'is_active' => true,
        ]);

        SeoMeta::query()->create([
            'seoable_type' => $translation->getMorphClass(),
            'seoable_id' => $translation->id,
            'target_key' => '',
            'language_id' => $es->id,
            'seo_title' => 'Inactive ES Title',
            'seo_description' => 'Inactive ES Description',
            'is_active' => false,
        ]);

        SeoMeta::query()->create([
            'seoable_type' => $translation->getMorphClass(),
            'seoable_id' => $translation->id,
            'target_key' => '',
            'language_id' => $en->id,
            'seo_title' => 'Default EN Title',
            'seo_description' => 'Default EN Description',
            'is_active' => true,
        ]);

        session(['language_id' => $es->id, 'locale' => 'es']);
        app()->setLocale('es');

        $resolved = app(SeoMetaResolver::class)->forModel($translation, null, [
            'title' => $translation->title,
            'description' => $translation->description,
        ]);

        $this->assertSame('Default EN Title', $resolved['metaTitle']);
        $this->assertSame('Default EN Description', $resolved['metaDescription']);
    }

    #[Test]
    public function it_uses_global_defaults_when_no_row_and_no_model_fallback_values(): void
    {
        $language = Language::factory()->english()->create();
        $page = Page::factory()->create(['is_active' => true]);
        $translation = PageTranslation::factory()->create([
            'page_id' => $page->id,
            'language_id' => $language->id,
            'title' => 'Unused Title',
            'description' => 'Unused Description',
            'is_active' => true,
        ]);

        config()->set('seo.default_title', 'Global Default Title');
        config()->set('seo.default_description', 'Global Default Description');

        $resolved = app(SeoMetaResolver::class)->forModel($translation, $language->id, []);

        $this->assertSame('Global Default Title', $resolved['metaTitle']);
        $this->assertSame('Global Default Description', $resolved['metaDescription']);
    }
}

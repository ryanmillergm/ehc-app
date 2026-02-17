<?php

namespace Tests\Feature\Seo;

use App\Models\Language;
use App\Models\Page;
use App\Models\PageTranslation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SeoInfrastructureTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function home_page_renders_core_seo_meta_tags_and_cta_anchor(): void
    {
        $response = $this->get('/');
        $content = $response->getContent();

        $response->assertOk()
            ->assertSee('<meta name="description"', false)
            ->assertSee('<meta property="og:title"', false)
            ->assertSee('<meta name="twitter:card"', false)
            ->assertSee('<link rel="canonical"', false)
            ->assertSee('type="application/ld+json"', false)
            ->assertSee('id="give-form"', false)
            ->assertSee('Frequently asked questions');

        $this->assertSame(1, substr_count((string) $content, '<link rel="canonical"'));
    }

    #[Test]
    public function sitemap_xml_lists_core_urls_and_only_active_page_translations(): void
    {
        $language = Language::factory()->english()->create();

        $activePage = Page::factory()->create(['is_active' => true]);
        $inactivePage = Page::factory()->create(['is_active' => false]);

        $active = PageTranslation::factory()
            ->forLanguage($language)
            ->forPage($activePage)
            ->state(['slug' => 'help-the-homeless-sacramento', 'is_active' => true])
            ->create();

        $inactiveTranslation = PageTranslation::factory()
            ->forLanguage($language)
            ->forPage($activePage)
            ->state(['slug' => 'inactive-translation', 'is_active' => false])
            ->create();

        $inactivePageTranslation = PageTranslation::factory()
            ->forLanguage($language)
            ->forPage($inactivePage)
            ->state(['slug' => 'inactive-page-translation', 'is_active' => true])
            ->create();

        $response = $this->get('/sitemap.xml');
        $xml = $response->getContent();

        $response->assertOk()
            ->assertHeader('Content-Type', 'application/xml')
            ->assertSee('<?xml version="1.0" encoding="UTF-8"?>', false)
            ->assertSee('<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">', false)
            ->assertSee(url('/'), false)
            ->assertSee(url('/give'), false)
            ->assertSee(url('/emails/subscribe'), false)
            ->assertSee(url('/pages'), false)
            ->assertSee(url('/pages/' . $active->slug), false);

        $this->assertStringNotContainsString(url('/pages/' . $inactiveTranslation->slug), (string) $xml);
        $this->assertStringNotContainsString(url('/pages/' . $inactivePageTranslation->slug), (string) $xml);
    }

    #[Test]
    public function robots_txt_exposes_sitemap_path(): void
    {
        $response = $this->get('/robots.txt');

        $response->assertOk()
            ->assertSee('User-agent: *')
            ->assertSee('Disallow:')
            ->assertSee('Sitemap: ' . rtrim(config('app.url'), '/') . '/sitemap.xml');
    }
}

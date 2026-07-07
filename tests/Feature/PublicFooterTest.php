<?php

namespace Tests\Feature;

use App\Models\Language;
use App\Models\Page;
use App\Models\PageTranslation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicFooterTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_layout_renders_footer_once_on_home_page(): void
    {
        $content = $this->get('/')
            ->assertOk()
            ->assertSee('Christ-centered street outreach')
            ->assertSee('Stay connected')
            ->getContent();

        $this->assertSame(1, substr_count((string) $content, '<footer'));
    }

    public function test_public_layout_renders_footer_once_on_pages_index(): void
    {
        $this->createPublishedPage('pages-index-footer-page');

        $content = $this->get('/pages')
            ->assertOk()
            ->assertSee('Christ-centered street outreach')
            ->assertSee('Stay connected')
            ->getContent();

        $this->assertSame(1, substr_count((string) $content, '<footer'));
    }

    public function test_public_layout_renders_footer_once_on_dynamic_page(): void
    {
        $translation = $this->createPublishedPage('dynamic-footer-page');

        $content = $this->get('/pages/' . $translation->slug)
            ->assertOk()
            ->assertSee('Christ-centered street outreach')
            ->assertSee('Stay connected')
            ->getContent();

        $this->assertSame(1, substr_count((string) $content, '<footer'));
    }

    public function test_public_layout_renders_footer_once_on_give_page(): void
    {
        $content = $this->get('/give')
            ->assertOk()
            ->assertSee('Christ-centered street outreach')
            ->assertSee('Stay connected')
            ->getContent();

        $this->assertSame(1, substr_count((string) $content, '<footer'));
    }

    private function createPublishedPage(string $slug): PageTranslation
    {
        $language = Language::factory()->english()->create();
        $page = Page::factory()->create(['is_active' => true]);

        return PageTranslation::factory()
            ->forLanguage($language)
            ->forPage($page)
            ->state([
                'slug' => $slug,
                'title' => 'Footer Test Page',
                'description' => 'Footer test description',
                'content' => '<p>Footer test content</p>',
                'is_active' => true,
            ])
            ->create();
    }
}

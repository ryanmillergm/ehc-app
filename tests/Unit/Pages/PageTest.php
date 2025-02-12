<?php

namespace Tests\Unit\Pages;

use App\Models\Language;
use App\Models\Page;
use App\Models\PageTranslation;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;

class PageTest extends TestCase
{
    use WithFaker, RefreshDatabase;

    /**
     * Test a page can add a translation
     */
    public function test_a_page_can_add_a_translation(): void
    {
        // $this->assertTrue(true);
        $page = Page::factory()->create();

        $language = Language::factory()->create();

        $attributes = [
            'language_id'   => $language->id,
            'title'         => 'Blog Test Title Example',
            'slug'          => 'blog-test-example',
            'description'   => 'Blog Test Description Example',
            'content'       => 'Blog Test Content Example',
            'is_active'     => true,
        ];

        $page->addTranslation($attributes);

        $this->assertCount(1, $page->pageTranslations);
    }

    /**
     * Test a Page has many PageTranslations relationship
     */
    public function test_a_page_has_many_page_translations()
    {
        $page = Page::factory()->create();

        $language = Language::factory()->create();

        $translation = PageTranslation::factory()->create(['language_id' => $language->id, 'page_id' => $page->id]);

        // Method 1: A Page Translation exists in a Languages pageTranslation collections.
        $this->assertTrue($page->pageTranslations->contains($translation));

        // Method 2: Count that a post comments collection exists.
        $this->assertEquals(1, $page->pageTranslations->count());

        // Method 3: PageTranslations are related to Pages and is a collection instance.
        $this->assertInstanceOf('Illuminate\Database\Eloquent\Collection', $page->pageTranslations);
    }
}

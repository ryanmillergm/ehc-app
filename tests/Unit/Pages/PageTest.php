<?php

namespace Tests\Unit\Pages;

use App\Models\Language;
use App\Models\Page;
use App\Models\PageTranslation;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\DB;

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
     * Test a Page can have a  PageTranslation relationship
     */
    public function test_a_page_can_have_a_page_translation()
    {
        $page = Page::factory()->create();
        $page2 = Page::factory()->create();
        $page3 = Page::factory()->create();

        $language = Language::factory()->create();
        $language2 = Language::factory()->create();
        $language3 = Language::factory()->create();

        $translation = PageTranslation::factory()->create(['language_id' => $language->id, 'page_id' => $page->id]);
        $translation2 = PageTranslation::factory()->create(['language_id' => $language2->id, 'page_id' => $page2->id]);
        $translation3 = PageTranslation::factory()->create(['language_id' => $language3->id, 'page_id' => $page3->id]);

        // Method 1: A Page Translation exists in a Languages pageTranslation collections.
        $this->assertTrue($page->pageTranslations->contains($translation));

        // Method 2: Count that a Page pageTranslations collection exists.
        $this->assertEquals(1, $page->pageTranslations->count());

        // Method 3: PageTranslations are related to Pages and is a collection instance.
        $this->assertInstanceOf('Illuminate\Database\Eloquent\Collection', $page->pageTranslations);
    }


    /**
     * Test a Page has many PageTranslations relationship
     */
    public function test_a_page_has_many_page_translations()
    {
        $page = Page::factory()->create();
        $page2 = Page::factory()->create();
        $page3 = Page::factory()->create();

        $language = Language::factory()->create();
        $language2 = Language::factory()->create();
        $language3 = Language::factory()->create();

        $translation = PageTranslation::factory()->create(['language_id' => $language->id, 'page_id' => $page->id]);
        $translation2 = PageTranslation::factory()->create(['language_id' => $language2->id, 'page_id' => $page->id]);
        $translation3 = PageTranslation::factory()->create(['language_id' => $language3->id, 'page_id' => $page->id]);

        // Method 1: A Page Translation exists in a Languages pageTranslation collections.
        $this->assertTrue($page->pageTranslations->contains($translation));

        // Method 2: Count that a Page pageTranslations collection exists.
        $this->assertEquals(3, $page->pageTranslations->count());

        // Method 3: PageTranslations are related to Pages and is a collection instance.
        $this->assertInstanceOf('Illuminate\Database\Eloquent\Collection', $page->pageTranslations);
    }
}

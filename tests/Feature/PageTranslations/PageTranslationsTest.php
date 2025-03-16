<?php

namespace Tests\Feature\PageTranslations;

use App\Models\Language;
use App\Models\Page;
use App\Models\PageTranslation;
use App\Models\Permission;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PageTranslationsTest extends TestCase
{
    use WithFaker, RefreshDatabase;

    /**
     * Test PageTranslations database has correct columns
     */
    public function test_page_tranlations_database_has_expected_columns()
    {
        $this->withoutExceptionHandling();

        $this->assertTrue(
          Schema::hasColumns('page_translations', [
            'id', 'page_id', 'language_id', 'title', 'slug', 'description', 'content', 'is_active'
        ]), 1);
    }


    /**
     * A Page Translation belongs to a Language and a page
     */
    public function test_a_page_translation_belongs_to_a_language_and_a_page()
    {
        $page = Page::factory()->create();
        $page2 = Page::factory()->create();
        $page3 = Page::factory()->create();

        $language = Language::factory()->create();
        $language2 = Language::factory()->create();
        $language3 = Language::factory()->create();

        $translation = PageTranslation::factory()->create(['language_id' => $language->id, 'page_id' => $page->id]);

        // Method 1: Test by count that a comment has a parent relationship with post
        $this->assertEquals(1, $translation->language()->count());
        $this->assertEquals(1, $translation->page()->count());

        // Method 2:
        $this->assertInstanceOf(Language::class, $translation->language);
        $this->assertInstanceOf(Page::class, $translation->page);
    }
}

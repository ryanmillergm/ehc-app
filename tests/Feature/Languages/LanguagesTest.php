<?php

namespace Tests\Feature\Languages;

use App\Models\Language;
use App\Models\Page;
use App\Models\PageTranslation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LanguagesTest extends TestCase
{
    use WithFaker, RefreshDatabase;

    /**
     * Test langugages database has correct columns
     */
    public function test_langugages_database_has_expected_columns()
    {
        $this->assertTrue(
          Schema::hasColumns('languages', [
            'title', 'iso_code', 'locale', 'right_to_left', 'created_at'
        ]), 1);
    }

    /**
     * Test a langugage has many PageTranslations relationship
     */
    public function test_a_language_has_many_page_translations()
    {
        $page = Page::factory()->create();
        $language = Language::factory()->create();

        $translation = PageTranslation::factory()->create(['language_id' => $language->id, 'page_id' => $page->id]);

        // Method 1: A Page Translation exists in a Languages pageTranslation collections.
        $this->assertTrue($language->pageTranslations->contains($translation));

        // Method 2: Count that a post comments collection exists.
        $this->assertEquals(1, $language->pageTranslations->count());

        // Method 3: PageTranslations are related to Languages and is a collection instance.
        $this->assertInstanceOf('Illuminate\Database\Eloquent\Collection', $language->pageTranslations);
    }
}

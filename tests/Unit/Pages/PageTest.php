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
        $page = Page::factory()->create();
        $language = Language::factory()->create();

        $attributes = [
            'language_id' => $language->id,
            'title'       => 'Blog Test Title Example',
            'slug'        => 'blog-test-example',
            'description' => 'Blog Test Description Example',
            'content'     => 'Blog Test Content Example',
            'is_active'   => true,
        ];

        $page->addTranslation($attributes);

        $page->refresh(); // ensure relationship reload

        $this->assertCount(1, $page->pageTranslations);
    }

    /**
     * Test a Page can have a PageTranslation relationship
     */
    public function test_a_page_can_have_a_page_translation()
    {
        $page  = Page::factory()->create();
        $page2 = Page::factory()->create();
        $page3 = Page::factory()->create();

        $language  = Language::factory()->create();
        $language2 = Language::factory()->create();
        $language3 = Language::factory()->create();

        $translation  = PageTranslation::factory()->create(['language_id' => $language->id,  'page_id' => $page->id]);
        $translation2 = PageTranslation::factory()->create(['language_id' => $language2->id, 'page_id' => $page2->id]);
        $translation3 = PageTranslation::factory()->create(['language_id' => $language3->id, 'page_id' => $page3->id]);

        $page->refresh();

        $this->assertTrue($page->pageTranslations->contains($translation));
        $this->assertEquals(1, $page->pageTranslations->count());
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Collection::class, $page->pageTranslations);
    }

    /**
     * Test a Page has many PageTranslations relationship
     */
    public function test_a_page_has_many_page_translations()
    {
        $page = Page::factory()->create();

        $language  = Language::factory()->create();
        $language2 = Language::factory()->create();
        $language3 = Language::factory()->create();

        $translation  = PageTranslation::factory()->create(['language_id' => $language->id,  'page_id' => $page->id]);
        $translation2 = PageTranslation::factory()->create(['language_id' => $language2->id, 'page_id' => $page->id]);
        $translation3 = PageTranslation::factory()->create(['language_id' => $language3->id, 'page_id' => $page->id]);

        $page->refresh();

        $this->assertTrue($page->pageTranslations->contains($translation));
        $this->assertEquals(3, $page->pageTranslations->count());
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Collection::class, $page->pageTranslations);
    }

    /**
     * Scope:
     * All active pages with active translations for current session language.
     */
    public function test_active_pages_can_scope_active_translations_by_language()
    {
        // Create languages exactly like seeder
        $english = Language::create([
            'title' => 'English', 'iso_code' => 'en', 'locale' => 'en', 'right_to_left' => false,
        ]);
        $spanish = Language::create([
            'title' => 'Spanish', 'iso_code' => 'es', 'locale' => 'es', 'right_to_left' => false,
        ]);
        $french = Language::create([
            'title' => 'French', 'iso_code' => 'fr', 'locale' => 'fr', 'right_to_left' => false,
        ]);

        // Active pages
        $page1 = Page::factory()->create(['is_active' => true]);
        $page2 = Page::factory()->create(['is_active' => true]);
        $page3 = Page::factory()->create(['is_active' => true]);

        // Inactive page
        $page4 = Page::factory()->create(['is_active' => false]);

        // Active translations
        // page1 has EN + ES + FR active
        PageTranslation::factory()->create([
            'language_id' => $english->id, 'page_id' => $page1->id, 'is_active' => true,
        ]);
        PageTranslation::factory()->create([
            'language_id' => $spanish->id, 'page_id' => $page1->id, 'is_active' => true,
        ]);
        PageTranslation::factory()->create([
            'language_id' => $french->id, 'page_id' => $page1->id, 'is_active' => true,
        ]);

        // page2 has EN only active
        PageTranslation::factory()->create([
            'language_id' => $english->id, 'page_id' => $page2->id, 'is_active' => true,
        ]);

        // page3 has EN inactive
        PageTranslation::factory()->create([
            'language_id' => $english->id, 'page_id' => $page3->id, 'is_active' => false,
        ]);

        // page4 inactive page but translations active
        PageTranslation::factory()->create([
            'language_id' => $english->id, 'page_id' => $page4->id, 'is_active' => true,
        ]);
        PageTranslation::factory()->create([
            'language_id' => $spanish->id, 'page_id' => $page4->id, 'is_active' => true,
        ]);

        // ---- EN session: should return page1 + page2 ----
        session(['language_id' => $english->id]);

        $pagesEn = Page::allActivePagesWithTranslationsByLanguage()->get();

        $this->assertCount(2, $pagesEn);
        $this->assertTrue($pagesEn->pluck('id')->contains($page1->id));
        $this->assertTrue($pagesEn->pluck('id')->contains($page2->id));
        $this->assertFalse($pagesEn->pluck('id')->contains($page3->id));
        $this->assertFalse($pagesEn->pluck('id')->contains($page4->id));

        // ---- ES session: should return ONLY page1 ----
        session(['language_id' => $spanish->id]);

        $pagesEs = Page::allActivePagesWithTranslationsByLanguage()->get();

        $this->assertCount(1, $pagesEs);
        $this->assertTrue($pagesEs->pluck('id')->contains($page1->id));

        // Add ES active to page3 → now ES should return page1 + page3
        PageTranslation::factory()->create([
            'language_id' => $spanish->id, 'page_id' => $page3->id, 'is_active' => true,
        ]);

        $pagesEsAfter = Page::allActivePagesWithTranslationsByLanguage()->get();

        $this->assertCount(2, $pagesEsAfter);
        $this->assertTrue($pagesEsAfter->pluck('id')->contains($page3->id));
    }

    /**
     * NEW Scope:
     * All active pages that have at least one active translation (any language),
     * and eager-load ONLY active translations.
     */
    public function test_active_pages_can_scope_any_active_translation()
    {
        // Languages (not strictly required, but clearer)
        $english = Language::create([
            'title' => 'English', 'iso_code' => 'en', 'locale' => 'en', 'right_to_left' => false,
        ]);
        $spanish = Language::create([
            'title' => 'Spanish', 'iso_code' => 'es', 'locale' => 'es', 'right_to_left' => false,
        ]);

        // Active pages
        $pageWithActiveTx = Page::factory()->create(['is_active' => true]);
        $pageWithInactiveOnlyTx = Page::factory()->create(['is_active' => true]);

        // Inactive page
        $inactivePage = Page::factory()->create(['is_active' => false]);

        // ---- Translations ----

        // pageWithActiveTx has one active EN and one inactive ES
        PageTranslation::factory()->create([
            'page_id' => $pageWithActiveTx->id,
            'language_id' => $english->id,
            'is_active' => true,
        ]);
        PageTranslation::factory()->create([
            'page_id' => $pageWithActiveTx->id,
            'language_id' => $spanish->id,
            'is_active' => false,
        ]);

        // pageWithInactiveOnlyTx has only inactive translations
        PageTranslation::factory()->create([
            'page_id' => $pageWithInactiveOnlyTx->id,
            'language_id' => $english->id,
            'is_active' => false,
        ]);

        // inactivePage has active translations but should be excluded due to page inactive
        PageTranslation::factory()->create([
            'page_id' => $inactivePage->id,
            'language_id' => $english->id,
            'is_active' => true,
        ]);

        $pages = Page::allActivePagesWithAnyActiveTranslation()->get();

        // Only the active page that has at least one active translation should appear
        $this->assertCount(1, $pages);
        $this->assertTrue($pages->pluck('id')->contains($pageWithActiveTx->id));
        $this->assertFalse($pages->pluck('id')->contains($pageWithInactiveOnlyTx->id));
        $this->assertFalse($pages->pluck('id')->contains($inactivePage->id));

        // And the eager-loaded translations are active-only
        $this->assertTrue($pages->first()->relationLoaded('pageTranslations'));
        $this->assertCount(1, $pages->first()->pageTranslations);
        $this->assertTrue($pages->first()->pageTranslations->every(fn ($tx) => $tx->is_active));
    }
}

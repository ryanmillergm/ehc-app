<?php

namespace Tests\Feature\PageTranslations;

use App\Models\Language;
use App\Models\Page;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PageTranslationsTest extends TestCase
{
    use WithFaker, RefreshDatabase;

    public function setUp(): void
    {
        parent::setUp();

        $this->seed();
    }

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
     * A Page translation can be created test
     */
    public function test_a_page_translation_can_be_created_by_super_admin(): void
    {
        $this->withoutExceptionHandling();

        $user = $this->signInAsSuperAdmin();

        $page = Page::factory()->create();
        $language = Language::where('title', 'English')->get()->first();

        $attributes = [
            'page_id'       => $page->id,
            'language_id'   => $language->id,
            'title'         => 'Blog Test Example',
            'slug'          => 'Blog Test Example',
            'description'   => 'Blog Test Example',
            'content'       => 'Blog Test Example',
            'is_active'     => true,
        ];

        $response = $this->post('/pages/' . $page->id . '/translations', $attributes);

        $this->assertDatabaseHas('page_translations', $attributes);
    }
}

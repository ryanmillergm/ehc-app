<?php

namespace Tests\Unit\Pages;

use App\Models\Language;
use App\Models\Page;
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

        $this->assertCount(1, $page->translations);
    }
}

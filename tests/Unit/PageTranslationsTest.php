<?php

namespace Tests\Unit;

use App\Models\Language;
use App\Models\Page;
use App\Models\PageTranslation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PageTranslationsTest extends TestCase
{
    use RefreshDatabase;
    
    
    public function test_a_page_translation_belongs_to_a_page()
    {
        $translation = PageTranslation::factory()->create();

        $this->assertInstanceOf(Page::class, $translation->page);
    }

    public function test_a_page_translation_belongs_to_a_language()
    {
        $translation = PageTranslation::factory()->create();

        $this->assertInstanceOf(Language::class, $translation->language);
    }
}

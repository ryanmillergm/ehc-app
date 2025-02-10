<?php

namespace Tests\Feature\PageTranslations;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Schema;
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
}

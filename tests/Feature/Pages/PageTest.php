<?php

namespace Tests\Feature\Pages;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use Illuminate\Support\Facades\Schema;

class PageTest extends TestCase
{
    use WithFaker, RefreshDatabase;

    /**
     * Test Pages database has correct columns
     */
    public function test_pages_database_has_expected_columns()
    {
        $this->assertTrue(
          Schema::hasColumns('pages', [
            'id', 'title', 'is_active'
        ]), 1);
    }
}

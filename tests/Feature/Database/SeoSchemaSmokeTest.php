<?php

namespace Tests\Feature\Database;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SeoSchemaSmokeTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function seo_meta_table_exists_with_expected_columns_and_legacy_columns_are_removed(): void
    {
        $this->assertTrue(Schema::hasTable('seo_meta'));

        $this->assertTrue(Schema::hasColumns('seo_meta', [
            'id',
            'seoable_type',
            'seoable_id',
            'target_key',
            'language_id',
            'seo_title',
            'seo_description',
            'seo_og_image',
            'canonical_path',
            'robots',
            'is_active',
            'created_at',
            'updated_at',
        ]));

        $this->assertFalse(Schema::hasColumn('page_translations', 'seo_title'));
        $this->assertFalse(Schema::hasColumn('page_translations', 'seo_description'));
        $this->assertFalse(Schema::hasColumn('page_translations', 'seo_og_image'));

        $this->assertFalse(Schema::hasColumn('home_page_contents', 'seo_title'));
        $this->assertFalse(Schema::hasColumn('home_page_contents', 'seo_description'));
    }
}

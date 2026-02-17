<?php

namespace Tests\Feature\Database;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MediaSchemaSmokeTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function polymorphic_media_tables_and_columns_exist(): void
    {
        $this->assertTrue(Schema::hasTable('image_types'));
        $this->assertTrue(Schema::hasTable('images'));
        $this->assertTrue(Schema::hasTable('image_groups'));
        $this->assertTrue(Schema::hasTable('image_group_items'));
        $this->assertTrue(Schema::hasTable('imageables'));
        $this->assertTrue(Schema::hasTable('image_groupables'));

        $this->assertTrue(Schema::hasColumns('images', [
            'image_type_id',
            'title',
            'description',
            'is_decorative',
            'deleted_at',
        ]));

        $this->assertTrue(Schema::hasColumns('imageables', [
            'image_id',
            'imageable_type',
            'imageable_id',
            'role',
            'is_active',
            'deleted_at',
        ]));

        $this->assertTrue(Schema::hasColumns('image_groupables', [
            'image_group_id',
            'image_groupable_type',
            'image_groupable_id',
            'role',
            'is_active',
            'deleted_at',
        ]));
    }
}

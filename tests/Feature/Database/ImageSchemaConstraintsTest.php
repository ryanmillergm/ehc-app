<?php

namespace Tests\Feature\Database;

use App\Models\Image;
use App\Models\Imageable;
use App\Models\Language;
use App\Models\PageTranslation;
use App\Models\SiteMediaDefault;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ImageSchemaConstraintsTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function unique_role_per_imageable_target_is_enforced(): void
    {
        $translation = PageTranslation::factory()->create();
        $imageA = Image::factory()->create();
        $imageB = Image::factory()->create();

        Imageable::factory()->create([
            'image_id' => $imageA->id,
            'imageable_type' => PageTranslation::class,
            'imageable_id' => $translation->id,
            'role' => 'header',
        ]);

        $this->expectException(QueryException::class);

        Imageable::factory()->create([
            'image_id' => $imageB->id,
            'imageable_type' => PageTranslation::class,
            'imageable_id' => $translation->id,
            'role' => 'header',
        ]);
    }

    #[Test]
    public function unique_site_default_role_is_enforced(): void
    {
        SiteMediaDefault::factory()->create(['role' => 'og']);

        $this->expectException(QueryException::class);

        SiteMediaDefault::factory()->create(['role' => 'og']);
    }

    #[Test]
    public function home_page_content_has_one_row_per_language(): void
    {
        $language = Language::factory()->english()->create();
        $image = Image::factory()->create();

        \App\Models\HomePageContent::factory()->create([
            'language_id' => $language->id,
            'hero_image_id' => $image->id,
            'featured_image_id' => $image->id,
            'og_image_id' => $image->id,
        ]);

        $this->expectException(QueryException::class);

        \App\Models\HomePageContent::factory()->create([
            'language_id' => $language->id,
            'hero_image_id' => $image->id,
            'featured_image_id' => $image->id,
            'og_image_id' => $image->id,
        ]);
    }
}

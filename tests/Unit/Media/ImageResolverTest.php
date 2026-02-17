<?php

namespace Tests\Unit\Media;

use App\Models\Image;
use App\Models\Imageable;
use App\Models\Language;
use App\Models\Page;
use App\Models\PageTranslation;
use App\Models\SiteMediaDefault;
use App\Services\Media\ImageResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ImageResolverTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_returns_direct_role_image_for_translation(): void
    {
        $translation = $this->makeTranslationPair()['current'];
        $image = Image::factory()->create(['public_url' => 'https://cdn.example.org/header.jpg']);

        Imageable::factory()->create([
            'imageable_type' => PageTranslation::class,
            'imageable_id' => $translation->id,
            'role' => 'header',
            'image_id' => $image->id,
        ]);

        $resolved = app(ImageResolver::class)->resolveForTranslation($translation, 'header');

        $this->assertSame('https://cdn.example.org/header.jpg', $resolved['url']);
        $this->assertSame('header', $resolved['role']);
        $this->assertSame('role', $resolved['source']);
    }

    #[Test]
    public function it_falls_back_to_featured_for_header_when_header_missing(): void
    {
        $translation = $this->makeTranslationPair()['current'];
        $featured = Image::factory()->create(['public_url' => 'https://cdn.example.org/featured.jpg']);

        Imageable::factory()->create([
            'imageable_type' => PageTranslation::class,
            'imageable_id' => $translation->id,
            'role' => 'featured',
            'image_id' => $featured->id,
        ]);

        $resolved = app(ImageResolver::class)->resolveForTranslation($translation, 'header');

        $this->assertSame('https://cdn.example.org/featured.jpg', $resolved['url']);
        $this->assertSame('featured', $resolved['role']);
    }

    #[Test]
    public function it_falls_back_to_site_default_when_translation_has_no_active_image(): void
    {
        $translation = $this->makeTranslationPair()['current'];
        $inactive = Image::factory()->create([
            'public_url' => 'https://cdn.example.org/inactive.jpg',
            'is_active' => false,
        ]);
        $default = Image::factory()->create(['public_url' => 'https://cdn.example.org/default-og.jpg']);

        Imageable::factory()->create([
            'imageable_type' => PageTranslation::class,
            'imageable_id' => $translation->id,
            'role' => 'og',
            'image_id' => $inactive->id,
        ]);
        SiteMediaDefault::factory()->create([
            'role' => 'og',
            'image_id' => $default->id,
        ]);

        $resolved = app(ImageResolver::class)->resolveForTranslation($translation, 'og');

        $this->assertSame('https://cdn.example.org/default-og.jpg', $resolved['url']);
        $this->assertSame('site_default', $resolved['source']);
        $this->assertSame('og', $resolved['role']);
    }

    #[Test]
    public function it_falls_back_to_default_language_translation(): void
    {
        $pair = $this->makeTranslationPair();
        $defaultTranslation = $pair['default'];
        $currentTranslation = $pair['current'];

        $image = Image::factory()->create(['public_url' => 'https://cdn.example.org/default-language.jpg']);
        Imageable::factory()->create([
            'imageable_type' => PageTranslation::class,
            'imageable_id' => $defaultTranslation->id,
            'role' => 'featured',
            'image_id' => $image->id,
        ]);

        $resolved = app(ImageResolver::class)->resolveForTranslation($currentTranslation, 'header');

        $this->assertSame('https://cdn.example.org/default-language.jpg', $resolved['url']);
        $this->assertSame('featured', $resolved['role']);
    }

    #[Test]
    public function it_ignores_soft_deleted_imageable_assignments(): void
    {
        $translation = $this->makeTranslationPair()['current'];
        $image = Image::factory()->create(['public_url' => 'https://cdn.example.org/header-soft-delete.jpg']);
        $fallback = Image::factory()->create(['public_url' => 'https://cdn.example.org/default-header.jpg']);

        $assignment = Imageable::factory()->create([
            'imageable_type' => PageTranslation::class,
            'imageable_id' => $translation->id,
            'role' => 'header',
            'image_id' => $image->id,
        ]);
        $assignment->delete();

        SiteMediaDefault::factory()->create([
            'role' => 'header',
            'image_id' => $fallback->id,
        ]);

        $resolved = app(ImageResolver::class)->resolveForTranslation($translation, 'header');

        $this->assertSame('https://cdn.example.org/default-header.jpg', $resolved['url']);
        $this->assertSame('site_default', $resolved['source']);
    }

    #[Test]
    public function it_ignores_soft_deleted_images(): void
    {
        $translation = $this->makeTranslationPair()['current'];
        $image = Image::factory()->create(['public_url' => 'https://cdn.example.org/header-deleted.jpg']);
        $fallback = Image::factory()->create(['public_url' => 'https://cdn.example.org/default-featured.jpg']);

        Imageable::factory()->create([
            'imageable_type' => PageTranslation::class,
            'imageable_id' => $translation->id,
            'role' => 'header',
            'image_id' => $image->id,
        ]);
        $image->delete();

        SiteMediaDefault::factory()->create([
            'role' => 'header',
            'image_id' => $fallback->id,
        ]);

        $resolved = app(ImageResolver::class)->resolveForTranslation($translation, 'header');

        $this->assertSame('https://cdn.example.org/default-featured.jpg', $resolved['url']);
        $this->assertSame('site_default', $resolved['source']);
    }

    #[Test]
    public function it_returns_decorative_metadata_in_resolved_payload(): void
    {
        $translation = $this->makeTranslationPair()['current'];
        $image = Image::factory()->create([
            'public_url' => 'https://cdn.example.org/decorative.jpg',
            'title' => 'Decorative Hero',
            'description' => 'Decorative hero image',
            'is_decorative' => true,
        ]);

        Imageable::factory()->create([
            'imageable_type' => PageTranslation::class,
            'imageable_id' => $translation->id,
            'role' => 'header',
            'image_id' => $image->id,
        ]);

        $resolved = app(ImageResolver::class)->resolveForTranslation($translation, 'header');

        $this->assertTrue($resolved['is_decorative']);
        $this->assertSame('Decorative Hero', $resolved['title']);
        $this->assertSame('Decorative hero image', $resolved['description']);
    }

    /**
     * @return array{default: PageTranslation, current: PageTranslation}
     */
    private function makeTranslationPair(): array
    {
        $english = Language::factory()->english()->create();
        $spanish = Language::factory()->spanish()->create();
        $page = Page::factory()->create(['is_active' => true]);

        $defaultTranslation = PageTranslation::factory()->create([
            'page_id' => $page->id,
            'language_id' => $english->id,
            'is_active' => true,
        ]);

        $currentTranslation = PageTranslation::factory()->create([
            'page_id' => $page->id,
            'language_id' => $spanish->id,
            'is_active' => true,
        ]);

        session(['language_id' => $spanish->id]);

        return ['default' => $defaultTranslation, 'current' => $currentTranslation];
    }
}

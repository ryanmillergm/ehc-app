<?php

namespace Tests\Unit\Media;

use App\Models\Language;
use App\Models\Page;
use App\Models\PageTranslation;
use App\Models\Video;
use App\Models\Videoable;
use App\Services\Media\VideoResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class VideoResolverTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_returns_direct_role_video_for_translation(): void
    {
        $translation = $this->makeTranslationPair()['current'];
        $video = Video::factory()->embed()->create([
            'embed_url' => 'https://www.youtube.com/embed/abc123',
        ]);

        Videoable::factory()->create([
            'videoable_type' => PageTranslation::class,
            'videoable_id' => $translation->id,
            'role' => 'hero_video',
            'video_id' => $video->id,
        ]);

        $resolved = app(VideoResolver::class)->resolveForTranslation($translation, 'hero_video');

        $this->assertSame('https://www.youtube.com/embed/abc123', $resolved['url']);
        $this->assertSame('hero_video', $resolved['role']);
        $this->assertSame('role', $resolved['source']);
        $this->assertSame('embed', $resolved['source_type']);
    }

    #[Test]
    public function it_falls_back_to_featured_video_when_hero_video_missing(): void
    {
        $translation = $this->makeTranslationPair()['current'];
        $video = Video::factory()->create([
            'public_url' => 'https://cdn.example.org/featured.mp4',
        ]);

        Videoable::factory()->create([
            'videoable_type' => PageTranslation::class,
            'videoable_id' => $translation->id,
            'role' => 'featured_video',
            'video_id' => $video->id,
        ]);

        $resolved = app(VideoResolver::class)->resolveForTranslation($translation, 'hero_video');

        $this->assertSame('https://cdn.example.org/featured.mp4', $resolved['url']);
        $this->assertSame('featured_video', $resolved['role']);
    }

    #[Test]
    public function it_ignores_soft_deleted_and_inactive_video_assignments(): void
    {
        $translation = $this->makeTranslationPair()['current'];
        $video = Video::factory()->create([
            'public_url' => 'https://cdn.example.org/deleted.mp4',
        ]);
        $fallback = Video::factory()->create([
            'public_url' => 'https://cdn.example.org/fallback.mp4',
        ]);

        $assignment = Videoable::factory()->create([
            'videoable_type' => PageTranslation::class,
            'videoable_id' => $translation->id,
            'role' => 'hero_video',
            'video_id' => $video->id,
        ]);
        $assignment->delete();

        Videoable::factory()->create([
            'videoable_type' => PageTranslation::class,
            'videoable_id' => $translation->id,
            'role' => 'featured_video',
            'video_id' => $fallback->id,
        ]);

        $resolved = app(VideoResolver::class)->resolveForTranslation($translation, 'hero_video');
        $this->assertSame('https://cdn.example.org/fallback.mp4', $resolved['url']);

        $fallback->forceFill(['is_active' => false])->save();
        $resolvedMissing = app(VideoResolver::class)->resolveForTranslation($translation, 'hero_video');
        $this->assertNull($resolvedMissing);
    }

    #[Test]
    public function it_falls_back_to_default_language_translation_assignment(): void
    {
        $pair = $this->makeTranslationPair();
        $defaultTranslation = $pair['default'];
        $currentTranslation = $pair['current'];

        $video = Video::factory()->create([
            'public_url' => 'https://cdn.example.org/default-language.mp4',
        ]);

        Videoable::factory()->create([
            'videoable_type' => PageTranslation::class,
            'videoable_id' => $defaultTranslation->id,
            'role' => 'featured_video',
            'video_id' => $video->id,
        ]);

        $resolved = app(VideoResolver::class)->resolveForTranslation($currentTranslation, 'hero_video');
        $this->assertSame('https://cdn.example.org/default-language.mp4', $resolved['url']);
        $this->assertSame('featured_video', $resolved['role']);
    }

    #[Test]
    public function it_ignores_inactive_role_assignment_and_uses_next_role_in_chain(): void
    {
        $translation = $this->makeTranslationPair()['current'];

        $inactiveHero = Video::factory()->create([
            'public_url' => 'https://cdn.example.org/inactive-assignment.mp4',
        ]);

        $featured = Video::factory()->create([
            'public_url' => 'https://cdn.example.org/featured-active.mp4',
        ]);

        Videoable::factory()->create([
            'videoable_type' => PageTranslation::class,
            'videoable_id' => $translation->id,
            'role' => 'hero_video',
            'video_id' => $inactiveHero->id,
            'is_active' => false,
        ]);

        Videoable::factory()->create([
            'videoable_type' => PageTranslation::class,
            'videoable_id' => $translation->id,
            'role' => 'featured_video',
            'video_id' => $featured->id,
            'is_active' => true,
        ]);

        $resolved = app(VideoResolver::class)->resolveForTranslation($translation, 'hero_video');

        $this->assertSame('https://cdn.example.org/featured-active.mp4', $resolved['url']);
        $this->assertSame('featured_video', $resolved['role']);
    }

    #[Test]
    public function it_prefers_current_translation_assignment_over_default_language_assignment(): void
    {
        $pair = $this->makeTranslationPair();
        $defaultTranslation = $pair['default'];
        $currentTranslation = $pair['current'];

        $currentVideo = Video::factory()->create(['public_url' => 'https://cdn.example.org/current.mp4']);
        $defaultVideo = Video::factory()->create(['public_url' => 'https://cdn.example.org/default.mp4']);

        Videoable::factory()->create([
            'videoable_type' => PageTranslation::class,
            'videoable_id' => $currentTranslation->id,
            'role' => 'hero_video',
            'video_id' => $currentVideo->id,
            'is_active' => true,
        ]);

        Videoable::factory()->create([
            'videoable_type' => PageTranslation::class,
            'videoable_id' => $defaultTranslation->id,
            'role' => 'hero_video',
            'video_id' => $defaultVideo->id,
            'is_active' => true,
        ]);

        $resolved = app(VideoResolver::class)->resolveForTranslation($currentTranslation, 'hero_video');

        $this->assertNotNull($resolved);
        $this->assertSame('https://cdn.example.org/current.mp4', $resolved['url']);
    }

    #[Test]
    public function it_prefers_hero_video_over_featured_video_when_both_exist(): void
    {
        $translation = $this->makeTranslationPair()['current'];

        $hero = Video::factory()->create([
            'public_url' => 'https://cdn.example.org/hero-priority.mp4',
        ]);

        $featured = Video::factory()->create([
            'public_url' => 'https://cdn.example.org/featured-secondary.mp4',
        ]);

        Videoable::factory()->create([
            'videoable_type' => PageTranslation::class,
            'videoable_id' => $translation->id,
            'role' => 'hero_video',
            'video_id' => $hero->id,
            'is_active' => true,
        ]);

        Videoable::factory()->create([
            'videoable_type' => PageTranslation::class,
            'videoable_id' => $translation->id,
            'role' => 'featured_video',
            'video_id' => $featured->id,
            'is_active' => true,
        ]);

        $resolved = app(VideoResolver::class)->resolveForTranslation($translation, 'hero_video');

        $this->assertNotNull($resolved);
        $this->assertSame('https://cdn.example.org/hero-priority.mp4', $resolved['url']);
        $this->assertSame('hero_video', $resolved['role']);
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

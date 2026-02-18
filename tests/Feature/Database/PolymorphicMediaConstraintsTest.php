<?php

namespace Tests\Feature\Database;

use App\Models\HomePageContent;
use App\Models\Image;
use App\Models\ImageGroup;
use App\Models\ImageGroupable;
use App\Models\Imageable;
use App\Models\Language;
use App\Models\PageTranslation;
use App\Models\Video;
use App\Models\Videoable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PolymorphicMediaConstraintsTest extends TestCase
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
    public function unique_role_per_image_groupable_target_is_enforced(): void
    {
        $translation = PageTranslation::factory()->create();
        $groupA = ImageGroup::factory()->create();
        $groupB = ImageGroup::factory()->create();

        ImageGroupable::factory()->create([
            'image_group_id' => $groupA->id,
            'image_groupable_type' => PageTranslation::class,
            'image_groupable_id' => $translation->id,
            'role' => 'gallery',
        ]);

        $this->expectException(QueryException::class);

        ImageGroupable::factory()->create([
            'image_group_id' => $groupB->id,
            'image_groupable_type' => PageTranslation::class,
            'image_groupable_id' => $translation->id,
            'role' => 'gallery',
        ]);
    }

    #[Test]
    public function same_role_can_exist_on_different_models_due_to_polymorphic_type(): void
    {
        $translation = PageTranslation::factory()->create();
        $english = Language::factory()->english()->create();
        $homeContent = HomePageContent::factory()->create(['language_id' => $english->id]);
        $imageA = Image::factory()->create();
        $imageB = Image::factory()->create();

        Imageable::factory()->create([
            'image_id' => $imageA->id,
            'imageable_type' => PageTranslation::class,
            'imageable_id' => $translation->id,
            'role' => 'header',
        ]);

        Imageable::factory()->create([
            'image_id' => $imageB->id,
            'imageable_type' => HomePageContent::class,
            'imageable_id' => $homeContent->id,
            'role' => 'header',
        ]);

        $this->assertSame(2, Imageable::query()->where('role', 'header')->count());
    }

    #[Test]
    public function unique_role_per_videoable_target_is_enforced(): void
    {
        $translation = PageTranslation::factory()->create();
        $videoA = Video::factory()->create();
        $videoB = Video::factory()->create();

        Videoable::factory()->create([
            'video_id' => $videoA->id,
            'videoable_type' => PageTranslation::class,
            'videoable_id' => $translation->id,
            'role' => 'hero_video',
        ]);

        $this->expectException(QueryException::class);

        Videoable::factory()->create([
            'video_id' => $videoB->id,
            'videoable_type' => PageTranslation::class,
            'videoable_id' => $translation->id,
            'role' => 'hero_video',
        ]);
    }

    #[Test]
    public function same_video_role_can_exist_on_different_models_due_to_polymorphic_type(): void
    {
        $translation = PageTranslation::factory()->create();
        $english = Language::factory()->english()->create();
        $homeContent = HomePageContent::factory()->create(['language_id' => $english->id]);
        $videoA = Video::factory()->create();
        $videoB = Video::factory()->create();

        Videoable::factory()->create([
            'video_id' => $videoA->id,
            'videoable_type' => PageTranslation::class,
            'videoable_id' => $translation->id,
            'role' => 'hero_video',
        ]);

        Videoable::factory()->create([
            'video_id' => $videoB->id,
            'videoable_type' => HomePageContent::class,
            'videoable_id' => $homeContent->id,
            'role' => 'hero_video',
        ]);

        $this->assertSame(2, Videoable::query()->where('role', 'hero_video')->count());
    }

    #[Test]
    public function force_deleting_video_cascades_to_videoable_assignments(): void
    {
        $translation = PageTranslation::factory()->create();
        $video = Video::factory()->create();

        $videoable = Videoable::factory()->create([
            'video_id' => $video->id,
            'videoable_type' => PageTranslation::class,
            'videoable_id' => $translation->id,
            'role' => 'hero_video',
        ]);

        $video->forceDelete();

        $this->assertDatabaseMissing('videoables', [
            'id' => $videoable->id,
        ]);
    }
}

<?php

namespace Tests\Feature\Database;

use App\Models\HomePageContent;
use App\Models\Image;
use App\Models\ImageGroup;
use App\Models\ImageGroupable;
use App\Models\Imageable;
use App\Models\Language;
use App\Models\PageTranslation;
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
}

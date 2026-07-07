<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\Imageables\Pages\CreateImageable;
use App\Filament\Resources\Imageables\Pages\ListImageables;
use App\Models\HomePageContent;
use App\Models\Image;
use App\Models\Imageable;
use App\Models\PageTranslation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ImageableResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed('PermissionSeeder');
        $this->signInWithPermissions(null, ['admin.panel']);
    }

    public function test_imageable_id_rejects_negative_values(): void
    {
        $image = Image::factory()->create();

        Livewire::actingAs(User::first())
            ->test(CreateImageable::class)
            ->fillForm([
                'image_id' => $image->id,
                'imageable_type' => PageTranslation::class,
                'imageable_id' => -120,
                'role' => 'header',
                'sort_order' => 0,
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasFormErrors(['imageable_id']);
    }

    public function test_imageable_relationship_can_be_created_with_valid_target(): void
    {
        $image = Image::factory()->create();
        $target = HomePageContent::factory()->create();

        Livewire::actingAs(User::first())
            ->test(CreateImageable::class)
            ->fillForm([
                'image_id' => $image->id,
                'imageable_type' => HomePageContent::class,
                'imageable_id' => $target->id,
                'role' => 'featured',
                'sort_order' => 0,
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas(Imageable::class, [
            'image_id' => $image->id,
            'imageable_type' => HomePageContent::class,
            'imageable_id' => $target->id,
            'role' => 'featured',
            'is_active' => true,
        ]);
    }

    public function test_imageable_id_fails_when_target_does_not_exist_for_selected_type(): void
    {
        $image = Image::factory()->create();
        $invalidForPageTranslationId = HomePageContent::factory()->count(3)->create()->last()->id;

        Livewire::actingAs(User::first())
            ->test(CreateImageable::class)
            ->fillForm([
                'image_id' => $image->id,
                'imageable_type' => PageTranslation::class,
                'imageable_id' => $invalidForPageTranslationId,
                'role' => 'header',
                'sort_order' => 0,
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasFormErrors(['imageable_id']);
    }

    public function test_changing_imageable_type_resets_related_record_selection(): void
    {
        $firstTarget = HomePageContent::factory()->create();

        Livewire::actingAs(User::first())
            ->test(CreateImageable::class)
            ->fillForm([
                'imageable_type' => HomePageContent::class,
                'imageable_id' => $firstTarget->id,
            ])
            ->fillForm([
                'imageable_type' => PageTranslation::class,
            ])
            ->assertFormSet([
                'imageable_id' => null,
            ]);
    }

    public function test_imageable_type_rejects_unknown_model_class(): void
    {
        $image = Image::factory()->create();
        $target = HomePageContent::factory()->create();

        Livewire::actingAs(User::first())
            ->test(CreateImageable::class)
            ->fillForm([
                'image_id' => $image->id,
                'imageable_type' => 'App\\Models\\UnknownModel',
                'imageable_id' => $target->id,
                'role' => 'featured',
                'sort_order' => 0,
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasFormErrors(['imageable_type']);
    }

    public function test_imageables_table_shows_human_readable_related_type_labels(): void
    {
        $image = Image::factory()->create();
        $target = PageTranslation::factory()->create();

        Imageable::factory()->create([
            'image_id' => $image->id,
            'imageable_type' => PageTranslation::class,
            'imageable_id' => $target->id,
            'role' => 'header',
            'sort_order' => 0,
            'is_active' => true,
        ]);

        Livewire::actingAs(User::first())
            ->test(ListImageables::class)
            ->assertSee('Page Translation')
            ->assertDontSee(PageTranslation::class);
    }

    public function test_imageable_create_form_shows_open_graph_helper_text(): void
    {
        Livewire::actingAs(User::first())
            ->test(CreateImageable::class)
            ->assertSee('Open Graph social preview image');
    }
}

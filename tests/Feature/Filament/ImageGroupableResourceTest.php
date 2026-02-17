<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\ImageGroupables\Pages\CreateImageGroupable;
use App\Filament\Resources\ImageGroupables\Pages\ListImageGroupables;
use App\Models\HomePageContent;
use App\Models\ImageGroup;
use App\Models\ImageGroupable;
use App\Models\PageTranslation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ImageGroupableResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed('PermissionSeeder');
        $this->signInWithPermissions(null, ['admin.panel']);
    }

    public function test_image_groupable_id_rejects_negative_values(): void
    {
        $group = ImageGroup::factory()->create();

        Livewire::actingAs(User::first())
            ->test(CreateImageGroupable::class)
            ->fillForm([
                'image_group_id' => $group->id,
                'image_groupable_type' => PageTranslation::class,
                'image_groupable_id' => -120,
                'role' => 'gallery',
                'sort_order' => 0,
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasFormErrors(['image_groupable_id']);
    }

    public function test_image_group_relationship_can_be_created_with_valid_target(): void
    {
        $group = ImageGroup::factory()->create();
        $target = HomePageContent::factory()->create();

        Livewire::actingAs(User::first())
            ->test(CreateImageGroupable::class)
            ->fillForm([
                'image_group_id' => $group->id,
                'image_groupable_type' => HomePageContent::class,
                'image_groupable_id' => $target->id,
                'role' => 'gallery',
                'sort_order' => 0,
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas(ImageGroupable::class, [
            'image_group_id' => $group->id,
            'image_groupable_type' => HomePageContent::class,
            'image_groupable_id' => $target->id,
            'role' => 'gallery',
            'is_active' => true,
        ]);
    }

    public function test_image_groupable_id_fails_when_target_does_not_exist_for_selected_type(): void
    {
        $group = ImageGroup::factory()->create();
        $invalidForPageTranslationId = HomePageContent::factory()->count(3)->create()->last()->id;

        Livewire::actingAs(User::first())
            ->test(CreateImageGroupable::class)
            ->fillForm([
                'image_group_id' => $group->id,
                'image_groupable_type' => PageTranslation::class,
                'image_groupable_id' => $invalidForPageTranslationId,
                'role' => 'gallery',
                'sort_order' => 0,
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasFormErrors(['image_groupable_id']);
    }

    public function test_changing_groupable_type_resets_related_record_selection(): void
    {
        $firstTarget = HomePageContent::factory()->create();

        Livewire::actingAs(User::first())
            ->test(CreateImageGroupable::class)
            ->fillForm([
                'image_groupable_type' => HomePageContent::class,
                'image_groupable_id' => $firstTarget->id,
            ])
            ->fillForm([
                'image_groupable_type' => PageTranslation::class,
            ])
            ->assertFormSet([
                'image_groupable_id' => null,
            ]);
    }

    public function test_image_groupable_type_rejects_unknown_model_class(): void
    {
        $group = ImageGroup::factory()->create();
        $target = HomePageContent::factory()->create();

        Livewire::actingAs(User::first())
            ->test(CreateImageGroupable::class)
            ->fillForm([
                'image_group_id' => $group->id,
                'image_groupable_type' => 'App\\Models\\UnknownModel',
                'image_groupable_id' => $target->id,
                'role' => 'gallery',
                'sort_order' => 0,
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasFormErrors(['image_groupable_type']);
    }

    public function test_image_groupables_table_shows_human_readable_related_type_labels(): void
    {
        $group = ImageGroup::factory()->create();
        $target = PageTranslation::factory()->create();

        ImageGroupable::factory()->create([
            'image_group_id' => $group->id,
            'image_groupable_type' => PageTranslation::class,
            'image_groupable_id' => $target->id,
            'role' => 'gallery',
            'sort_order' => 0,
            'is_active' => true,
        ]);

        Livewire::actingAs(User::first())
            ->test(ListImageGroupables::class)
            ->assertSee('Page Translation')
            ->assertDontSee(PageTranslation::class);
    }
}

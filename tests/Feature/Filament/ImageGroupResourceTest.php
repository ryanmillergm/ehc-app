<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\ImageGroups\Pages\EditImageGroup;
use App\Filament\Resources\ImageGroups\RelationManagers\ItemsRelationManager;
use App\Filament\Resources\ImageGroupItems\ImageGroupItemResource;
use App\Models\Image;
use App\Models\ImageGroup;
use App\Models\ImageGroupItem;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ImageGroupResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed('PermissionSeeder');
        $this->signInWithPermissions(null, ['admin.panel']);
    }

    public function test_image_group_items_relation_manager_renders_on_edit_page(): void
    {
        $group = ImageGroup::factory()->create();

        Livewire::test(ItemsRelationManager::class, [
            'ownerRecord' => $group,
            'pageClass' => EditImageGroup::class,
        ])->assertSuccessful();
    }

    public function test_image_group_items_relation_manager_lists_group_items(): void
    {
        $group = ImageGroup::factory()->create();
        $image = Image::factory()->create();

        $item = ImageGroupItem::factory()->create([
            'image_group_id' => $group->id,
            'image_id' => $image->id,
            'sort_order' => 0,
            'is_active' => true,
        ]);

        Livewire::test(ItemsRelationManager::class, [
            'ownerRecord' => $group,
            'pageClass' => EditImageGroup::class,
        ])->assertCanSeeTableRecords([$item]);
    }

    public function test_image_group_items_relation_manager_can_create_edit_and_delete_items(): void
    {
        $group = ImageGroup::factory()->create();
        $image = Image::factory()->create();

        Livewire::test(ItemsRelationManager::class, [
            'ownerRecord' => $group,
            'pageClass' => EditImageGroup::class,
        ])
            ->callAction(
                TestAction::make(CreateAction::class)->table(),
                [
                    'image_id' => $image->id,
                    'sort_order' => 5,
                    'is_active' => true,
                ],
            )
            ->assertHasNoFormErrors();

        $item = ImageGroupItem::query()
            ->where('image_group_id', $group->id)
            ->where('image_id', $image->id)
            ->firstOrFail();

        Livewire::test(ItemsRelationManager::class, [
            'ownerRecord' => $group,
            'pageClass' => EditImageGroup::class,
        ])
            ->callTableAction(EditAction::class, $item, [
                'sort_order' => 9,
                'is_active' => false,
            ])
            ->assertHasNoFormErrors();

        $item->refresh();
        $this->assertSame(9, (int) $item->sort_order);
        $this->assertFalse((bool) $item->is_active);

        Livewire::test(ItemsRelationManager::class, [
            'ownerRecord' => $group,
            'pageClass' => EditImageGroup::class,
        ])->callTableAction(DeleteAction::class, $item);

        $this->assertSoftDeleted('image_group_items', [
            'id' => $item->id,
        ]);
    }

    public function test_image_group_item_resource_does_not_register_navigation(): void
    {
        $this->assertFalse(ImageGroupItemResource::shouldRegisterNavigation());
    }

    public function test_inserting_at_existing_sort_order_resequences_items(): void
    {
        $group = ImageGroup::factory()->create();
        $firstImage = Image::factory()->create();
        $secondImage = Image::factory()->create();

        $first = ImageGroupItem::factory()->create([
            'image_group_id' => $group->id,
            'image_id' => $firstImage->id,
            'sort_order' => 3,
            'is_active' => true,
        ]);

        Livewire::test(ItemsRelationManager::class, [
            'ownerRecord' => $group,
            'pageClass' => EditImageGroup::class,
        ])
            ->callAction(
                TestAction::make(CreateAction::class)->table(),
                [
                    'image_id' => $secondImage->id,
                    'sort_order' => 3,
                    'is_active' => true,
                ],
            )
            ->assertHasNoFormErrors();

        $rowsAtThree = ImageGroupItem::query()
            ->where('image_group_id', $group->id)
            ->where('sort_order', 3)
            ->get();

        $this->assertCount(1, $rowsAtThree);
        $this->assertSame($secondImage->id, $rowsAtThree->first()->image_id);

        $first->refresh();
        $this->assertSame(4, (int) $first->sort_order);
    }
}

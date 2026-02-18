<?php

namespace Tests\Feature\Filament;

use App\Enums\HomeSectionKey;
use App\Filament\Resources\HomeSections\Pages\CreateHomeSection;
use App\Filament\Resources\HomeSections\Pages\EditHomeSection;
use App\Filament\Resources\HomeSections\RelationManagers\ItemsRelationManager;
use App\Models\HomeSection;
use App\Models\HomeSectionItem;
use App\Models\Language;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class HomeSectionResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed('PermissionSeeder');
        $this->signInWithPermissions(null, ['admin.panel']);
    }

    public function test_items_relation_manager_renders_for_home_section(): void
    {
        $section = HomeSection::factory()->for(Language::factory()->english())->create();

        Livewire::test(ItemsRelationManager::class, [
            'ownerRecord' => $section,
            'pageClass' => EditHomeSection::class,
        ])->assertSuccessful();
    }

    public function test_items_relation_manager_can_create_edit_and_delete_items(): void
    {
        $section = HomeSection::factory()->for(Language::factory()->english())->create();

        Livewire::test(ItemsRelationManager::class, [
            'ownerRecord' => $section,
            'pageClass' => EditHomeSection::class,
        ])
            ->callAction(
                TestAction::make(CreateAction::class)->table(),
                [
                    'item_key' => 'phase',
                    'label' => '01',
                    'title' => 'Phase one',
                    'description' => 'First step',
                    'sort_order' => 1,
                    'is_active' => true,
                ],
            )
            ->assertHasNoFormErrors();

        $item = HomeSectionItem::query()
            ->where('home_section_id', $section->id)
            ->firstOrFail();

        Livewire::test(ItemsRelationManager::class, [
            'ownerRecord' => $section,
            'pageClass' => EditHomeSection::class,
        ])
            ->callTableAction(EditAction::class, $item, [
                'title' => 'Phase one updated',
                'sort_order' => 2,
            ])
            ->assertHasNoFormErrors();

        $item->refresh();
        $this->assertSame('Phase one updated', $item->title);
        $this->assertSame(2, (int) $item->sort_order);

        Livewire::test(ItemsRelationManager::class, [
            'ownerRecord' => $section,
            'pageClass' => EditHomeSection::class,
        ])->callTableAction(DeleteAction::class, $item);

        $this->assertDatabaseMissing('home_section_items', [
            'id' => $item->id,
        ]);
    }

    public function test_create_home_section_rejects_invalid_section_key(): void
    {
        $language = Language::factory()->english()->create();

        Livewire::test(CreateHomeSection::class)
            ->fillForm([
                'language_id' => $language->id,
                'section_key' => 'not_a_valid_key',
                'heading' => 'Invalid Section',
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasFormErrors(['section_key']);
    }

    public function test_create_home_section_accepts_enum_section_key(): void
    {
        $language = Language::factory()->english()->create();

        Livewire::test(CreateHomeSection::class)
            ->fillForm([
                'language_id' => $language->id,
                'section_key' => HomeSectionKey::PreGiveCta->value,
                'heading' => 'Pre Give CTA',
                'cta_primary_label' => 'Jump to donation form →',
                'cta_primary_url' => '#give-form',
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('home_sections', [
            'language_id' => $language->id,
            'section_key' => HomeSectionKey::PreGiveCta->value,
            'heading' => 'Pre Give CTA',
        ]);
    }
}

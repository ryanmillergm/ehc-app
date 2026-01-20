<?php

namespace Tests\Feature\Filament\Volunteers;

use App\Filament\Resources\ApplicationForms\Pages\EditApplicationForm;
use App\Filament\Resources\ApplicationForms\RelationManagers\FieldsRelationManager;
use App\Models\ApplicationForm;
use App\Models\FormField;
use App\Models\FormFieldPlacement;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithFilamentAdmin;
use Tests\TestCase;

class ApplicationFormFieldsRelationManagerTest extends TestCase
{
    use RefreshDatabase;
    use InteractsWithFilamentAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->loginAsSuperAdmin();
    }

    #[Test]
    public function edit_page_renders_including_questions_relation_manager(): void
    {
        $form = ApplicationForm::factory()->create();

        Livewire::test(EditApplicationForm::class, ['record' => $form->getRouteKey()])
            ->assertOk()
            ->assertSeeLivewire(FieldsRelationManager::class);
    }

    #[Test]
    public function it_can_attach_a_global_field_to_the_form_via_relation_manager(): void
    {
        $form = ApplicationForm::factory()->create();

        $field = FormField::query()->create([
            'key'       => 'experience_years',
            'type'      => 'text',
            'label'     => 'Years of experience',
            'help_text' => 'Rough estimate is fine.',
            'config'    => [
                'min'         => 0,
                'max'         => 80,
                'placeholder' => 'e.g. 5',
            ],
        ]);

        Livewire::test(FieldsRelationManager::class, [
            'ownerRecord' => $form,
            'pageClass'   => EditApplicationForm::class,
        ])
            ->callAction(
                TestAction::make(CreateAction::class)->table(),
                [
                    'form_field_id'       => $field->id,
                    'is_required'         => true,
                    'is_active'           => true,
                    'sort'                => 20,
                    'label_override'      => null,
                    'help_text_override'  => null,
                    'config_override'     => null,
                ],
            )
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('form_field_placements', [
            'fieldable_type' => ApplicationForm::class,
            'fieldable_id'   => $form->id,
            'form_field_id'  => $field->id,
            'is_required'    => 1,
            'is_active'      => 1,
            'sort'           => 20,
        ]);
    }

    #[Test]
    public function it_does_not_allow_attaching_the_same_field_twice(): void
    {
        $form = ApplicationForm::factory()->create();

        $field = FormField::query()->create([
            'key'       => 'message_extra',
            'type'      => 'textarea',
            'label'     => 'Extra message',
            'help_text' => null,
            'config'    => ['rows' => 3],
        ]);

        FormFieldPlacement::query()->create([
            'fieldable_type' => ApplicationForm::class,
            'fieldable_id'   => $form->id,
            'form_field_id'  => $field->id,
            'is_required'    => false,
            'is_active'      => true,
            'sort'           => 30,
        ]);

        Livewire::test(FieldsRelationManager::class, [
            'ownerRecord' => $form,
            'pageClass'   => EditApplicationForm::class,
        ])
            ->callAction(
                TestAction::make(CreateAction::class)->table(),
                [
                    'form_field_id' => $field->id,
                    'is_required'   => false,
                    'is_active'     => true,
                    'sort'          => 40,
                ],
            )
            ->assertHasFormErrors([
                'form_field_id' => ['unique'],
            ]);

        $this->assertSame(
            1,
            FormFieldPlacement::query()
                ->where('fieldable_type', ApplicationForm::class)
                ->where('fieldable_id', $form->id)
                ->where('form_field_id', $field->id)
                ->count()
        );
    }

#[Test]
public function it_can_edit_a_placement_to_toggle_required_and_active(): void
{
    $form = ApplicationForm::factory()->create();

    $field = FormField::query()->create([
        'key'   => 'background_check',
        'type'  => 'toggle',
        'label' => 'Are you willing to complete a background check?',
        'help_text' => null,
        'config' => [],
    ]);

    $placement = FormFieldPlacement::query()->create([
        'fieldable_type' => ApplicationForm::class,
        'fieldable_id'   => $form->id,
        'form_field_id'  => $field->id,
        'is_required'    => false,
        'is_active'      => true,
        'sort'           => 10,
    ]);

    Livewire::test(FieldsRelationManager::class, [
        'ownerRecord' => $form,
        'pageClass'   => EditApplicationForm::class,
    ])
        ->callTableAction(
            EditAction::class,
            $placement,
            [
                'is_required' => true,
                'is_active'   => false,
                'sort'        => 15,
            ],
        )
        ->assertHasNoFormErrors();

    $placement->refresh();

    $this->assertTrue($placement->is_required);
    $this->assertFalse($placement->is_active);
    $this->assertSame(15, (int) $placement->sort);
}
#[Test]
public function is_active_is_only_applied_to_placements_not_form_fields(): void
{
    $form = ApplicationForm::factory()->create();

    $field = FormField::query()->create([
        'key'   => 'city',
        'type'  => 'text',
        'label' => 'City',
        'config' => [],
    ]);

    $placement = FormFieldPlacement::query()->create([
        'fieldable_type' => ApplicationForm::class,
        'fieldable_id'   => $form->id,
        'form_field_id'  => $field->id,
        'is_active'      => false,
        'sort'           => 10,
    ]);

    // Assert database truth (no scopes)
    $this->assertFalse(
        FormFieldPlacement::query()
            ->whereKey($placement->id)
            ->value('is_active')
    );

    // Assert FormField has no is_active column
    $this->assertArrayNotHasKey(
        'is_active',
        $field->getAttributes(),
    );
}

#[Test]
public function inactive_placements_are_hidden_from_active_relationship(): void
{
    $form = ApplicationForm::factory()->create();
    $field = FormField::factory()->create();

    // Add an inactive placement
    FormFieldPlacement::query()->create([
        'fieldable_type' => ApplicationForm::class,
        'fieldable_id'   => $form->id,
        'form_field_id'  => $field->id,
        'is_active'      => false,
        'sort'           => 99,
    ]);

    $form->refresh();

    // One active placement exists (the auto "message" field)
    $this->assertCount(1, $form->activeFieldPlacements);

    // Two total placements (active + inactive)
    $this->assertCount(2, $form->fieldPlacements);

    // Helper also respects active-only contract
    $this->assertCount(1, $form->activePlacements());
}


#[Test]
public function form_field_model_has_no_is_active_column_or_scope(): void
{
    $field = FormField::factory()->create();

    // No attribute
    $this->assertArrayNotHasKey('is_active', $field->getAttributes());

    // No global scope silently filtering rows
    $this->assertSame(1, FormField::query()->count());
}


#[Test]
public function fields_relation_manager_mounts_without_errors(): void
{
    $form = ApplicationForm::factory()->create();

    Livewire::test(FieldsRelationManager::class, [
        'ownerRecord' => $form,
        'pageClass'   => EditApplicationForm::class,
    ])
        ->assertOk()
        ->assertSeeLivewire(FieldsRelationManager::class);
}

}

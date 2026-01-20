<?php

namespace Tests\Feature\Filament\Volunteers;

use App\Filament\Resources\ApplicationForms\Pages\EditApplicationForm;
use App\Filament\Resources\ApplicationForms\RelationManagers\FieldsRelationManager;
use App\Models\ApplicationForm;
use App\Models\FormField;
use App\Models\FormFieldPlacement;
use App\Models\User;
use Filament\Actions\CreateAction;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ApplicationFormFieldsRelationManagerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());

        Gate::before(fn () => true);

        try {
            Filament::setCurrentPanel(Filament::getPanel('admin') ?? Filament::getDefaultPanel());
        } catch (\Throwable $e) {
            // ignore
        }
    }

    #[Test]
    public function it_can_attach_a_global_field_to_the_form_via_relation_manager(): void
    {
        $form = ApplicationForm::factory()->create();

        $field = FormField::query()->create([
            'key'   => 'experience_years',
            'type'  => 'text',
            'label' => 'Years of experience',
            'help_text' => 'Rough estimate is fine.',
            'config' => [
                'min' => 0,
                'max' => 80,
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
                    'form_field_id' => $field->id,
                    'is_required' => true,
                    'is_active'   => true,
                    'sort'        => 20,
                    'label_override'     => null,
                    'help_text_override' => null,
                    'config_override'    => null,
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
            'key'   => 'message_extra',
            'type'  => 'textarea',
            'label' => 'Extra message',
            'help_text' => null,
            'config' => [
                'rows' => 3,
            ],
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
}

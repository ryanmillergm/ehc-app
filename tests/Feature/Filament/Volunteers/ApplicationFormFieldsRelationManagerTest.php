<?php

namespace Tests\Feature\Filament\Volunteers;

use App\Filament\Resources\ApplicationForms\Pages\EditApplicationForm;
use App\Filament\Resources\ApplicationForms\RelationManagers\FieldsRelationManager;
use App\Models\ApplicationForm;
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

        // Must be authenticated or Filament will hide actions.
        $this->actingAs(User::factory()->create());

        // Hard-bypass policies/permissions so CreateAction is "visible"
        // (replace this later with your real "admin" login helper.)
        Gate::before(fn () => true);

        // Ensure a panel is set if your app uses panels.
        try {
            Filament::setCurrentPanel(Filament::getPanel('admin') ?? Filament::getDefaultPanel());
        } catch (\Throwable $e) {
            // ignore if your Filament version/app doesn't expose these methods
        }
    }

    #[Test]
    public function it_can_create_a_field_on_the_form_via_relation_manager(): void
    {
        $form = ApplicationForm::factory()->create();

        Livewire::test(FieldsRelationManager::class, [
            'ownerRecord' => $form,
            'pageClass'   => EditApplicationForm::class,
        ])
            ->callAction(
                TestAction::make(CreateAction::class)->table(),
                [
                    'type'        => 'text',
                    'key'         => 'experience_years',
                    'label'       => 'Years of experience',
                    'help_text'   => 'Rough estimate is fine.',
                    'is_required' => true,
                    'is_active'   => true,
                    'sort'        => 20,
                ],
            )
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('application_form_fields', [
            'application_form_id' => $form->id,
            'type' => 'text',
            'key' => 'experience_years',
            'label' => 'Years of experience',
        ]);
    }

    #[Test]
    public function it_requires_unique_key_per_form(): void
    {
        $form = ApplicationForm::factory()->create();

        // Booted() creates key=message already, so this should fail.
        Livewire::test(FieldsRelationManager::class, [
            'ownerRecord' => $form,
            'pageClass'   => EditApplicationForm::class,
        ])
            ->callAction(
                TestAction::make(CreateAction::class)->table(),
                [
                    'type'        => 'text',
                    'key'         => 'message',
                    'label'       => 'Another message',
                    'help_text'   => null,
                    'is_required' => false,
                    'is_active'   => true,
                    'sort'        => 30,
                ],
            )
            ->assertHasFormErrors([
                'key' => ['unique'],
            ]);
    }
}

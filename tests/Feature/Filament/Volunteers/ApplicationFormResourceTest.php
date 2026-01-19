<?php

namespace Tests\Feature\Filament\Volunteers;

use App\Filament\Resources\ApplicationForms\Pages\CreateApplicationForm;
use App\Filament\Resources\ApplicationForms\Pages\EditApplicationForm;
use App\Filament\Resources\ApplicationForms\Pages\ListApplicationForms;
use App\Models\ApplicationForm;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithFilamentAdmin;
use Tests\TestCase;

class ApplicationFormResourceTest extends TestCase
{
    use RefreshDatabase;
    use InteractsWithFilamentAdmin;

    #[Test]
    public function list_page_can_render(): void
    {
        $this->loginAsFilamentAdmin();

        Livewire::test(ListApplicationForms::class)
            ->assertOk();
    }

    #[Test]
    public function create_page_can_create_application_form(): void
    {
        $this->loginAsFilamentAdmin();

        Livewire::test(CreateApplicationForm::class)
            ->fillForm([
                'name' => 'Volunteer - Setup Crew',
                'slug' => 'volunteer-setup-crew',
                'description' => 'Setup crew application form',
                'is_active' => true,
                'use_availability' => false,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('application_forms', [
            'slug' => 'volunteer-setup-crew',
            'use_availability' => 0,
        ]);

        // default message field should exist
        $form = ApplicationForm::where('slug', 'volunteer-setup-crew')->firstOrFail();

        $this->assertDatabaseHas('application_form_fields', [
            'application_form_id' => $form->id,
            'key' => 'message',
        ]);
    }

    #[Test]
    public function edit_page_can_update_application_form(): void
    {
        $this->loginAsFilamentAdmin();

        $form = ApplicationForm::factory()->create([
            'use_availability' => true,
        ]);

        Livewire::test(EditApplicationForm::class, ['record' => $form->getKey()])
            ->fillForm([
                'name' => 'Updated Name',
                'use_availability' => false,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('application_forms', [
            'id' => $form->id,
            'name' => 'Updated Name',
            'use_availability' => 0,
        ]);
    }
}

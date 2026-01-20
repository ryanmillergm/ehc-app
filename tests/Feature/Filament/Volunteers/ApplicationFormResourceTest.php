<?php

namespace Tests\Feature\Filament\Volunteers;

use App\Filament\Resources\ApplicationForms\Pages\CreateApplicationForm;
use App\Filament\Resources\ApplicationForms\Pages\EditApplicationForm;
use App\Filament\Resources\ApplicationForms\Pages\ListApplicationForms;
use App\Models\ApplicationForm;
use App\Models\FormField;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithFilamentAdmin;
use Tests\TestCase;

class ApplicationFormResourceTest extends TestCase
{
    use RefreshDatabase;
    use InteractsWithFilamentAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        if (method_exists($this, 'bootFilamentPermissions')) {
            $this->bootFilamentPermissions();
        }

        $this->loginAsSuperAdmin();
    }

    #[Test]
    public function list_page_can_render(): void
    {
        Livewire::test(ListApplicationForms::class)
            ->assertOk();
    }

    #[Test]
    public function create_page_can_create_application_form(): void
    {
        Livewire::test(CreateApplicationForm::class)
            ->fillForm([
                'name' => 'Volunteer - Setup Crew',
                'slug' => 'volunteer-setup-crew',
                'description' => 'Setup crew application form',
                'is_active' => true,
                'use_availability' => false,

                // keep defaults sane for thank-you:
                'thank_you_format' => ApplicationForm::THANK_YOU_TEXT,
                'thank_you_text' => "Thanks!\nWe will reach out soon.",
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('application_forms', [
            'slug' => 'volunteer-setup-crew',
            'use_availability' => 0,
        ]);

        $form = ApplicationForm::where('slug', 'volunteer-setup-crew')->firstOrFail();

        // ✅ Option B: "message" is a global FormField + a placement
        $messageField = FormField::query()
            ->where('key', 'message')
            ->firstOrFail();

        $this->assertDatabaseHas('form_fields', [
            'id'  => $messageField->id,
            'key' => 'message',
        ]);

        $this->assertDatabaseHas('form_field_placements', [
            'fieldable_type' => ApplicationForm::class,
            'fieldable_id'   => $form->id,
            'form_field_id'  => $messageField->id,
        ]);
    }

    #[Test]
    public function edit_page_can_update_application_form(): void
    {
        $form = ApplicationForm::factory()->create([
            'use_availability' => true,
            'thank_you_format' => ApplicationForm::THANK_YOU_TEXT,
            'thank_you_content' => 'Old',
        ]);

        Livewire::test(EditApplicationForm::class, ['record' => $form->getKey()])
            ->fillForm([
                'name' => 'Updated Name',
                'use_availability' => false,
                // don’t accidentally trigger empty content:
                'thank_you_format' => ApplicationForm::THANK_YOU_TEXT,
                'thank_you_text' => 'Old',
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

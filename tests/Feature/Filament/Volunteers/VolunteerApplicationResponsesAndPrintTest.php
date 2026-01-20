<?php

namespace Tests\Feature\Filament\Volunteers;

use App\Filament\Resources\VolunteerApplications\VolunteerApplicationResource;
use App\Models\ApplicationForm;
use App\Models\FormField;
use App\Models\User;
use App\Models\VolunteerApplication;
use App\Models\VolunteerNeed;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class VolunteerApplicationResponsesAndPrintTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::findOrCreate('admin.panel', 'web');

        $user = User::factory()->create();

        $role = Role::findOrCreate('Super Admin', 'web');
        $role->givePermissionTo('admin.panel');
        $user->assignRole($role);

        $this->actingAs($user);

        // Avoid policy noise in tests
        Gate::before(fn () => true);
    }

    #[Test]
    public function edit_page_shows_responses_section_and_values(): void
    {
        $form = ApplicationForm::factory()->create(['use_availability' => true]);

        // Create + attach a reusable "city" field via placements (polymorphic)
        $cityField = FormField::query()->create([
            'type'      => 'text',
            'key'       => 'city',
            'label'     => 'City',
            'help_text' => null,
            'config'    => ['min' => 2, 'max' => 50],
        ]);

        $form->fieldPlacements()->create([
            'form_field_id' => $cityField->id,
            'is_required'   => true,
            'is_active'     => true,
            'sort'          => 20,
        ]);

        $need = VolunteerNeed::factory()->create([
            'application_form_id' => $form->id,
            'is_active'           => true,
        ]);

        $app = VolunteerApplication::factory()->create([
            'volunteer_need_id' => $need->id,
            'answers' => [
                'message' => 'Happy to serve!',
                'city'    => 'Denver',
            ],
            'availability' => [
                'mon' => ['am' => true, 'pm' => false],
            ],
        ]);

        $res = $this->get(VolunteerApplicationResource::getUrl('edit', ['record' => $app]));
        $res->assertOk();

        $res->assertSee('Responses', false);

        // Values from answers
        $res->assertSee('Happy to serve!', false);
        $res->assertSee('Denver', false);

        // Availability block presence (we don't care about exact formatting)
        $res->assertSee('Availability', false);
        $res->assertSee('Mon', false);
    }

    #[Test]
    public function print_page_renders_and_includes_labels_and_answers(): void
    {
        $form = ApplicationForm::factory()->create(['use_availability' => true]);

        // Ensure there is a second question so we can verify labels render on print view
        $cityField = FormField::query()->create([
            'type'      => 'text',
            'key'       => 'city',
            'label'     => 'City',
            'help_text' => null,
            'config'    => ['min' => 2, 'max' => 50],
        ]);

        $form->fieldPlacements()->create([
            'form_field_id' => $cityField->id,
            'is_required'   => true,
            'is_active'     => true,
            'sort'          => 20,
        ]);

        $need = VolunteerNeed::factory()->create([
            'application_form_id' => $form->id,
            'is_active'           => true,
        ]);

        $app = VolunteerApplication::factory()->create([
            'volunteer_need_id' => $need->id,
            'answers' => [
                'message' => 'Printing test message',
                'city'    => 'Denver',
            ],
            'availability' => [
                'mon' => ['am' => true, 'pm' => false],
            ],
        ]);

        $res = $this->get(VolunteerApplicationResource::getUrl('print', ['record' => $app]));
        $res->assertOk();

        $res->assertSee('Volunteer Application', false);
        $res->assertSee("Volunteer Application #{$app->id}", false);

        // On PRINT, labels should be stable plain text
        $res->assertSee('Why do you want to volunteer', false);
        $res->assertSee('City', false);
        $res->assertSee('Printing test message', false);
        $res->assertSee('Denver', false);

        $res->assertSee('Availability', false);
        $res->assertSee('Mon', false);
    }
}

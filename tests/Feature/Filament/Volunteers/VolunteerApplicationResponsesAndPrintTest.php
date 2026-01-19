<?php

namespace Tests\Feature\Filament\Volunteers;

use App\Filament\Resources\VolunteerApplications\VolunteerApplicationResource;
use App\Models\ApplicationForm;
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

        // Ensure your permission exists so Spatie won't throw.
        Permission::findOrCreate('admin.panel', 'web');

        // Create a user and give them a role that has admin.panel
        $user = User::factory()->create();

        $role = Role::findOrCreate('Super Admin', 'web');
        $role->givePermissionTo('admin.panel');
        $user->assignRole($role);

        $this->actingAs($user);

        // This helps with policies, but the key fix is the Permission above.
        Gate::before(fn () => true);
    }

    #[Test]
    public function edit_page_shows_presented_questions_and_answers(): void
    {
        $form = ApplicationForm::factory()->create(['use_availability' => true]);

        // message is auto-created on ApplicationForm::booted()
        $form->fields()->create([
            'type' => 'text',
            'key' => 'city',
            'label' => 'City',
            'help_text' => null,
            'is_required' => true,
            'is_active' => true,
            'sort' => 20,
            'config' => ['min' => 2, 'max' => 50],
        ]);

        $need = VolunteerNeed::factory()->create([
            'application_form_id' => $form->id,
            'is_active' => true,
        ]);

        $app = VolunteerApplication::factory()->create([
            'volunteer_need_id' => $need->id,
            'answers' => [
                'message' => 'Happy to serve!',
                'city' => 'Denver',
                'availability' => [
                    'mon' => ['am' => true, 'pm' => false],
                ],
            ],
        ]);

        $res = $this->get(VolunteerApplicationResource::getUrl('edit', ['record' => $app]));
        $res->assertOk();

        // labels
        $res->assertSee('Responses', false);
        $res->assertSee('Why do you want to volunteer?', false);
        $res->assertSee('City', false);

        // values
        $res->assertSee('Happy to serve!', false);
        $res->assertSee('Denver', false);

        // availability summary
        $res->assertSee('Availability', false);
        $res->assertSee('Mon', false);
    }

    #[Test]
    public function print_page_renders_and_includes_answers(): void
    {
        $form = ApplicationForm::factory()->create();

        $need = VolunteerNeed::factory()->create([
            'application_form_id' => $form->id,
            'is_active' => true,
        ]);

        $app = VolunteerApplication::factory()->create([
            'volunteer_need_id' => $need->id,
            'answers' => [
                'message' => 'Printing test message',
            ],
        ]);

        $res = $this->get(VolunteerApplicationResource::getUrl('print', ['record' => $app]));
        $res->assertOk();

        $res->assertSee('Volunteer Application', false);
        $res->assertSee('Printing test message', false);
        $res->assertSee("Volunteer Application #{$app->id}", false);
    }
}

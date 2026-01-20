<?php

namespace Tests\Feature\Filament\Volunteers;

use App\Filament\Resources\VolunteerApplications\Pages\EditVolunteerApplication;
use App\Filament\Resources\VolunteerApplications\Pages\ListVolunteerApplications;
use App\Models\VolunteerApplication;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithFilamentAdmin;
use Tests\TestCase;

class VolunteerApplicationResourceTest extends TestCase
{
    use RefreshDatabase;
    use InteractsWithFilamentAdmin;

    #[Test]
    public function list_page_can_render_and_show_records(): void
    {
        $this->loginAsSuperAdmin();

        $apps = VolunteerApplication::factory()->count(3)->create();

        Livewire::test(ListVolunteerApplications::class)
            ->assertOk()
            ->assertCanSeeTableRecords($apps);
    }

    #[Test]
    public function edit_page_shows_applicant_and_need_details(): void
    {
        $this->loginAsSuperAdmin();

        $app = VolunteerApplication::factory()->create();

        Livewire::test(EditVolunteerApplication::class, ['record' => $app->getRouteKey()])
            ->assertOk()
            ->assertSee($app->user->full_name)
            ->assertSee($app->user->email)
            ->assertSee($app->need->title);
    }

    #[Test]
    public function edit_page_can_update_status_notes_interests_and_availability_matrix(): void
    {
        $this->loginAsSuperAdmin();

        $app = VolunteerApplication::factory()->create([
            'status' => VolunteerApplication::STATUS_SUBMITTED,
            'interests' => ['food'],
            'availability' => [
                'sun' => ['am' => true,  'pm' => false],
                'mon' => ['am' => false, 'pm' => false],
                'tue' => ['am' => false, 'pm' => false],
                'wed' => ['am' => false, 'pm' => false],
                'thu' => ['am' => false, 'pm' => false],
                'fri' => ['am' => false, 'pm' => false],
                'sat' => ['am' => false, 'pm' => false],
            ],
        ]);

        Livewire::test(EditVolunteerApplication::class, ['record' => $app->getRouteKey()])
            ->fillForm([
                'status' => VolunteerApplication::STATUS_REVIEWING,
                'internal_notes' => 'Contacted applicant. Scheduling follow-up.',
                'interests' => ['food', 'prayer'],
                'availability' => [
                    'sun' => ['am' => true,  'pm' => true],
                    'mon' => ['am' => false, 'pm' => false],
                    'tue' => ['am' => false, 'pm' => false],
                    'wed' => ['am' => false, 'pm' => true],
                    'thu' => ['am' => false, 'pm' => false],
                    'fri' => ['am' => false, 'pm' => false],
                    'sat' => ['am' => false, 'pm' => false],
                ],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $app->refresh();

        $this->assertSame(VolunteerApplication::STATUS_REVIEWING, $app->status);
        $this->assertSame('Contacted applicant. Scheduling follow-up.', $app->internal_notes);
        $this->assertSame(['food', 'prayer'], $app->interests);

        $this->assertTrue((bool) data_get($app->availability, 'sun.am'));
        $this->assertTrue((bool) data_get($app->availability, 'sun.pm'));
        $this->assertTrue((bool) data_get($app->availability, 'wed.pm'));
        $this->assertFalse((bool) data_get($app->availability, 'thu.am'));
    }

    #[Test]
    public function edit_page_shows_responses_using_field_labels_and_availability(): void
    {
        $this->loginAsSuperAdmin();

        $app = VolunteerApplication::factory()->create([
            'answers' => [
                'message' => 'I want to serve because I’ve been blessed and I want to bless others.',
            ],
            'availability' => [
                'sun' => ['am' => true,  'pm' => false],
                'mon' => ['am' => true,  'pm' => false],
                'tue' => ['am' => true,  'pm' => false],
                'wed' => ['am' => true,  'pm' => false],
                'thu' => ['am' => false, 'pm' => true],
                'fri' => ['am' => true,  'pm' => true],
                'sat' => ['am' => true,  'pm' => true],
            ],
        ]);

        Livewire::test(EditVolunteerApplication::class, ['record' => $app->getRouteKey()])
            ->assertOk()
            //  Assert the human label (from FormField label / placement label())
            ->assertSee('Why do you want to volunteer?')
            //  Assert the actual answer content
            ->assertSee('I want to serve because I’ve been blessed and I want to bless others.')
            //  Availability section renders (we don’t need to assert ✓ specifically)
            ->assertSee('Availability')
            ->assertSee('Sun')
            ->assertSee('Mon');
    }
}

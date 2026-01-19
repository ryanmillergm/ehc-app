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
}

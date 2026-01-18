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
    public function edit_page_can_update_status_and_notes_and_arrays(): void
    {
        $this->loginAsSuperAdmin();

        $app = VolunteerApplication::factory()->create([
            'status' => VolunteerApplication::STATUS_SUBMITTED,
            'interests' => ['food'],
            'availability' => ['thursday'],
        ]);

        Livewire::test(EditVolunteerApplication::class, ['record' => $app->getRouteKey()])
            ->fillForm([
                'status' => VolunteerApplication::STATUS_REVIEWING,
                'internal_notes' => 'Contacted applicant. Scheduling follow-up.',
                'interests' => ['food', 'prayer'],
                'availability' => ['sunday', 'flexible'],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $app->refresh();

        $this->assertSame(VolunteerApplication::STATUS_REVIEWING, $app->status);
        $this->assertSame('Contacted applicant. Scheduling follow-up.', $app->internal_notes);
        $this->assertSame(['food', 'prayer'], $app->interests);
        $this->assertSame(['sunday', 'flexible'], $app->availability);
    }
}

<?php

namespace Tests\Feature\Livewire\Volunteers;

use App\Livewire\Volunteers\Apply;
use App\Models\User;
use App\Models\VolunteerApplication;
use App\Models\VolunteerNeed;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ApplyVolunteerTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_submits_application_and_stores_availability_and_interests_columns(): void
    {
        $user = User::factory()->create();

        $need = VolunteerNeed::factory()->create(['is_active' => true]);
        $need->loadMissing('applicationForm');

        $need->applicationForm->update([
            'is_active' => true,
            'use_availability' => true,
        ]);

        Livewire::actingAs($user)
            // IMPORTANT: pass the model instance to match `public VolunteerNeed $need`
            ->test(Apply::class, ['need' => $need])
            ->set('answers', [
                // your ApplicationForm boot() creates this required field on create
                'message' => 'I would love to volunteer because I want to serve and help wherever needed.',
            ])
            ->set('interests', ['food', 'prayer'])
            ->set('availability', [
                'sun' => ['am' => true,  'pm' => false],
                'mon' => ['am' => false, 'pm' => false],
                'tue' => ['am' => false, 'pm' => false],
                'wed' => ['am' => false, 'pm' => true],
                'thu' => ['am' => false, 'pm' => false],
                'fri' => ['am' => false, 'pm' => false],
                'sat' => ['am' => false, 'pm' => false],
            ])
            ->call('submit')
            ->assertSet('submitted', true);

        $this->assertDatabaseHas('volunteer_applications', [
            'user_id' => $user->id,
            'volunteer_need_id' => $need->id,
            'status' => VolunteerApplication::STATUS_SUBMITTED,
        ]);

        $app = VolunteerApplication::query()
            ->where('user_id', $user->id)
            ->where('volunteer_need_id', $need->id)
            ->firstOrFail();

        $this->assertSame(['food', 'prayer'], $app->interests);

        $this->assertTrue((bool) data_get($app->availability, 'sun.am'));
        $this->assertTrue((bool) data_get($app->availability, 'wed.pm'));
    }
}

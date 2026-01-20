<?php

namespace Tests\Feature\Volunteers;

use App\Models\User;
use App\Models\VolunteerNeed;
use App\Models\VolunteerApplication;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class VolunteerApplicationFlowTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function guests_are_redirected_to_login(): void
    {
        $need = VolunteerNeed::factory()->general()->create();

        $this->get(route('volunteer.apply', $need))
            ->assertRedirect(route('login'));
    }

    #[Test]
    public function authenticated_users_can_view_apply_page_by_slug(): void
    {
        $user = User::factory()->create();
        $need = VolunteerNeed::factory()->general()->create();

        $this->actingAs($user)
            ->get(route('volunteer.apply', $need))
            ->assertOk()
            ->assertSee('Volunteer Application')
            ->assertSee($need->title);
    }

    #[Test]
    public function user_can_submit_a_volunteer_application_for_a_need(): void
    {
        $user = User::factory()->create();
        $need = VolunteerNeed::factory()->create(['slug' => 'setup-crew', 'title' => 'Setup Crew']);

        Livewire::actingAs($user)
            ->test(\App\Livewire\Volunteers\Apply::class, ['need' => $need])
            ->set('message', 'I would love to help with setup and teardown.')
            ->set('interests', ['cleanup', 'logistics'])
            // availability is a structured week grid now; old "['thursday']" format isn't meaningful
            ->set('availability.thu.am', true)
            ->set('availability.thu.pm', false)
            ->call('submit')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('volunteer_applications', [
            'user_id' => $user->id,
            'volunteer_need_id' => $need->id,
            'status' => VolunteerApplication::STATUS_SUBMITTED,
        ]);
    }

    #[Test]
    public function message_is_required(): void
    {
        $user = User::factory()->create();
        $need = VolunteerNeed::factory()->general()->create();

        Livewire::actingAs($user)
            ->test(\App\Livewire\Volunteers\Apply::class, ['need' => $need])
            ->set('message', '')
            ->call('submit')
            ->assertHasErrors(['answers.message' => 'required']);
    }

    #[Test]
    public function user_cannot_submit_duplicate_application_for_same_need(): void
    {
        $user = User::factory()->create();
        $need = VolunteerNeed::factory()->general()->create();

        VolunteerApplication::factory()->create([
            'user_id' => $user->id,
            'volunteer_need_id' => $need->id,
            'status' => VolunteerApplication::STATUS_SUBMITTED,
        ]);

        Livewire::actingAs($user)
            ->test(\App\Livewire\Volunteers\Apply::class, ['need' => $need])
            ->set('message', 'Another application')
            ->call('submit')
            ->assertHasErrors(['duplicate']);
    }

    #[Test]
    public function inactive_need_shows_unavailable_page(): void
    {
        $user = User::factory()->create();
        $need = VolunteerNeed::factory()->create(['is_active' => false]);

        $this->actingAs($user)
            ->get(route('volunteer.apply', $need))
            ->assertOk()
            ->assertSee('data-testid="apply-unavailable"', false);
    }
}

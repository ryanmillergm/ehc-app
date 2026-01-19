<?php

namespace Tests\Feature\Volunteers;

use App\Models\User;
use App\Models\VolunteerNeed;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class VolunteerApplyUnavailableTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_renders_unavailable_state_when_need_has_no_application_form(): void
    {
        $user = User::factory()->create();

        $need = VolunteerNeed::factory()->create([
            'is_active' => true,
        ]);

        if (method_exists($need, 'applicationForm')) {
            $need->applicationForm()->dissociate();
            $need->save();
        } else {
            $need->forceFill(['application_form_id' => null])->save();
        }

        $this->actingAs($user);

        $res = $this->get(route('volunteer.apply', $need));

        $res->assertOk();
        $res->assertSee('data-testid="apply-unavailable"', false);
    }

    #[Test]
    public function it_renders_unavailable_state_when_need_is_inactive(): void
    {
        $user = User::factory()->create();

        $need = VolunteerNeed::factory()->create([
            'is_active' => false,
        ]);

        $this->actingAs($user);

        $res = $this->get(route('volunteer.apply', $need));

        $res->assertOk();
        $res->assertSee('data-testid="apply-unavailable"', false);
    }
}

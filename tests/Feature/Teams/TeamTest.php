<?php

namespace Tests\Feature\Teams;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class TeamTest extends TestCase
{
    use WithFaker, RefreshDatabase;

    /**
     * A basic feature test example.
     */
    public function test_a_team_can_be_created(): void
    {
        $this->withoutExceptionHandling();

        $user = User::factory()->create();

        $attributes = [
            'user_id' => $user->id,
            'name' => $user->first_name . ' ' . $user->last_name . "'s Team",
        ];

        $response = $this->post('/teams', $attributes);

        $this->assertDatabaseHas('teams', $attributes);
    }
    /**
     * A basic feature test example.
     */
    // public function test_a_user_can_belong_to_a_team(): void
    // {
    //     $response = $this->get('/');

    //     $response->assertStatus(200);
    // }
}

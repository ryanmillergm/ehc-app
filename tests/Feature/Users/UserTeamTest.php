<?php

namespace Tests\Feature\Users;

use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class UserTeamTest extends TestCase
{
    use WithFaker, RefreshDatabase;

    /**
     * A user must be authenticated to create a team test
     */
    public function test_an_unauthenticated_user_cannot_create_a_team(): void
    {
        // $this->withoutExceptionHandling();

        $attributes = [
            'user_id' => 2,
            'name' => "Test Unauthenticated Team",
        ];

        $response = $this->post('/teams', $attributes)->assertRedirect('login');

        $this->assertDatabaseMissing('teams', $attributes);
    }

    /**
     * An authenticated user can create a team test
     */
    public function test_an_authenticated_user_can_create_a_team(): void
    {
        $this->withoutExceptionHandling();

        $user = $this->signIn();

        $attributes = [
            'user_id' => $user->id,
            'name' => $user->first_name . ' ' . $user->last_name . "'s Team",
        ];

        $response = $this->post('/teams', $attributes);

        $this->assertDatabaseHas('teams', $attributes);
    }


    /**
     * Test a user can have a team / be the owner of a team - One to Many Relationship
     */
    public function test_a_user_has_a_team(): void
    {
        $this->withoutExceptionHandling();

        $user = User::factory()->create();
        $team = Team::factory([
            'user_id' => $user->id
        ])->create();

        $team_query = Team::where('name', $team->name)->first();

        $this->assertEquals(1, $user->loadCount('teams')->teams_count);
        $this->assertEquals($user->id, $team_query->user->id);

        $user_team = $user->teams->where('name', $team->name)->first();

        $this->assertEquals($user_team->id, $team_query->id);
        $this->assertInstanceOf(Team::class, $user_team);
    }

    /**
     * Test a user can have many teams / be the owner of many teams - One to Many Relationship
     */
    public function test_a_user_can_own_many_teams(): void
    {
        $this->withoutExceptionHandling();

        $user = User::factory()->create();
        $team1 = Team::factory([
            'user_id' => $user->id
        ])->create();
        $team2 = Team::factory([
            'user_id' => $user->id
        ])->create();
        $team3 = Team::factory([
            'user_id' => $user->id
        ])->create();

        $team1_query = Team::where('name', $team1->name)->first();

        $this->assertEquals(3, $user->loadCount('teams')->teams_count);
        $this->assertEquals($user->id, $team1_query->user->id);

        $user_team = $user->teams->where('name', $team1->name)->first();

        $this->assertEquals($user_team->id, $team1_query->id);
        $this->assertInstanceOf(Team::class, $user_team);
    }

    /**
     * Test a user can delete a team as the owner
     */
    public function test_an_owner_can_delete_their_team(): void
    {
        $this->withoutExceptionHandling();

        $user = $this->signIn();
        $team = Team::factory([
            'user_id' => $user->id
        ])->create();

        $attributes = [
            'name' => $team->name,
        ];

        $this->delete('/teams/' . $team->id);

        $this->assertEquals(0, $user->loadCount('teams')->teams_count);
        $this->assertDatabaseMissing('teams', $attributes);
    }

    /**
     * Test all team members are detached when a team is deleted
     */
    public function test_team_users_are_detached_when_a_team_is_deleted(): void
    {
        $this->withoutExceptionHandling();

        $user = $this->signIn();
        $team = Team::factory([
            'user_id' => $user->id
        ])->create();

        $user2 = User::factory()->create();
        $user3 = User::factory()->create();
        $user4 = User::factory()->create();

        $team->users()->attach([
            $user2->id,
            $user3->id,
            $user4->id,
        ]);

        $this->assertEquals(3, $team->loadCount('users')->users_count);

        $attributes = [
            'name' => $team->name,
        ];

        $pivot_table_attributes = [
            'team_id' => $team->id,
        ];

        $this->delete('/teams/' . $team->id);

        $this->assertEquals(0, $user->loadCount('teams')->teams_count);
        $this->assertDatabaseMissing('teams', $attributes);
        $this->assertDatabaseMissing('team_user', $pivot_table_attributes);
    }

    /**
     * A basic feature test example.
     */
    // public function test_a_user_can_belong_to_a_team(): void
    // {
    //     $this->withoutExceptionHandling();

    //     $user = User::factory()->create();
    //     $user2 = User::factory()->create();
    //     $team = Team::factory([
    //         'user_id' => $user->id
    //     ])->create();

    //     $team->users()->attach($user2);

    //     $this->assertEquals(1, $team->users->count());
    // }
}

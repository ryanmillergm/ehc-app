<?php

namespace Tests\Feature\Teams;

use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;


class TeamTest extends TestCase
{
    use WithFaker, RefreshDatabase;

    /**
     * Test teams database has correct columns
     */
    public function test_teams_database_has_expected_columns()
    {
        $this->assertTrue(
          Schema::hasColumns('teams', [
            'id','user_id', 'name'
        ]), 1);
    }

    /**
     * A team can be created test
     */
    public function test_a_team_can_be_created(): void
    {
        $this->withoutExceptionHandling();

        $this->seed('PermissionSeeder');

        $user = $this->signInWithPermissions(null, ['teams.create']);

        $attributes = [
            'user_id' => $user->id,
            'name' => $user->first_name . ' ' . $user->last_name . "'s Team",
            'slug' => 'team-slug',
        ];

        $response = $this->post('/teams', $attributes);

        $this->assertDatabaseHas('teams', $attributes);
    }

    /**
     * Creating a team requires a user id test
     */
    public function test_creating_a_team_requires_a_user_id(): void
    {
        // $this->withoutExceptionHandling();

        $attributes = [
            'name' => 'Test Team',
        ];

        $response = $this->post('/teams', $attributes);

        $this->assertDatabaseMissing('teams', $attributes);
    }

    /**
     * Test a team belongs to a user, a user owns a team - One to Many Relationship
     */
    public function test_a_team_belongs_to_a_user(): void
    {
        $this->withoutExceptionHandling();

        $user = User::factory()->create();
        $team = Team::factory([
            'user_id' => $user->id
        ])->create();

        $this->assertEquals(1, $team->user->count());

        $this->assertEquals($user->id, $team->user->id);

        $this->assertInstanceOf(User::class, $team->user);
    }

    /**
     * A team can have many users
     */
    public function test_a_team_can_have_users(): void
    {
        $this->withoutExceptionHandling();

        $user = User::factory()->create();
        $user2 = User::factory()->create();
        $team = Team::factory([
            'user_id' => $user->id
        ])->create();

        $team->users()->attach($user2);

        $this->assertEquals(1, $team->loadCount('users')->users_count);

        $user3 = User::factory()->create();
        $user4 = User::factory()->create();

        $team->users()->attach([
            $user3->id,
            $user4->id,
        ]);

        $this->assertEquals(3, $team->loadCount('users')->users_count);
    }

    /**
     * A user can be removed from a team
     */
    public function test_a_team_member_can_be_removed(): void
    {
        $this->withoutExceptionHandling();

        $user = User::factory()->create();
        $user2 = User::factory()->create();
        $user3 = User::factory()->create();
        $user4 = User::factory()->create();

        $team = Team::factory([
            'user_id' => $user->id
        ])->create();

        $team->users()->attach([
            $user2->id,
            $user3->id,
            $user4->id,
        ]);

        $this->assertEquals(3, $team->loadCount('users')->users_count);

        $team->users()->detach($user3);

        $this->assertEquals(2, $team->loadCount('users')->users_count);
    }
}

<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\TeamResource;
use App\Filament\Resources\TeamResource\Pages\CreateTeam;
use App\Filament\Resources\TeamResource\Pages\EditTeam;
use App\Filament\Resources\TeamResource\Pages\ListTeams;
use App\Models\Team;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Livewire\Livewire;
use Tests\TestCase;

class TeamResourceTest extends TestCase
{
    use WithFaker, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->signIn();
    }

    /**
     * Test an authenticated user can visit the user resource page in the filament admin panel.
     */
    public function test_an_authenticated_user_can_render_the_user_resource_page(): void
    {
        $this->get(TeamResource::getUrl('index'))->assertSuccessful();
    }

    /**
     * Test an authenticated team can visit the team resource table builder list page in the filament admin panel.
     */
    public function test_an_authenticated_team_can_render_the_team_resource_table_page(): void
    {
        livewire::test(ListTeams::class)->assertSuccessful();
    }

    /**
     * Test an authenticated team can visit the team resource table builder list page and see a list of teams.
     */
    public function test_team_resource_page_can_list_teams(): void
    {
        Team::factory()->count(10)->create();
        $teams = Team::all();


        livewire::test(ListTeams::class)
        ->assertCountTableRecords(10)
        ->assertCanSeeTableRecords($teams);
    }

    /**
     * Test an authenticated team visit the create a team resource page
     */
    public function test_auth_team_visit_create_team_resource_page(): void
    {
        $this->get(teamResource::getUrl('create'))->assertSuccessful();
    }

    /**
     * Test an authenticated team create a team resource
     */
    public function test_auth_team_can_create_team(): void
    {
        $newData = Team::factory()->make();
        $user = User::factory()->create();

        livewire::test(CreateTeam::class)
            ->fillForm([
                'user_id' => $user->id,
                'name' => $newData->name,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas(Team::class, [
            'user_id' => $user->id,
            'name' => $newData->name,
        ]);
    }

    /**
     * Test validation - team requires first name
     */
    public function test_create_team_requires_user_id(): void
    {
        $newData = Team::factory()->make();

        livewire::test(CreateTeam::class)
            ->fillForm([
                'user_id' => null,
                'name' => $newData->name,
            ])
            ->call('create')
            ->assertHasFormErrors(['user_id' => 'required']);

        $this->assertDatabaseMissing(Team::class, [
                'user_id' => $newData->user_id,
                'name' => $newData->name,
        ]);
    }

    /**
     * Test validation - team requires last name
     */
    public function test_create_team_requires_name(): void
    {
        $newData = Team::factory()->make();

        livewire::test(CreateTeam::class)
            ->fillForm([
                'user_id' => $newData->user_id,
                'name' => null,
            ])
            ->call('create')
            ->assertHasFormErrors(['name' => 'required']);

        $this->assertDatabaseMissing(Team::class, [
            'user_id' => $newData->user_id,
            'name' => $newData->name,
        ]);
    }

    /**
     * Test an authenticated team can visit the team resource edit page
     */
    public function test_auth_team_can_visit_team_resource_edit_page(): void
    {
        $this->get(teamResource::getUrl('edit', [
            'record' => Team::factory()->create(),
        ]))->assertSuccessful();
    }

    /**
     * Test team resource edit form retrieves correct data
     */
    public function test_team_resource_edit_form_retrieves_correct_data(): void
    {
        $team = Team::factory()->create();

        livewire::test(EditTeam::class, [
            'record' => $team->getRouteKey(),
        ])
            ->assertFormSet([
                'user_id' => $team->user_id,
                'name' => $team->name,
            ]);
    }

    /**
     * Test team resource edit form saves correct data
     */
    public function test_team_resource_edit_form_saves_correct_data(): void
    {
        $team = Team::factory()->create();
        $newData = Team::factory()->make();

        livewire::test(EditTeam::class, [
            'record' => $team->getRouteKey(),
        ])
            ->fillForm([
                'first_name' => $newData->first_name,
                'last_name' => $newData->last_name,
                'email' => $newData->email,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $team->refresh();

        $this->assertEquals($team->first_name, $newData->first_name);
        $this->assertEquals($team->last_name, $newData->last_name);
        $this->assertEquals($team->email, $newData->email);
    }

    /**
     * Test team resource edit form requires first name
     */
    public function test_team_resource_edit_form_requires_user_id_data(): void
    {
        $team = Team::factory()->create();

        livewire::test(EditTeam::class, [
            'record' => $team->getRouteKey(),
        ])
            ->fillForm([
                'user_id' => null,
            ])
            ->call('save')
            ->assertHasFormErrors(['user_id' => 'required']);
    }

    /**
     * Test team resource edit form requires last name
     */
    public function test_team_resource_edit_form_requires_name_data(): void
    {
        $team = Team::factory()->create();

        livewire::test(EditTeam::class, [
            'record' => $team->getRouteKey(),
        ])
            ->fillForm([
                'name' => null,
            ])
            ->call('save')
            ->assertHasFormErrors(['name' => 'required']);
    }

    /**
     * Test authenticated team can delete a team
     */
    public function test_auth_team_can_delete_a_team(): void
    {
        $team = Team::factory()->create();

        livewire::test(EditTeam::class, [
            'record' => $team->getRouteKey(),
        ])
        ->callAction(DeleteAction::class);

        $this->assertModelMissing($team);
    }

    /**
     * Test an authenticated team can visit the team resource view page
     */
    public function test_an_authenticated_team_can_render_the_team_resource_view_page(): void
    {
        $this->get(teamResource::getUrl('view', [
            'record' => Team::factory()->create(),
        ]))->assertSuccessful();
    }
}

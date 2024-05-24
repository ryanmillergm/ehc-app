<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\TeamResource\Pages\EditTeam;
use App\Filament\Resources\UserResource;
use App\Filament\Resources\UserResource\Pages\CreateUser;
use App\Filament\Resources\UserResource\Pages\EditUser;
use App\Filament\Resources\UserResource\Pages\ListUsers;
use App\Filament\Resources\UserResource\RelationManagers\OwnedTeamsRelationManager;
use App\Models\Team;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Livewire\Livewire;
use Tests\TestCase;

class UserResourceTest extends TestCase
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
        $this->get(UserResource::getUrl('index'))->assertSuccessful();
    }

    /**
     * Test an authenticated user can visit the user resource table builder list page in the filament admin panel.
     */
    public function test_an_authenticated_user_can_render_the_user_resource_table_page(): void
    {
        livewire::test(ListUsers::class)->assertSuccessful();
    }

    /**
     * Test an authenticated user can visit the user resource table builder list page and see a list of users.
     */
    public function test_user_resource_page_can_list_users(): void
    {
        User::factory()->count(9)->create();
        $users = User::all();


        livewire::test(ListUsers::class)
        ->assertCountTableRecords(10)
        ->assertCanSeeTableRecords($users);
    }

    /**
     * Test an authenticated user visit the create a user resource page
     */
    public function test_auth_user_visit_create_user_resource_page(): void
    {
        $this->get(UserResource::getUrl('create'))->assertSuccessful();
    }

    /**
     * Test an authenticated user create a user resource
     */
    public function test_auth_user_can_create_user(): void
    {
        $newData = User::factory()->make();

        livewire::test(CreateUser::class)
            ->fillForm([
                'first_name' => $newData->first_name,
                'last_name' => $newData->last_name,
                'email' => $newData->email,
                'password' => 'password',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas(User::class, [
            'first_name' => $newData->first_name,
            'last_name' => $newData->last_name,
            'email' => $newData->email,
        ]);
    }

    /**
     * Test validation - User requires first name
     */
    public function test_create_user_requires_first_name(): void
    {
        $newData = User::factory()->make();

        livewire::test(CreateUser::class)
            ->fillForm([
                'first_name' => null,
                'last_name' => $newData->last_name,
                'email' => $newData->email,
                'password' => 'password',
            ])
            ->call('create')
            ->assertHasFormErrors(['first_name' => 'required']);

        $this->assertDatabaseMissing(User::class, [
            'first_name' => $newData->first_name,
            'last_name' => $newData->last_name,
            'email' => $newData->email,
        ]);
    }

    /**
     * Test validation - User requires last name
     */
    public function test_create_user_requires_last_name(): void
    {
        $newData = User::factory()->make();

        livewire::test(CreateUser::class)
            ->fillForm([
                'first_name' => $newData->first_name,
                'last_name' => null,
                'email' => $newData->email,
                'password' => 'password',
            ])
            ->call('create')
            ->assertHasFormErrors(['last_name' => 'required']);

        $this->assertDatabaseMissing(User::class, [
            'first_name' => $newData->first_name,
            'last_name' => $newData->last_name,
            'email' => $newData->email,
        ]);
    }

    /**
     * Test validation - User requires email
     */
    public function test_create_user_requires_email(): void
    {
        $newData = User::factory()->make();

        livewire::test(CreateUser::class)
            ->fillForm([
                'first_name' => $newData->first_name,
                'last_name' => $newData->last_name,
                'email' => null,
                'password' => 'password',
            ])
            ->call('create')
            ->assertHasFormErrors(['email' => 'required']);

        $this->assertDatabaseMissing(User::class, [
            'first_name' => $newData->first_name,
            'last_name' => $newData->last_name,
            'email' => $newData->email,
        ]);
    }

    /**
     * Test validation - User requires password
     */
    public function test_create_user_requires_password(): void
    {
        $newData = User::factory()->make();

        livewire::test(CreateUser::class)
            ->fillForm([
                'first_name' => $newData->first_name,
                'last_name' => $newData->last_name,
                'email' => $newData->email,
                'password' => null,
            ])
            ->call('create')
            ->assertHasFormErrors(['password' => 'required']);

        $this->assertDatabaseMissing(User::class, [
            'first_name' => $newData->first_name,
            'last_name' => $newData->last_name,
            'email' => $newData->email,
        ]);
    }

    /**
     * Test an authenticated user can visit the user resource edit page
     */
    public function test_auth_user_can_visit_user_resource_edit_page(): void
    {
        $this->get(UserResource::getUrl('edit', [
            'record' => User::factory()->create(),
        ]))->assertSuccessful();
    }

    /**
     * Test user resource edit form retrieves correct data
     */
    public function test_user_resource_edit_form_retrieves_correct_data(): void
    {
        $user = User::factory()->create();

        livewire::test(EditUser::class, [
            'record' => $user->getRouteKey(),
        ])
            ->assertFormSet([
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'email' => $user->email,
            ]);
    }

    /**
     * Test user resource edit form saves correct data
     */
    public function test_user_resource_edit_form_saves_correct_data(): void
    {
        $user = User::factory()->create();
        $newData = User::factory()->make();

        livewire::test(EditUser::class, [
            'record' => $user->getRouteKey(),
        ])
            ->fillForm([
                'first_name' => $newData->first_name,
                'last_name' => $newData->last_name,
                'email' => $newData->email,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $user->refresh();

        $this->assertEquals($user->first_name, $newData->first_name);
        $this->assertEquals($user->last_name, $newData->last_name);
        $this->assertEquals($user->email, $newData->email);
    }

    /**
     * Test user resource edit form requires first name
     */
    public function test_user_resource_edit_form_requires_first_name_data(): void
    {
        $user = User::factory()->create();

        livewire::test(EditUser::class, [
            'record' => $user->getRouteKey(),
        ])
            ->fillForm([
                'first_name' => null,
            ])
            ->call('save')
            ->assertHasFormErrors(['first_name' => 'required']);
    }

    /**
     * Test user resource edit form requires last name
     */
    public function test_user_resource_edit_form_requires_last_name_data(): void
    {
        $user = User::factory()->create();

        livewire::test(EditUser::class, [
            'record' => $user->getRouteKey(),
        ])
            ->fillForm([
                'last_name' => null,
            ])
            ->call('save')
            ->assertHasFormErrors(['last_name' => 'required']);
    }

    /**
     * Test user resource edit form requires email
     */
    public function test_user_resource_edit_form_requires_email_data(): void
    {
        $user = User::factory()->create();

        livewire::test(EditUser::class, [
            'record' => $user->getRouteKey(),
        ])
            ->fillForm([
                'email' => null,
            ])
            ->call('save')
            ->assertHasFormErrors(['email' => 'required']);
    }

    /**
     * Test authenticated user can delete a user
     */
    public function test_auth_user_can_delete_a_user(): void
    {
        $user = User::factory()->create();

        livewire::test(EditUser::class, [
            'record' => $user->getRouteKey(),
        ])
        ->callAction(DeleteAction::class);

        $this->assertModelMissing($user);
    }

    /**
     * Test an authenticated user can visit the user resource view page
     */
    public function test_an_authenticated_user_can_render_the_user_resource_view_page(): void
    {
        $this->get(UserResource::getUrl('view', [
            'record' => User::factory()->create(),
        ]))->assertSuccessful();
    }

    /**
     * Test user resource renders relation manager successfully
     */
    public function test_user_resource_renders_relation_manager_successfully(): void
    {
        $user = User::factory()
            ->has(Team::factory()->count(1), 'ownedTeams')
            ->create();

        livewire::test(OwnedTeamsRelationManager::class, [
            'ownerRecord' => $user,
            'pageClass' => EditTeam::class,
        ])
            ->assertSuccessful();
    }

    /**
     * Test user resource lists teams relation manager successfully
     */
    public function test_user_resource_lists_teams(): void
    {
        $user = User::factory()
            ->has(Team::factory()->count(1), 'ownedTeams')
            ->create();

        livewire::test(OwnedTeamsRelationManager::class, [
            'ownerRecord' => $user,
            'pageClass' => EditTeam::class,
        ])
            ->assertCanSeeTableRecords($user->ownedTeams);
    }
}

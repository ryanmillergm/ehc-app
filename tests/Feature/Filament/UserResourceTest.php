<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\UserResource;
use App\Filament\Resources\UserResource\Pages\CreateUser;
use App\Filament\Resources\UserResource\Pages\EditUser;
use App\Filament\Resources\UserResource\Pages\ListUsers;
use App\Filament\Resources\UserResource\RelationManagers\OwnedTeamsRelationManager;
use App\Models\Team;
use App\Models\User;
use Filament\Actions\DeleteAction as PageDeleteAction;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
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

        $this->seed('PermissionSeeder');

        // If you have multiple panels, ensure the correct panel is set.
        // Change 'admin' if your panel ID differs.
        Filament::setCurrentPanel('admin');

        $this->signIn();
    }

    public function test_an_authenticated_user_without_permissions_cannot_render_the_user_resource_page(): void
    {
        $this->get(UserResource::getUrl('index'))->assertStatus(403);

        $this->signInWithPermissions(null, ['teams.read', 'admin.panel']);

        $this->get(UserResource::getUrl('index'))->assertStatus(403);
    }

    public function test_an_authenticated_user_with_permissions_can_render_the_user_resource_page(): void
    {
        $this->signInWithPermissions(null, ['users.read', 'admin.panel']);

        $this->get(UserResource::getUrl('index'))->assertSuccessful();
    }

    public function test_an_authenticated_user_with_permissions_can_render_the_user_resource_table_page(): void
    {
        $this->signInWithPermissions(null, ['users.read', 'users.create', 'users.update', 'users.delete', 'admin.panel']);

        Livewire::test(ListUsers::class)->assertOk();
    }

    public function test_user_resource_page_can_list_users(): void
    {
        $this->signInWithPermissions(null, ['users.read', 'users.create', 'users.update', 'users.delete', 'admin.panel']);

        User::factory()->count(7)->create();
        $users = User::all();

        Livewire::test(ListUsers::class)
            ->assertCountTableRecords(9)
            ->assertCanSeeTableRecords($users);
    }

    public function test_auth_user_visit_create_user_resource_page(): void
    {
        $this->signInWithPermissions(null, ['users.read', 'users.create', 'users.update', 'users.delete', 'admin.panel']);

        $this->get(UserResource::getUrl('create'))->assertSuccessful();
    }

    public function test_auth_user_can_create_user(): void
    {
        $this->signInWithPermissions(null, ['users.read', 'users.create', 'users.update', 'users.delete', 'admin.panel']);

        $newData = User::factory()->make();

        Livewire::test(CreateUser::class)
            ->fillForm([
                'first_name' => $newData->first_name,
                'last_name'  => $newData->last_name,
                'email'      => $newData->email,
                'password'   => 'password',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas(User::class, [
            'first_name' => $newData->first_name,
            'last_name'  => $newData->last_name,
            'email'      => $newData->email,
        ]);
    }

    public function test_create_user_requires_first_name(): void
    {
        $this->signInWithPermissions(null, ['users.read', 'users.create', 'users.update', 'users.delete', 'admin.panel']);

        $newData = User::factory()->make();

        Livewire::test(CreateUser::class)
            ->fillForm([
                'first_name' => null,
                'last_name'  => $newData->last_name,
                'email'      => $newData->email,
                'password'   => 'password',
            ])
            ->call('create')
            ->assertHasFormErrors(['first_name' => 'required']);

        $this->assertDatabaseMissing(User::class, [
            'first_name' => $newData->first_name,
            'last_name'  => $newData->last_name,
            'email'      => $newData->email,
        ]);
    }

    public function test_create_user_requires_last_name(): void
    {
        $this->signInWithPermissions(null, ['users.read', 'users.create', 'users.update', 'users.delete', 'admin.panel']);

        $newData = User::factory()->make();

        Livewire::test(CreateUser::class)
            ->fillForm([
                'first_name' => $newData->first_name,
                'last_name'  => null,
                'email'      => $newData->email,
                'password'   => 'password',
            ])
            ->call('create')
            ->assertHasFormErrors(['last_name' => 'required']);

        $this->assertDatabaseMissing(User::class, [
            'first_name' => $newData->first_name,
            'last_name'  => $newData->last_name,
            'email'      => $newData->email,
        ]);
    }

    public function test_create_user_requires_email(): void
    {
        $this->signInWithPermissions(null, ['users.read', 'users.create', 'users.update', 'users.delete', 'admin.panel']);

        $newData = User::factory()->make();

        Livewire::test(CreateUser::class)
            ->fillForm([
                'first_name' => $newData->first_name,
                'last_name'  => $newData->last_name,
                'email'      => null,
                'password'   => 'password',
            ])
            ->call('create')
            ->assertHasFormErrors(['email' => 'required']);

        $this->assertDatabaseMissing(User::class, [
            'first_name' => $newData->first_name,
            'last_name'  => $newData->last_name,
            'email'      => $newData->email,
        ]);
    }

    public function test_create_user_requires_password(): void
    {
        $this->signInWithPermissions(null, ['users.read', 'users.create', 'users.update', 'users.delete', 'admin.panel']);

        $newData = User::factory()->make();

        Livewire::test(CreateUser::class)
            ->fillForm([
                'first_name' => $newData->first_name,
                'last_name'  => $newData->last_name,
                'email'      => $newData->email,
                'password'   => null,
            ])
            ->call('create')
            ->assertHasFormErrors(['password' => 'required']);

        $this->assertDatabaseMissing(User::class, [
            'first_name' => $newData->first_name,
            'last_name'  => $newData->last_name,
            'email'      => $newData->email,
        ]);
    }

    public function test_auth_user_can_visit_user_resource_edit_page(): void
    {
        $this->signInWithPermissions(null, ['users.read', 'users.create', 'users.update', 'users.delete', 'admin.panel']);

        $this->get(UserResource::getUrl('edit', [
            'record' => User::factory()->create(),
        ]))->assertSuccessful();
    }

    public function test_user_resource_edit_form_retrieves_correct_data(): void
    {
        $this->signInWithPermissions(null, ['users.read', 'users.create', 'users.update', 'users.delete', 'admin.panel']);

        $user = User::factory()->create();

        Livewire::test(EditUser::class, [
            'record' => $user->getRouteKey(),
        ])
            ->assertOk()
            ->assertSchemaStateSet([
                'first_name' => $user->first_name,
                'last_name'  => $user->last_name,
                'email'      => $user->email,
            ]);
    }

    public function test_user_resource_edit_form_saves_correct_data(): void
    {
        $this->signInWithPermissions(null, ['users.read', 'users.create', 'users.update', 'users.delete', 'admin.panel']);

        $user    = User::factory()->create();
        $newData = User::factory()->make();

        Livewire::test(EditUser::class, [
            'record' => $user->getRouteKey(),
        ])
            ->fillForm([
                'first_name' => $newData->first_name,
                'last_name'  => $newData->last_name,
                'email'      => $newData->email,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $user->refresh();

        $this->assertEquals($newData->first_name, $user->first_name);
        $this->assertEquals($newData->last_name, $user->last_name);
        $this->assertEquals($newData->email, $user->email);
    }

    public function test_user_resource_edit_form_requires_first_name_data(): void
    {
        $this->signInWithPermissions(null, ['users.read', 'users.create', 'users.update', 'users.delete', 'admin.panel']);

        $user = User::factory()->create();

        Livewire::test(EditUser::class, [
            'record' => $user->getRouteKey(),
        ])
            ->fillForm(['first_name' => null])
            ->call('save')
            ->assertHasFormErrors(['first_name' => 'required']);
    }

    public function test_user_resource_edit_form_requires_last_name_data(): void
    {
        $this->signInWithPermissions(null, ['users.read', 'users.create', 'users.update', 'users.delete', 'admin.panel']);

        $user = User::factory()->create();

        Livewire::test(EditUser::class, [
            'record' => $user->getRouteKey(),
        ])
            ->fillForm(['last_name' => null])
            ->call('save')
            ->assertHasFormErrors(['last_name' => 'required']);
    }

    public function test_user_resource_edit_form_requires_email_data(): void
    {
        $this->signInWithPermissions(null, ['users.read', 'users.create', 'users.update', 'users.delete', 'admin.panel']);

        $user = User::factory()->create();

        Livewire::test(EditUser::class, [
            'record' => $user->getRouteKey(),
        ])
            ->fillForm(['email' => null])
            ->call('save')
            ->assertHasFormErrors(['email' => 'required']);
    }

    public function test_auth_user_can_delete_a_user(): void
    {
        $this->signInWithPermissions(null, ['users.read', 'users.create', 'users.update', 'users.delete', 'admin.panel']);

        $user = User::factory()->create();

        Livewire::test(EditUser::class, [
            'record' => $user->getRouteKey(),
        ])->callAction(PageDeleteAction::class);

        $this->assertModelMissing($user);
    }

    public function test_auth_user_can_delete_a_user2(): void
    {
        $this->signInWithPermissions(null, ['users.read', 'users.create', 'users.update', 'users.delete', 'admin.panel']);

        $user = User::factory()->create();

        Livewire::test(ListUsers::class)
            ->callAction(TestAction::make('delete')->table($user));

        $this->assertModelMissing($user);
    }

    public function test_auth_user_without_permissions_cannot_delete_a_user(): void
    {
        $this->signInWithPermissions(null, ['users.read', 'users.create', 'users.update', 'admin.panel']);

        $user = User::factory()->create();

        Livewire::test(ListUsers::class)
            ->assertActionVisible(TestAction::make('view')->table($user))
            ->assertActionVisible(TestAction::make('edit')->table($user))

            // The action still exists, but authorization hides it.
            ->assertActionHidden(TestAction::make('delete')->table($user))

            // Bulk delete varies by config: sometimes hidden, sometimes disabled.
            ->assertActionExists(
                TestAction::make('delete')->table()->bulk(),
                fn ($action) => $action->isHidden() || $action->isDisabled()
            );
    }

    public function test_auth_user_without_permissions_cannot_edit_a_user(): void
    {
        $this->signInWithPermissions(null, ['users.read', 'users.create', 'admin.panel']);

        $user = User::factory()->create();

        Livewire::test(ListUsers::class)
            ->assertActionVisible(TestAction::make('view')->table($user))

            // Same story: these actions still exist, but are hidden by authorization.
            ->assertActionHidden(TestAction::make('edit')->table($user))
            ->assertActionHidden(TestAction::make('delete')->table($user));
    }

    public function test_an_authenticated_user_with_permissions_can_render_the_user_resource_view_page(): void
    {
        $this->signInWithPermissions(null, ['users.read', 'admin.panel']);

        $this->get(UserResource::getUrl('view', [
            'record' => User::factory()->create(),
        ]))->assertSuccessful();
    }

    public function test_user_resource_renders_relation_manager_successfully(): void
    {
        $user = User::factory()
            ->has(Team::factory()->count(1), 'ownedTeams')
            ->create();

        Livewire::test(OwnedTeamsRelationManager::class, [
            'ownerRecord' => $user,
            'pageClass'   => EditUser::class,
        ])->assertOk();
    }

    public function test_user_resource_lists_teams(): void
    {
        $user = User::factory()
            ->has(Team::factory()->count(1), 'ownedTeams')
            ->create();

        Livewire::test(OwnedTeamsRelationManager::class, [
            'ownerRecord' => $user,
            'pageClass'   => EditUser::class,
        ])->assertCanSeeTableRecords($user->ownedTeams);
    }
}

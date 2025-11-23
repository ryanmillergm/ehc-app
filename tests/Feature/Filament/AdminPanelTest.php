<?php

namespace Tests\Feature\Filament;

use Tests\TestCase;
use App\Models\User;
use Spatie\Permission\PermissionRegistrar;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;

class AdminPanelTest extends TestCase
{
    use WithFaker, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed('PermissionSeeder');

        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Unauthenticated users cannot visit admin page.
     */
    public function test_unauthenticated_users_cannot_visit_admin_page(): void
    {
        $response = $this->get('/admin');

        $response->assertStatus(302);
        $response->assertRedirect('/admin/login');
    }

    /**
     * An authenticated user that doesn't have permissions cannot visit admin page.
     */
    public function test_an_authenticated_user_without_permissions_cannot_can_visit_admin_page(): void
    {
        $user = User::factory()->create();
        $this->signIn($user);

        $response = $this->get('/admin');

        $response->assertStatus(403);
    }

    /**
     * An authenticated user with Super Admin role can visit admin page.
     */
public function test_an_authenticated_user_with_permissions_can_visit_admin_page(): void
{
    // $this->withoutExceptionHandling();

    $user = User::factory()->create();
    $this->signInWithPermissions($user, ['admin.panel']);

    $this->get('/admin')->assertOk();
}

    /**
     * An authenticated user with Super Admin role can visit admin page.
     */
    public function test_an_authenticated_user_with_super_admin_role_can_visit_admin_page(): void
    {
        $user = User::factory()->create();
        $this->signInAsSuperAdmin($user);

        $response = $this->get('/admin');

        $response->assertStatus(200);
    }


}

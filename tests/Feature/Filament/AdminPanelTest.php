<?php

namespace Tests\Feature\Filament;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class AdminPanelTest extends TestCase
{
    use WithFaker, RefreshDatabase;

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
     * A basic feature test example.
     */
    public function test_an_authenticated_user_with_permissions_can_visit_admin_page(): void
    {
        $user = User::factory()->create();
        $this->signInAsSuperAdmin($user);

        $response = $this->get('/admin');

        $response->assertStatus(200);
    }
}

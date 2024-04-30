<?php

namespace Tests\Feature\Filament;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class AdminPanelTest extends TestCase
{
    /**
     * A basic feature test example.
     */
    public function test_unauthenticated_users_cannot_visit_admin_page(): void
    {
        $response = $this->get('/admin');

        $response->assertStatus(302);
        $response->assertRedirect('/admin/login');
    }

    /**
     * A basic feature test example.
     */
    public function test_an_authenticated_user_can_visit_admin_page(): void
    {
        $user = User::factory()->create();
        $this->signIn($user);

        $response = $this->get('/admin');

        $response->assertStatus(200);
    }
}

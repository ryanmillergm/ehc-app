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
    public function test_example(): void
    {
        $user = User::factory()->create();
        $this->signIn($user);
        
        $response = $this->get('/admin');

        $response->assertStatus(302);
    }
}

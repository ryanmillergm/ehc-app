<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Fortify\Features;
use Laravel\Jetstream\Jetstream;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered(): void
    {
        if (! Features::enabled(Features::registration())) {
            $this->markTestSkipped('Registration support is not enabled.');
        }

        $response = $this->get('/register');

        $response->assertStatus(200);
    }

    public function test_registration_screen_cannot_be_rendered_if_support_is_disabled(): void
    {
        if (Features::enabled(Features::registration())) {
            $this->markTestSkipped('Registration support is enabled.');
        }

        $response = $this->get('/register');

        $response->assertStatus(404);
    }

public function test_new_users_can_register(): void
{
    if (! Features::enabled(Features::registration())) {
        $this->markTestSkipped('Registration support is not enabled.');
    }

    config()->set('services.turnstile.secret', 'test-secret');

    Http::fake([
        'https://challenges.cloudflare.com/turnstile/v0/siteverify' => Http::response([
            'success' => true,
        ], 200),
    ]);

    $email = 'test@gmail.com';

    $response = $this->from('/register')->post('/register', [
        'first_name' => 'Test',
        'last_name'  => 'User',

        'email' => $email,
        'email_confirmation' => $email,

        'password' => 'password',
        'password_confirmation' => 'password',

        ...(Jetstream::hasTermsAndPrivacyPolicyFeature() ? ['terms' => 'on'] : []),

        'cf-turnstile-response' => 'test-token',
    ]);

    $response->assertSessionHasNoErrors();

    $this->assertDatabaseHas('users', ['email' => $email]);
    $this->assertAuthenticated();

    $user = User::where('email', $email)->firstOrFail();
    $this->assertNull($user->email_verified_at);

    $response->assertRedirect(config('fortify.home', '/'));

    // But verified routes should redirect to the verification notice:
    if (Features::enabled(Features::emailVerification())) {
        $this->get('/dashboard')->assertRedirect('/email/verify');
    }
}

}

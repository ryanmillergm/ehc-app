<?php

namespace Tests\Feature\Auth;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class RegisterRateLimitTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_returns_429_after_too_many_register_attempts(): void
    {
        // Ensure Turnstile verification is "successful" if your CreateNewUser ever calls it.
        Http::fake([
            'https://challenges.cloudflare.com/turnstile/v0/siteverify' => Http::response(['success' => true], 200),
        ]);

        // Make sure Turnstile "secret" exists so your code doesn't fail-closed for misconfig.
        config([
            'services.turnstile.secret' => 'test-secret',
            'services.turnstile.key' => 'test-site-key',
        ]);

        // Ensure Fortify is using the limiter name we expect (if you’re using Option A).
        config([
            'fortify.limiters.register' => 'register',
        ]);

        // Define the limiter in-test (so the test is self-contained and doesn't depend on provider wiring).
        // Use IP-only key so changing emails doesn't bypass the throttle.
        RateLimiter::for('register', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip() ?? 'ip:unknown');
        });

        $ip = '203.0.113.10'; // TEST-NET-3, safe for docs/tests
        $url = '/register';

        // Intentionally fail validation (password confirmation mismatch) so we stay "guest"
        // while still exercising the throttle middleware.
        $payload = [
            'first_name' => 'Ryan',
            'last_name' => 'M',
            'email' => 'rate-limit@example.com',
            'password' => 'Password123!',              // valid-ish
            'password_confirmation' => 'Different!',   // force validation fail
            'terms' => 'on',
            'cf-turnstile-response' => 'fake-token',
        ];

        // First 5 attempts: should NOT be rate-limited (typically 302 redirect back with errors)
        for ($i = 1; $i <= 5; $i++) {
            $response = $this
                ->withServerVariables(['REMOTE_ADDR' => $ip])
                ->from('/register')
                ->post($url, $payload);

            // We accept either a redirect (common for failed validation)
            // or a 422 (if your app/API responds with JSON validation errors).
            $this->assertTrue(
                in_array($response->getStatusCode(), [302, 422], true),
                "Attempt {$i} unexpectedly returned {$response->getStatusCode()}."
            );
        }

        // 6th attempt: should be throttled.
        $response = $this
            ->withServerVariables(['REMOTE_ADDR' => $ip])
            ->from('/register')
            ->post($url, $payload);

        $response->assertStatus(429);
    }
}

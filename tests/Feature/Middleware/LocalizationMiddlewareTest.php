<?php

namespace Tests\Feature\Middleware;

use App\Http\Middleware\Localization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LocalizationMiddlewareTest extends TestCase
{
    use WithFaker, RefreshDatabase;
    
    /**
     * Test that the Localization method sets the locale on any route.
     */
    public function test_locale_is_set_to_default_if_no_session_exists_or_value_given_when_visiting_any_page(): void
    {
        // Define a sample route to apply middleware for the test
        $this->withoutExceptionHandling();

        // Mocking a request that passes through middleware
        $response = $this->withSession([]) // starting with an empty session
            ->get('/');

        // Check the session for the expected key and value
        $this->assertEquals('en', session('locale'));
        $this->assertNotEquals('es', session('locale'));

        // Or check if the key exists if the value is dynamic
        $this->assertTrue(session()->has('locale'));
    }

    /**
     * Test that the Localization method sets the locale on any route.
     */
    public function test_locale_is_not_changed_if_locale_exists_in_session_when_visiting_any_page(): void
    {
        // Define a sample route to apply middleware for the test
        $this->withoutExceptionHandling();

        // Mocking a request that passes through middleware
        $response = $this->withSession(['locale' => 'es']) // starting with an empty session
            ->get('/');

        // Check the session for the expected key and value
        $this->assertEquals('es', session('locale'));
        $this->assertNotEquals('en', session('locale'));

        // Or check if the key exists if the value is dynamic
        $this->assertTrue(session()->has('locale'));
    }
}

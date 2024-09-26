<?php

namespace Tests\Feature\Languages;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class SwitchLanguagesTest extends TestCase
{
    /**
     * Test the locale/language can be switched
     */
    public function test_the_locale_language_can_be_switched(): void
    {
         // Mocking a request that passes through middleware
         $response = $this->withSession([]) // starting with an empty session
         ->get('/');

        // Check the session for the expected key and value
        $this->assertEquals('en', session('locale'));
        $this->assertNotEquals('es', session('locale'));


        // Switch language from english to spanish
        $this->get('/lang/es');

        $this->assertEquals('es', session('locale'));
        $this->assertNotEquals('en', session('locale'));


        // Switch language from spanish to french
        $this->get('/lang/fr');

        $this->assertEquals('fr', session('locale'));
        $this->assertNotEquals('es', session('locale'));
    }
}

<?php

namespace Tests\Feature\Languages;

use App\Models\Language;
use Database\Seeders\LanguageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class LanguageSwitchTest extends TestCase
{
    use WithFaker, RefreshDatabase;

    /**
     * Test the locale/language can be switched
     */
    public function test_the_locale_language_can_be_switched(): void
    {
        $this->withoutExceptionHandling();

        $this->seed(LanguageSeeder::class);

        Language::create([
            'title'         => 'french',
            'iso_code'      => 'fr',
            'locale'        => 'fr',
            'right_to_left' => false,
        ]);

        $english_language = Language::where('iso_code', 'en')->first();
        $spanish_language = Language::where('iso_code', 'es')->first();
        $french_language = Language::where('iso_code', 'fr')->first();

         // Mocking a request that passes through middleware
         $response = $this->withSession([]) // starting with an empty session
         ->get('/');

        // Check the session for the expected key and value
        $this->assertEquals('en', session('locale'));
        $this->assertEquals($english_language->id, session('language_id'));
        $this->assertNotEquals('es', session('locale'));

        // Switch language from english to spanish
        $this->get('/lang/es');

        $this->assertEquals('es', session('locale'));
        $this->assertEquals($spanish_language->id, session('language_id'));
        $this->assertNotEquals('en', session('locale'));


        // Switch language from spanish to french
        $this->get('/lang/fr');

        $this->assertEquals('fr', session('locale'));
        $this->assertEquals($french_language->id, session('language_id'));
        $this->assertNotEquals('es', session('locale'));
    }
}

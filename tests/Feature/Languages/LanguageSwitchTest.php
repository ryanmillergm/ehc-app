<?php

namespace Tests\Feature\Languages;

use App\Models\Language;
use Database\Seeders\LanguageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LanguageSwitchTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_switches_locale_and_language_id_and_sets_flash_banner_on_redirect(): void
    {
        $this->seed(LanguageSeeder::class);

        Language::create([
            'title'         => 'french',
            'name'          => 'Français',
            'iso_code'      => 'fr',
            'locale'        => 'fr',
            'right_to_left' => false,
        ]);

        $english = Language::where('iso_code', 'en')->firstOrFail();
        $spanish = Language::where('iso_code', 'es')->firstOrFail();
        $french  = Language::where('iso_code', 'fr')->firstOrFail();

        // Hit home so Localization middleware sets defaults
        $this->withSession([])->get('/')->assertOk();

        $this->assertSame('en', session('locale'));
        $this->assertSame($english->id, session('language_id'));

        // Switch to Spanish (redirect response)
        $res = $this->get('/lang/es');

        $res->assertRedirect(); // back() or / depending on previousUrl
        $this->assertSame('es', session('locale'));
        $this->assertSame($spanish->id, session('language_id'));

        // Flash banner is set
        $this->assertSame('success', session('flash.bannerStyle'));
        $this->assertNotEmpty(session('flash.banner'));
        $this->assertStringContainsString($spanish->name, session('flash.banner'));

        // Switch to French (redirect response)
        $res = $this->get('/lang/fr');

        $res->assertRedirect();
        $this->assertSame('fr', session('locale'));
        $this->assertSame($french->id, session('language_id'));

        $this->assertSame('success', session('flash.bannerStyle'));
        $this->assertNotEmpty(session('flash.banner'));
        $this->assertStringContainsString($french->name, session('flash.banner'));
    }

    #[Test]
    public function it_returns_json_message_for_ajax_requests(): void
    {
        $this->seed(LanguageSeeder::class);

        // Ensure Spanish has a "name" to inject
        $spanish = Language::where('iso_code', 'es')->firstOrFail();
        if (! $spanish->name) {
            $spanish->update(['name' => 'Español']);
            $spanish->refresh();
        }

        $res = $this->get('/lang/es', [
            'X-Requested-With' => 'XMLHttpRequest',
            'Accept'           => 'application/json',
        ]);

        $res->assertOk()
            ->assertJson([
                'style'  => 'success',
                'locale' => 'es',
            ]);

        $this->assertSame('es', session('locale'));
        $this->assertSame($spanish->id, session('language_id'));

        $json = $res->json();
        $this->assertIsString($json['message'] ?? null);
        $this->assertStringContainsString($spanish->name, $json['message']);
    }
}

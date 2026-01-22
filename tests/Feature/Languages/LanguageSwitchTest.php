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

    private function label(Language $lang): string
    {
        // Use the most human-friendly non-null value.
        return (string) ($lang->name ?: $lang->title ?: $lang->iso_code);
    }

    #[Test]
    public function it_switches_locale_and_language_id_and_sets_flash_banner_on_redirect(): void
    {
        $this->seed(LanguageSeeder::class);

        $english = Language::query()->updateOrCreate(
            ['iso_code' => 'en'],
            [
                'title'         => 'English',
                'name'          => 'English',
                'iso_code'      => 'en',
                'locale'        => 'en',
                'right_to_left' => false,
            ]
        )->refresh();

        $spanish = Language::query()->updateOrCreate(
            ['iso_code' => 'es'],
            [
                'title'         => 'Spanish',
                'name'          => 'Español',
                'iso_code'      => 'es',
                'locale'        => 'es',
                'right_to_left' => false,
            ]
        )->refresh();

        $french = Language::query()->updateOrCreate(
            ['iso_code' => 'fr'],
            [
                'title'         => 'French',
                'name'          => 'Français',
                'iso_code'      => 'fr',
                'locale'        => 'fr',
                'right_to_left' => false,
            ]
        )->refresh();

        // Optional extra language
        Language::query()->updateOrCreate(
            ['iso_code' => 'ar'],
            [
                'title'         => 'Arabic',
                'name'          => 'عربي',
                'iso_code'      => 'ar',
                'locale'        => 'ar',
                'right_to_left' => true,
            ]
        );

        // Hit home so Localization middleware sets defaults (if that's how your app works)
        $this->withSession([])->get('/')->assertOk();

        // Defaults should be English
        $this->assertSame('en', session('locale'));
        $this->assertSame($english->id, session('language_id'));

        // Switch to Spanish
        $res = $this->get('/lang/es');
        $res->assertRedirect();

        $this->assertSame('es', session('locale'));
        $this->assertSame($spanish->id, session('language_id'));

        $this->assertSame('success', session('flash.bannerStyle'));
        $this->assertNotEmpty(session('flash.banner'));
        $this->assertStringContainsString(
            $this->label($spanish),
            (string) session('flash.banner')
        );

        // Switch to French
        $res = $this->get('/lang/fr');
        $res->assertRedirect();

        $this->assertSame('fr', session('locale'));
        $this->assertSame($french->id, session('language_id'));

        $this->assertSame('success', session('flash.bannerStyle'));
        $this->assertNotEmpty(session('flash.banner'));
        $this->assertStringContainsString(
            $this->label($french),
            (string) session('flash.banner')
        );
    }

    #[Test]
    public function it_returns_json_message_for_ajax_requests(): void
    {
        $this->seed(LanguageSeeder::class);

        $spanish = Language::query()->updateOrCreate(
            ['iso_code' => 'es'],
            [
                'title'         => 'Spanish',
                'name'          => 'Español',
                'iso_code'      => 'es',
                'locale'        => 'es',
                'right_to_left' => false,
            ]
        )->refresh();

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
        $this->assertStringContainsString(
            $this->label($spanish),
            (string) ($json['message'] ?? '')
        );
    }
}

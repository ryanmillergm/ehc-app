<?php

namespace Tests\Feature\Seo;

use App\Models\Language;
use App\Models\RouteSeo;
use App\Services\Seo\RouteSeoResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RouteSeoResolverTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_prefers_current_language_route_seo_row_when_available(): void
    {
        $en = Language::factory()->english()->create();
        $es = Language::factory()->spanish()->create();

        RouteSeo::factory()->create([
            'route_key' => RouteSeo::ROUTE_DONATIONS_SHOW,
            'language_id' => $en->id,
            'seo_title' => 'Give EN',
            'seo_description' => 'Give EN Description',
            'canonical_path' => '/give',
            'is_active' => true,
        ]);

        RouteSeo::factory()->create([
            'route_key' => RouteSeo::ROUTE_DONATIONS_SHOW,
            'language_id' => $es->id,
            'seo_title' => 'Dar ES',
            'seo_description' => 'Dar ES Description',
            'canonical_path' => '/give',
            'is_active' => true,
        ]);

        session(['language_id' => $es->id, 'locale' => 'es']);
        app()->setLocale('es');

        $resolved = app(RouteSeoResolver::class)->resolve(RouteSeo::ROUTE_DONATIONS_SHOW);

        $this->assertSame('Dar ES', $resolved['metaTitle']);
        $this->assertSame('Dar ES Description', $resolved['metaDescription']);
    }

    #[Test]
    public function it_falls_back_to_default_language_when_current_language_row_is_missing(): void
    {
        $en = Language::factory()->english()->create();
        $es = Language::factory()->spanish()->create();

        RouteSeo::factory()->create([
            'route_key' => RouteSeo::ROUTE_EMAILS_SUBSCRIBE,
            'language_id' => $en->id,
            'seo_title' => 'Subscribe EN',
            'seo_description' => 'Subscribe EN Description',
            'canonical_path' => '/emails/subscribe',
            'is_active' => true,
        ]);

        session(['language_id' => $es->id, 'locale' => 'es']);
        app()->setLocale('es');

        $resolved = app(RouteSeoResolver::class)->resolve(RouteSeo::ROUTE_EMAILS_SUBSCRIBE);

        $this->assertSame('Subscribe EN', $resolved['metaTitle']);
        $this->assertSame('Subscribe EN Description', $resolved['metaDescription']);
    }

    #[Test]
    public function give_route_renders_route_seo_metadata_from_database(): void
    {
        $en = Language::factory()->english()->create();

        RouteSeo::factory()->create([
            'route_key' => RouteSeo::ROUTE_DONATIONS_SHOW,
            'language_id' => $en->id,
            'seo_title' => 'Custom Give SEO',
            'seo_description' => 'Custom Give Description',
            'seo_og_image' => 'https://cdn.example.org/custom-give.jpg',
            'canonical_path' => '/give',
            'is_active' => true,
        ]);

        $this->withSession(['language_id' => $en->id, 'locale' => 'en'])
            ->get(route('donations.show'))
            ->assertOk()
            ->assertSee('<title>Custom Give SEO</title>', false)
            ->assertSee('<meta name="description" content="Custom Give Description">', false)
            ->assertSee('<meta property="og:image" content="https://cdn.example.org/custom-give.jpg">', false);
    }
}

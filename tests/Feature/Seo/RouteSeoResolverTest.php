<?php

namespace Tests\Feature\Seo;

use App\Models\Language;
use App\Models\SeoMeta;
use App\Services\Seo\RouteSeoResolver;
use App\Support\Seo\RouteSeoTarget;
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

        SeoMeta::factory()->routeTarget(RouteSeoTarget::DONATIONS_SHOW)->create([
            'language_id' => $en->id,
            'seo_title' => 'Give EN',
            'seo_description' => 'Give EN Description',
            'canonical_path' => '/give',
            'is_active' => true,
        ]);

        SeoMeta::factory()->routeTarget(RouteSeoTarget::DONATIONS_SHOW)->create([
            'language_id' => $es->id,
            'seo_title' => 'Dar ES',
            'seo_description' => 'Dar ES Description',
            'canonical_path' => '/give',
            'is_active' => true,
        ]);

        session(['language_id' => $es->id, 'locale' => 'es']);
        app()->setLocale('es');

        $resolved = app(RouteSeoResolver::class)->resolve(RouteSeoTarget::DONATIONS_SHOW);

        $this->assertSame('Dar ES', $resolved['metaTitle']);
        $this->assertSame('Dar ES Description', $resolved['metaDescription']);
    }

    #[Test]
    public function it_falls_back_to_default_language_when_current_language_row_is_missing(): void
    {
        $en = Language::factory()->english()->create();
        $es = Language::factory()->spanish()->create();

        SeoMeta::factory()->routeTarget(RouteSeoTarget::EMAILS_SUBSCRIBE)->create([
            'language_id' => $en->id,
            'seo_title' => 'Subscribe EN',
            'seo_description' => 'Subscribe EN Description',
            'canonical_path' => '/emails/subscribe',
            'is_active' => true,
        ]);

        session(['language_id' => $es->id, 'locale' => 'es']);
        app()->setLocale('es');

        $resolved = app(RouteSeoResolver::class)->resolve(RouteSeoTarget::EMAILS_SUBSCRIBE);

        $this->assertSame('Subscribe EN', $resolved['metaTitle']);
        $this->assertSame('Subscribe EN Description', $resolved['metaDescription']);
    }

    #[Test]
    public function give_route_renders_route_seo_metadata_from_database(): void
    {
        $en = Language::factory()->english()->create();

        SeoMeta::factory()->routeTarget(RouteSeoTarget::DONATIONS_SHOW)->create([
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

    #[Test]
    public function it_uses_route_defaults_when_no_db_row_exists(): void
    {
        Language::factory()->english()->create();

        $resolved = app(RouteSeoResolver::class)->resolve(RouteSeoTarget::PAGES_INDEX);

        $this->assertSame('Community Outreach Pages | Bread of Grace Ministries', $resolved['metaTitle']);
        $this->assertSame('Explore Bread of Grace Ministries pages on outreach, discipleship, and ways to serve and give in Sacramento.', $resolved['metaDescription']);
    }

    #[Test]
    public function it_uses_canonical_path_and_robots_from_database_when_present(): void
    {
        $en = Language::factory()->english()->create();

        SeoMeta::factory()->routeTarget(RouteSeoTarget::DONATIONS_SHOW)->create([
            'language_id' => $en->id,
            'canonical_path' => '/give-now',
            'robots' => 'noindex,follow',
            'is_active' => true,
        ]);

        $resolved = app(RouteSeoResolver::class)->resolve(RouteSeoTarget::DONATIONS_SHOW);

        $this->assertSame(url('/give-now'), $resolved['canonicalUrl']);
        $this->assertSame('noindex,follow', $resolved['metaRobots']);

        $this->withSession(['language_id' => $en->id, 'locale' => 'en'])
            ->get(route('donations.show'))
            ->assertOk()
            ->assertSee('<meta name="robots" content="noindex,follow">', false)
            ->assertSee('<link rel="canonical" href="' . url('/give-now') . '">', false);
    }
}

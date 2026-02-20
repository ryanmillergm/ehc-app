<?php

namespace Tests\Feature\Seo;

use App\Models\Language;
use App\Models\Page;
use App\Models\PageTranslation;
use App\Models\EmailSubscriber;
use App\Models\Pledge;
use App\Models\SeoMeta;
use App\Models\Transaction;
use App\Support\Seo\RouteSeoTarget;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SeoInfrastructureTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function home_page_renders_core_seo_meta_tags_and_cta_anchor(): void
    {
        $response = $this->get('/');
        $content = $response->getContent();

        $response->assertOk()
            ->assertSee('<meta name="description"', false)
            ->assertSee('<meta property="og:title"', false)
            ->assertSee('<meta name="twitter:card"', false)
            ->assertSee('<meta name="robots" content="index,follow">', false)
            ->assertSee('<link rel="canonical"', false)
            ->assertSee('type="application/ld+json"', false)
            ->assertSee('id="give-form"', false)
            ->assertSee('Frequently asked questions');

        $this->assertSame(1, substr_count((string) $content, '<link rel="canonical"'));
    }

    #[Test]
    public function sitemap_xml_lists_core_urls_and_only_active_page_translations(): void
    {
        $language = Language::factory()->english()->create();

        $activePage = Page::factory()->create(['is_active' => true]);
        $inactivePage = Page::factory()->create(['is_active' => false]);

        $active = PageTranslation::factory()
            ->forLanguage($language)
            ->forPage($activePage)
            ->state(['slug' => 'help-the-homeless-sacramento', 'is_active' => true])
            ->create();

        $inactiveTranslation = PageTranslation::factory()
            ->forLanguage($language)
            ->forPage($activePage)
            ->state(['slug' => 'inactive-translation', 'is_active' => false])
            ->create();

        $inactivePageTranslation = PageTranslation::factory()
            ->forLanguage($language)
            ->forPage($inactivePage)
            ->state(['slug' => 'inactive-page-translation', 'is_active' => true])
            ->create();

        $response = $this->get('/sitemap.xml');
        $xml = $response->getContent();

        $response->assertOk()
            ->assertHeader('Content-Type', 'application/xml')
            ->assertSee('<?xml version="1.0" encoding="UTF-8"?>', false)
            ->assertSee('<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">', false)
            ->assertSee(url('/'), false)
            ->assertSee(url('/give'), false)
            ->assertSee(url('/emails/subscribe'), false)
            ->assertSee(url('/pages'), false)
            ->assertSee(url('/pages/' . $active->slug), false);

        $this->assertStringNotContainsString(url('/pages/' . $inactiveTranslation->slug), (string) $xml);
        $this->assertStringNotContainsString(url('/pages/' . $inactivePageTranslation->slug), (string) $xml);
    }

    #[Test]
    public function robots_txt_exposes_sitemap_path(): void
    {
        $response = $this->get('/robots.txt');

        $response->assertOk()
            ->assertSee('User-agent: *')
            ->assertSee('Disallow: /')
            ->assertSee('Sitemap: ' . rtrim(config('app.url'), '/') . '/sitemap.xml');
    }

    #[Test]
    public function indexable_public_routes_render_indexable_robots_meta(): void
    {
        $this->get('/give')
            ->assertOk()
            ->assertSee('<meta name="robots" content="index,follow">', false)
            ->assertSee('<link rel="canonical" href="' . url('/give') . '">', false);

        $this->get('/emails/subscribe')
            ->assertOk()
            ->assertSee('<meta name="robots" content="index,follow">', false)
            ->assertSee('<link rel="canonical" href="' . url('/emails/subscribe') . '">', false);
    }

    #[Test]
    public function tokenized_and_thank_you_routes_render_noindex_robots_meta(): void
    {
        $subscriber = EmailSubscriber::create([
            'email' => 'test@example.com',
            'unsubscribe_token' => str_repeat('x', 64),
            'subscribed_at' => now(),
        ]);

        $this->get(route('emails.unsubscribe', ['token' => $subscriber->unsubscribe_token]))
            ->assertOk()
            ->assertSee('<meta name="robots" content="noindex,nofollow">', false);

        $this->get(route('emails.preferences', ['token' => $subscriber->unsubscribe_token]))
            ->assertOk()
            ->assertSee('<meta name="robots" content="noindex,nofollow">', false);

        $transaction = Transaction::factory()->create([
            'type' => 'one_time',
            'status' => 'succeeded',
        ]);

        $this->withSession(['transaction_thankyou_id' => $transaction->id])
            ->get(route('donations.thankyou'))
            ->assertOk()
            ->assertSee('<meta name="robots" content="noindex,nofollow">', false);

        $pledge = Pledge::factory()->create();

        $this->withSession(['pledge_thankyou_id' => $pledge->id])
            ->get(route('donations.thankyou-subscription'))
            ->assertOk()
            ->assertSee('<meta name="robots" content="noindex,nofollow">', false);
    }

    #[Test]
    public function tokenized_and_thank_you_routes_stay_noindex_even_when_route_seo_exists(): void
    {
        $language = Language::factory()->english()->create();

        SeoMeta::factory()->routeTarget(RouteSeoTarget::DONATIONS_SHOW)->create([
            'language_id' => $language->id,
            'seo_title' => 'Give Indexable',
            'seo_description' => 'Should not affect token pages',
            'robots' => 'index,follow',
            'is_active' => true,
        ]);

        $subscriber = EmailSubscriber::create([
            'email' => 'test2@example.com',
            'unsubscribe_token' => str_repeat('y', 64),
            'subscribed_at' => now(),
        ]);

        $this->get(route('emails.unsubscribe', ['token' => $subscriber->unsubscribe_token]))
            ->assertOk()
            ->assertSee('<meta name="robots" content="noindex,nofollow">', false);

        $this->get(route('emails.preferences', ['token' => $subscriber->unsubscribe_token]))
            ->assertOk()
            ->assertSee('<meta name="robots" content="noindex,nofollow">', false);
    }

    #[Test]
    public function it_renders_google_verification_and_ga4_tags_when_configured(): void
    {
        config()->set('seo.google_site_verification', 'test-verification-token');
        config()->set('seo.ga4_measurement_id', 'G-TEST123456');

        $this->get('/')
            ->assertOk()
            ->assertSee('<meta name="google-site-verification" content="test-verification-token">', false)
            ->assertSee('https://www.googletagmanager.com/gtag/js?id=G-TEST123456', false)
            ->assertSee("gtag('config', 'G-TEST123456'", false);
    }

    #[Test]
    public function dedicated_keyword_page_is_listed_in_sitemap_when_active_and_published(): void
    {
        $language = Language::factory()->english()->create();
        $page = Page::factory()->create(['is_active' => true]);

        PageTranslation::factory()
            ->forLanguage($language)
            ->forPage($page)
            ->state([
                'slug' => 'homeless-ministry-sacramento',
                'is_active' => true,
                'published_at' => now()->subHour(),
            ])
            ->create();

        $this->get('/sitemap.xml')
            ->assertOk()
            ->assertSee(url('/pages/homeless-ministry-sacramento'), false);
    }

    #[Test]
    public function home_and_give_pages_link_to_keyword_landing_page(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee(url('/pages/homeless-ministry-sacramento'), false);

        $this->get('/give')
            ->assertOk()
            ->assertSee(url('/pages/homeless-ministry-sacramento'), false);
    }
}

<?php

namespace Tests\Feature\Livewire;

use App\Livewire\Home;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class HomeTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function home_component_renders_successfully(): void
    {
        Livewire::test(Home::class)
            ->assertStatus(200)
            ->assertSee('Bread of Grace Ministries')
            ->assertSee('Give today');
    }

    #[Test]
    public function home_route_renders_and_contains_expected_sections(): void
    {
        $response = $this->get('/');

        $response->assertOk()
            ->assertSeeLivewire(Home::class)

            // HERO
            ->assertSee('Bread of Grace Ministries')
            ->assertSee('Help restore lives through')
            ->assertSee('Give today')

            // ABOUT
            ->assertSee('A simple path to restoration.')
            ->assertSee('3 phases to rehabilitation')

            // SERVE
            ->assertSee('Serve with Bread of Grace.')

            // GIVE
            ->assertSee('Make outreach possible this week.')
            ->assertSee('Give now')

            // VISIT
            ->assertSee('Visit us');
    }

    #[Test]
    public function home_page_has_expected_anchors_for_scroll_navigation(): void
    {
        $response = $this->get('/');

        // false = don't escape, so raw HTML is matched
        $response->assertOk()
            ->assertSee('id="hero"', false)
            ->assertSee('id="about"', false)
            ->assertSee('id="serve"', false)
            ->assertSee('id="give"', false)
            ->assertSee('id="give-form"', false)
            ->assertSee('id="visit"', false);
    }

    #[Test]
    public function home_layout_sets_custom_title(): void
    {
        $response = $this->get('/');

        $response->assertOk()
            ->assertSee('<title>Bread of Grace Ministries</title>', false);
    }
}

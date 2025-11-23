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
    public function home_component_renders_successfully()
    {
        Livewire::test(Home::class)
            ->assertStatus(200);
    }

    #[Test]
    public function home_route_renders_and_contains_expected_sections()
    {
        // If your home route isn't "/", change this to the correct URI.
        $response = $this->get('/');

        $response->assertOk()
            ->assertSeeLivewire(Home::class)
            ->assertSee('Bread of Grace Ministries')
            ->assertSee('Give now') // CTA appears multiple times
            ->assertSee('Lives are changing')
            ->assertSee('Frequently asked questions')
            ->assertSee('Help us keep showing up every week');
    }

    #[Test]
    public function home_page_has_story_and_donate_anchors_for_scroll_navigation()
    {
        $response = $this->get('/');

        // false = don't escape, so raw HTML is matched
        $response->assertOk()
            ->assertSee('id="story"', false)
            ->assertSee('id="donate"', false);
    }

    #[Test]
    public function home_layout_sets_custom_title()
    {
        $response = $this->get('/');

        // Works if your app layout does: <title>{{ $title ?? config('app.name') }}</title>
        $response->assertOk()
            ->assertSee('<title>Bread of Grace Ministries</title>', false);
    }
}

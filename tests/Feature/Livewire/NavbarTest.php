<?php

namespace Tests\Feature\Livewire;

use App\Livewire\Navbar;
use App\Models\Language;
use App\Models\Page;
use App\Models\PageTranslation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class NavbarTest extends TestCase
{
    /** @test */
    #[Test]
    public function renders_successfully()
    {
        Livewire::test(Navbar::class)
            ->assertStatus(200);
    }

    /** @test */
    #[Test]
    function landing_page_contains_navbar_livewire_component()
    {
        $this->get('/')->assertSeeLivewire('navbar');
    }

    /** @test */
    #[Test]
    function pages_contains_navbar_livewire_component()
    {
        $translation = PageTranslation::factory()->create();
        
        $this->get('/pages/' . $translation->slug)->assertSeeLivewire('navbar');
    }
}

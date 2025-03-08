<?php

namespace Tests\Feature\Livewire\Pages;

use App\Livewire\Pages\IndexPage;
use App\Models\Language;
use App\Models\Page;
use App\Models\PageTranslation;
use Illuminate\Foundation\Testing\Concerns\WithoutExceptionHandlingHandler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Livewire\Livewire;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class IndexPageTest extends TestCase
{
    use WithFaker, RefreshDatabase;

    /** @test */
    #[Test]
    public function test_renders_successfully()
    {
        Livewire::test(IndexPage::class)
            ->assertStatus(200);
    }
}

<?php

namespace Tests\Feature\Email;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EmailSubscribePageTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_shows_the_subscribe_page(): void
    {
        $this->get(route('emails.subscribe'))
            ->assertOk()
            ->assertSee('Stay connected');
    }
}

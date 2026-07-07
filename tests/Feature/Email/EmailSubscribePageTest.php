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
            ->assertSee('Stay connected')
            ->assertSee('<meta name="robots" content="index,follow">', false)
            ->assertSee('<link rel="canonical" href="' . url('/emails/subscribe') . '">', false);
    }
}

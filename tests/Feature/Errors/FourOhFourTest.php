<?php

namespace Tests\Feature\Errors;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FourOhFourTest extends TestCase
{
    #[Test]
    public function it_renders_the_custom_404_view(): void
    {
        $res = $this->get('/definitely-not-a-real-route');

        $res->assertNotFound();
        $res->assertSee('404 — Page not found', false);
    }
}

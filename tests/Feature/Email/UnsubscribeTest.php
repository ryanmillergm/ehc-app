<?php

namespace Tests\Feature\Email;

use App\Models\EmailSubscriber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UnsubscribeTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_unsubscribes_a_valid_token(): void
    {
        $this->withoutExceptionHandling();

        $subscriber = EmailSubscriber::create([
            'email' => 'test@example.com',
            'unsubscribe_token' => 'tok-123',
            'subscribed_at' => now(),
        ]);

        $this->get(route('emails.unsubscribe', ['token' => 'tok-123']))
            ->assertOk();

        $subscriber->refresh();
        $this->assertNotNull($subscriber->unsubscribed_at);
    }

    #[Test]
    public function it_404s_on_invalid_token(): void
    {
        $this->get(route('emails.unsubscribe', ['token' => 'nope']))
            ->assertNotFound();
    }
}

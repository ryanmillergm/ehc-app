<?php

namespace Tests\Feature\Email;

use App\Models\EmailSubscriber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EmailPreferencesPublicPageTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_shows_the_public_preferences_page_for_a_valid_token(): void
    {
        $subscriber = EmailSubscriber::create([
            'email' => 'test@example.com',
            'unsubscribe_token' => str_repeat('t', 64),
            'subscribed_at' => now(),
        ]);

        $this->get(route('emails.preferences', ['token' => $subscriber->unsubscribe_token]))
            ->assertOk()
            ->assertSee('Email preferences')
            ->assertSee('test@example.com');
    }

    #[Test]
    public function it_404s_for_an_invalid_token(): void
    {
        $this->get(route('emails.preferences', ['token' => 'nope']))
            ->assertNotFound();
    }
}

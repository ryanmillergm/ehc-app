<?php

namespace Tests\Unit\Mail;

use App\Mail\MarketingMailable;
use App\Models\EmailSubscriber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MarketingMailableMetaTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_creates_a_subscriber_and_injects_marketing_urls_when_rendered(): void
    {
        $email = 'R.Y.A.N+tag@googlemail.com';

        $mailable = new class extends MarketingMailable {
            public function __construct()
            {
                $this->forEmailList('newsletter');

                // keep it simple and sendable
                $this->subject('Test subject');
                $this->html('<p>Hello world</p>');
            }
        };

        // IMPORTANT: rendering triggers buildViewData()
        $mailable->to($email)->render();

        $this->assertDatabaseCount('email_subscribers', 1);

        $subscriber = EmailSubscriber::query()->firstOrFail();

        $this->assertNotEmpty($subscriber->unsubscribe_token);
        $this->assertNotNull($subscriber->subscribed_at);
        $this->assertNull($subscriber->unsubscribed_at);

        $this->assertNotEmpty($subscriber->email_canonical);

        // Assert the injected view data exists
        $data = $mailable->buildViewData();

        $this->assertArrayHasKey('unsubscribeAllUrl', $data);
        $this->assertArrayHasKey('managePreferencesUrl', $data);
        $this->assertArrayHasKey('unsubscribeThisUrl', $data);
    }

    #[Test]
    public function it_reuses_existing_subscriber_and_does_not_duplicate_when_rendered_twice(): void
    {
        $email = 'test@example.com';

        $mailable = new class extends MarketingMailable {
            public function __construct()
            {
                $this->forEmailList('newsletter');
                $this->subject('Test subject');
                $this->html('<p>Hello world</p>');
            }
        };

        $mailable->to($email)->render();
        $mailable->to($email)->render();

        $this->assertDatabaseCount('email_subscribers', 1);

        $subscriber = EmailSubscriber::query()->firstOrFail();
        $this->assertNull($subscriber->unsubscribed_at);
        $this->assertNotNull($subscriber->subscribed_at);
    }
}

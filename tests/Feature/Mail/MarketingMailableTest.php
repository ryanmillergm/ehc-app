<?php

namespace Tests\Feature\Mail;

use App\Mail\MarketingMailable;
use App\Models\EmailSubscriber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MarketingMailableTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_injects_unsubscribe_and_preferences_urls_and_creates_subscriber_if_missing(): void
    {
        $mailable = new class extends MarketingMailable {
            public function __construct()
            {
                $this->forEmailList('newsletter');
            }

            public function exposeViewData(): array
            {
                return $this->buildViewData();
            }
        };

        $mailable->to('R.Y.A.N+tag@googlemail.com');

        $data = $mailable->exposeViewData();

        $subscriber = EmailSubscriber::query()
            ->where('email', 'ryan@gmail.com')
            ->firstOrFail();

        $this->assertSame(
            route('emails.unsubscribe', ['token' => $subscriber->unsubscribe_token]),
            $data['unsubscribeAllUrl'],
        );

        $this->assertSame(
            route('emails.unsubscribe', [
                'token' => $subscriber->unsubscribe_token,
                'list'  => 'newsletter',
            ]),
            $data['unsubscribeThisUrl'],
        );

        $this->assertSame(
            route('profile.show') . '#email-preferences',
            $data['managePreferencesUrl'],
        );
    }
}

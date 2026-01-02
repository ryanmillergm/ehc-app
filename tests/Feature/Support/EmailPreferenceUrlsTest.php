<?php

namespace Tests\Feature\Support;

use Tests\TestCase;
use App\Models\EmailList;
use App\Models\EmailSubscriber;
use App\Support\EmailPreferenceUrls;
use PHPUnit\Framework\Attributes\Test;
use Illuminate\Foundation\Testing\RefreshDatabase;

class EmailPreferenceUrlsTest extends TestCase
{
    use RefreshDatabase;

    private function makeSubscriber(array $overrides = []): EmailSubscriber
    {
        return EmailSubscriber::create(array_merge([
            'email' => 'test@example.com',
            'unsubscribe_token' => str_repeat('a', 64),
            'subscribed_at' => now(),
            'unsubscribed_at' => null,
        ], $overrides));
    }

    private function makeList(array $overrides = []): EmailList
    {
        return EmailList::create(array_merge([
            'key' => 'newsletter',
            'label' => 'Newsletter',
            'description' => null,
            'purpose' => 'marketing',
            'is_default' => true,
            'is_opt_outable' => true,
        ], $overrides));
    }

    #[Test]
    public function it_builds_the_unsubscribe_all_url_from_the_subscriber_token(): void
    {
        $subscriber = $this->makeSubscriber([
            'unsubscribe_token' => str_repeat('b', 64),
        ]);

        $this->assertSame(
            route('emails.unsubscribe', ['token' => $subscriber->unsubscribe_token]),
            EmailPreferenceUrls::unsubscribeAll($subscriber),
        );
    }

    #[Test]
    public function it_builds_the_unsubscribe_list_url_when_given_a_list_model(): void
    {
        $subscriber = $this->makeSubscriber([
            'unsubscribe_token' => str_repeat('c', 64),
        ]);

        $list = $this->makeList(['key' => 'events', 'label' => 'Events']);

        $this->assertSame(
            route('emails.unsubscribe', [
                'token' => $subscriber->unsubscribe_token,
                'list'  => $list->key,
            ]),
            EmailPreferenceUrls::unsubscribeList($subscriber, $list),
        );
    }

    #[Test]
    public function it_builds_the_unsubscribe_list_url_when_given_a_list_key_string(): void
    {
        $subscriber = $this->makeSubscriber([
            'unsubscribe_token' => str_repeat('d', 64),
        ]);

        $this->assertSame(
            route('emails.unsubscribe', [
                'token' => $subscriber->unsubscribe_token,
                'list'  => 'updates',
            ]),
            EmailPreferenceUrls::unsubscribeList($subscriber, 'updates'),
        );
    }

    #[Test]
    public function it_builds_the_manage_preferences_url_with_the_expected_anchor(): void
    {
        $this->assertSame(
            route('profile.show') . '#email-preferences',
            EmailPreferenceUrls::managePreferences(),
        );
    }

    #[Test]
    public function it_builds_the_public_manage_preferences_url_when_given_a_subscriber(): void
    {
        $subscriber = $this->makeSubscriber([
            'unsubscribe_token' => str_repeat('p', 64),
        ]);

        $this->assertSame(
            route('emails.preferences', ['token' => $subscriber->unsubscribe_token]),
            EmailPreferenceUrls::managePreferences($subscriber),
        );
    }
}

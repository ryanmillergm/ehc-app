<?php

namespace Tests\Feature\Email;

use App\Models\EmailList;
use App\Models\EmailSubscriber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EmailUnsubscribeControllerTest extends TestCase
{
    use RefreshDatabase;

    private function makeSubscriber(): EmailSubscriber
    {
        return EmailSubscriber::create([
            'email' => 'test@example.com',
            'first_name' => 'Test',
            'last_name' => 'User',
            'unsubscribe_token' => str_repeat('a', 64),
            'subscribed_at' => now(),
            'unsubscribed_at' => null,
        ]);
    }

    private function makeList(array $overrides = []): EmailList
    {
        return EmailList::create(array_merge([
            'key' => 'newsletter',
            'label' => 'Newsletter',
            'description' => null,
            'purpose' => 'marketing',     // <- your rename
            'is_default' => true,
            'is_opt_outable' => true,
        ], $overrides));
    }

    #[Test]
    public function it_globally_unsubscribes_a_subscriber(): void
    {
        $subscriber = $this->makeSubscriber();

        $this->get(route('emails.unsubscribe', ['token' => $subscriber->unsubscribe_token]))
            ->assertOk();

        $subscriber->refresh();

        $this->assertNotNull($subscriber->unsubscribed_at);
    }

    #[Test]
    public function it_unsubscribes_from_a_specific_list_when_list_query_param_is_present(): void
    {
        $subscriber = $this->makeSubscriber();
        $list = $this->makeList(['key' => 'events', 'label' => 'Events']);

        $subscriber->lists()->attach($list->id, [
            'subscribed_at' => now()->subDays(2),
            'unsubscribed_at' => null,
        ]);

        $this->get(route('emails.unsubscribe', [
            'token' => $subscriber->unsubscribe_token,
            'list' => 'events',
        ]))->assertOk();

        $this->assertDatabaseHas('email_list_subscriber', [
            'email_subscriber_id' => $subscriber->id,
            'email_list_id' => $list->id,
            // can't assert exact timestamp reliably; just assert not null by reloading below
        ]);

        $pivot = $subscriber->lists()->where('email_lists.id', $list->id)->firstOrFail()->pivot;
        $this->assertNotNull($pivot->unsubscribed_at);

        // and it should NOT globally unsubscribe them for list-specific opt-out
        $subscriber->refresh();
        $this->assertNull($subscriber->unsubscribed_at);
    }

    #[Test]
    public function it_refuses_to_unsubscribe_from_a_non_opt_outable_list(): void
    {
        $subscriber = $this->makeSubscriber();
        $list = $this->makeList([
            'key' => 'serve_request_received',
            'label' => 'Serve Request Received',
            'purpose' => 'transactional',
            'is_default' => false,
            'is_opt_outable' => false,
        ]);

        $subscriber->lists()->attach($list->id, [
            'subscribed_at' => now()->subDays(2),
            'unsubscribed_at' => null,
        ]);

        $this->get(route('emails.unsubscribe', [
            'token' => $subscriber->unsubscribe_token,
            'list' => 'serve_request_received',
        ]))->assertOk();

        $pivot = $subscriber->lists()->where('email_lists.id', $list->id)->firstOrFail()->pivot;
        $this->assertNull($pivot->unsubscribed_at);
    }

    #[Test]
    public function it_404s_on_invalid_token(): void
    {
        $this->get(route('emails.unsubscribe', ['token' => 'nope']))
            ->assertNotFound();
    }

    #[Test]
    public function it_404s_on_invalid_list_key_when_list_param_is_provided(): void
    {
        $subscriber = $this->makeSubscriber();

        $this->get(route('emails.unsubscribe', [
            'token' => $subscriber->unsubscribe_token,
            'list' => 'does_not_exist',
        ]))->assertNotFound();
    }
}

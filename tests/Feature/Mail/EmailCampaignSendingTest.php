<?php

namespace Tests\Feature\Mail;

use App\Jobs\QueueEmailCampaignSend;
use App\Models\EmailCampaign;
use App\Models\EmailCampaignDelivery;
use App\Models\EmailList;
use App\Models\EmailSubscriber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EmailCampaignSendingTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_sends_campaign_and_creates_delivery_rows(): void
    {
        config(['queue.default' => 'sync']);
        config(['mail.from.address' => 'admin@example.com']);
        config(['mail.from.name' => 'Admin']);
        Mail::fake();

        $list = EmailList::create([
            'key' => 'newsletter',
            'label' => 'Newsletter',
            'purpose' => 'marketing',
        ]);

        $s1 = EmailSubscriber::create([
            'email' => 'a@example.com',
            'unsubscribe_token' => Str::random(64),
            'subscribed_at' => now(),
            'preferences' => [],
        ]);

        $s2 = EmailSubscriber::create([
            'email' => 'b@example.com',
            'unsubscribe_token' => Str::random(64),
            'subscribed_at' => now(),
            'preferences' => [],
        ]);

        $s1->lists()->attach($list->id, ['subscribed_at' => now()]);
        $s2->lists()->attach($list->id, ['subscribed_at' => now()]);

        $campaign = EmailCampaign::create([
            'email_list_id' => $list->id,
            'subject' => 'Hello world',
            'body_html' => '<p>Test email</p>',
            'status' => EmailCampaign::STATUS_DRAFT,
        ]);

        QueueEmailCampaignSend::dispatchSync($campaign->id);

        $campaign->refresh();

        $this->assertSame(EmailCampaign::STATUS_SENT, $campaign->status);
        $this->assertSame(2, $campaign->sent_count);
        $this->assertNotNull($campaign->sent_at);

        $this->assertDatabaseCount('email_campaign_deliveries', 2);

        $delivery = EmailCampaignDelivery::query()->where('to_email', 'a@example.com')->firstOrFail();
        $this->assertSame(EmailCampaignDelivery::STATUS_SENT, $delivery->status);
        $this->assertSame('Hello world', $delivery->subject);
        $this->assertNotNull($delivery->body_html);
        $this->assertSame('admin@example.com', $delivery->from_email);
    }
}

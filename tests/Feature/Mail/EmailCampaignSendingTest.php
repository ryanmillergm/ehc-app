<?php

namespace Tests\Feature\Mail;

use App\Jobs\QueueEmailCampaignSend;
use App\Models\EmailCampaign;
use App\Models\EmailCampaignDelivery;
use App\Models\EmailList;
use App\Models\EmailSubscriber;
use App\Services\MailtrapApiMailer;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

        $fakeMailer = new class {
            /** @var array<int,array{from_email:string,to_email:string}> */
            public array $sent = [];

            public function sendHtml(
                string $fromEmail,
                ?string $fromName,
                string $toEmail,
                ?string $toName,
                string $subject,
                string $html,
                ?string $text = null,
                ?string $category = null,
            ): void {
                $this->sent[] = [
                    'from_email' => $fromEmail,
                    'to_email' => $toEmail,
                ];
            }
        };
        $this->app->instance(MailtrapApiMailer::class, $fakeMailer);

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
        $this->assertCount(2, $fakeMailer->sent);
    }
}

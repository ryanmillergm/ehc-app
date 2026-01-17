<?php

namespace Tests\Feature\Mail;

use App\Jobs\SendEmailCampaignChunk;
use App\Mail\EmailCampaignMail;
use App\Models\EmailCampaign;
use App\Models\EmailCampaignDelivery;
use App\Models\EmailList;
use App\Models\EmailSubscriber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SendEmailCampaignChunkExtraTest extends TestCase
{
    use RefreshDatabase;

    private function runJob(object $job): void
    {
        app()->call([$job, 'handle']);
    }

    #[Test]
    public function it_skips_deliveries_already_marked_sent_and_does_not_send_again(): void
    {
        Mail::fake();

        $list = EmailList::factory()->create(['purpose' => 'marketing']);

        $sub = EmailSubscriber::factory()->create([
            'subscribed_at' => now(),
            'unsubscribed_at' => null,
        ]);

        $list->subscribers()->attach($sub->id, [
            'subscribed_at' => now(),
            'unsubscribed_at' => null,
        ]);

        $campaign = EmailCampaign::factory()->create([
            'email_list_id' => $list->id,
            'status' => EmailCampaign::STATUS_SENDING,
            'pending_chunks' => 1,
            'editor' => 'rich',
            'subject' => 'Hello',
            'body_html' => '<p>Hi</p>',
        ]);

        $delivery = EmailCampaignDelivery::create([
            'email_campaign_id' => $campaign->id,
            'email_subscriber_id' => $sub->id,
            'to_email' => $sub->email,
            'to_name' => $sub->name,
            'from_email' => 'from@example.com',
            'from_name' => 'From',
            'subject' => $campaign->subject,
            'body_html' => '<p>old snapshot</p>',
            'status' => EmailCampaignDelivery::STATUS_SENT,
            'attempts' => 0,
            'sent_at' => now(),
        ]);

        $this->runJob(new SendEmailCampaignChunk($campaign->id, [$delivery->id]));

        $delivery->refresh();
        $campaign->refresh();

        $this->assertSame(0, (int) $delivery->attempts);
        Mail::assertNothingSent();

        // Because pending_chunks decremented to 0, it will finalize campaign
        $this->assertSame(EmailCampaign::STATUS_SENT, $campaign->status);
        $this->assertNotNull($campaign->sent_at);
    }

    #[Test]
    public function it_uses_delivery_from_email_when_present(): void
    {
        Mail::fake();

        $list = EmailList::factory()->create(['purpose' => 'marketing']);

        $sub = EmailSubscriber::factory()->create([
            'subscribed_at' => now(),
            'unsubscribed_at' => null,
        ]);

        $list->subscribers()->attach($sub->id, [
            'subscribed_at' => now(),
            'unsubscribed_at' => null,
        ]);

        $campaign = EmailCampaign::factory()->create([
            'email_list_id' => $list->id,
            'status' => EmailCampaign::STATUS_SENDING,
            'pending_chunks' => 1,
            'editor' => 'rich',
            'subject' => 'Hello',
            'body_html' => '<p>Hi</p>',
        ]);

        $delivery = EmailCampaignDelivery::create([
            'email_campaign_id' => $campaign->id,
            'email_subscriber_id' => $sub->id,
            'to_email' => $sub->email,
            'to_name' => $sub->name,
            'from_email' => 'special-from@example.com',
            'from_name' => 'Special From',
            'subject' => $campaign->subject,
            'body_html' => null,
            'status' => EmailCampaignDelivery::STATUS_QUEUED,
            'attempts' => 0,
        ]);

        $this->runJob(new SendEmailCampaignChunk($campaign->id, [$delivery->id]));

        $delivery->refresh();

        $this->assertSame(EmailCampaignDelivery::STATUS_SENT, $delivery->status);

        Mail::assertSent(EmailCampaignMail::class, function (EmailCampaignMail $mail) {
            $from = $mail->envelope()->from;

            return $from
                && $from->address === 'special-from@example.com'
                && $from->name === 'Special From';
        });
    }

    #[Test]
    public function its_failed_hook_marks_campaign_failed_and_clears_pending_chunks(): void
    {
        $list = EmailList::factory()->create(['purpose' => 'marketing']);

        $campaign = EmailCampaign::factory()->create([
            'email_list_id' => $list->id,
            'status' => EmailCampaign::STATUS_SENDING,
            'pending_chunks' => 3,
        ]);

        $job = new SendEmailCampaignChunk($campaign->id, []);

        $job->failed(new \RuntimeException('Boom'));

        $campaign->refresh();

        $this->assertSame(EmailCampaign::STATUS_FAILED, $campaign->status);
        $this->assertSame('Boom', $campaign->last_error);
        $this->assertSame(0, (int) $campaign->pending_chunks);
    }
}

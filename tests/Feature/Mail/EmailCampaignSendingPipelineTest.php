<?php

namespace Tests\Feature\Mail;

use App\Jobs\QueueEmailCampaignSend;
use App\Jobs\SendEmailCampaignChunk;
use App\Models\EmailCampaign;
use App\Models\EmailCampaignDelivery;
use App\Models\EmailList;
use App\Models\EmailSubscriber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EmailCampaignSendingPipelineTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: EmailList, 1: \Illuminate\Support\Collection<int, EmailSubscriber>}
     */
    private function makeListWithSubscribers(int $count = 3): array
    {
        /** @var EmailList $list */
        $list = EmailList::factory()->create([
            'purpose' => 'marketing',
        ]);

        /** @var \Illuminate\Support\Collection<int, EmailSubscriber> $subs */
        $subs = EmailSubscriber::factory()->count($count)->create([
            'subscribed_at' => now(),
            'unsubscribed_at' => null,
        ]);

        foreach ($subs as $sub) {
            $list->subscribers()->attach($sub->id, [
                'subscribed_at' => now(),
                'unsubscribed_at' => null,
            ]);
        }

        return [$list, $subs];
    }

    private function makeCampaign(EmailList $list): EmailCampaign
    {
        return EmailCampaign::factory()->create([
            'email_list_id' => $list->id,
            'status'        => EmailCampaign::STATUS_DRAFT,
        ]);
    }

    private function restoreMail(): void
    {
        // Restore the real mail manager after we swap it in a test.
        Mail::swap(app('mail.manager'));
    }

    private function swapMailToThrowFor(string $badEmail, string $message = 'Kaboom'): void
    {
        Mail::swap(new class($badEmail, $message) {
            public function __construct(
                private string $badEmail,
                private string $message,
            ) {}

            public function to($email, $name = null)
            {
                return new class((string) $email, $this->badEmail, $this->message) {
                    public function __construct(
                        private string $email,
                        private string $badEmail,
                        private string $message,
                    ) {}

                    public function send($mailable): void
                    {
                        if ($this->email === $this->badEmail) {
                            throw new \RuntimeException($this->message);
                        }

                        // success no-op
                    }
                };
            }
        });
    }

    private function runJob(object $job): void
    {
        // Run the job the same way the queue worker does: container resolves handle() args.
        app()->call([$job, 'handle']);
    }

    #[Test]
    public function queue_job_creates_delivery_rows_and_dispatches_chunk_jobs(): void
    {
        Bus::fake();

        [$list, $subs] = $this->makeListWithSubscribers(3);
        $campaign = $this->makeCampaign($list);

        (new QueueEmailCampaignSend($campaign->id))->handle();

        $this->assertDatabaseCount('email_campaign_deliveries', 3);

        foreach ($subs as $sub) {
            $this->assertDatabaseHas('email_campaign_deliveries', [
                'email_campaign_id'   => $campaign->id,
                'email_subscriber_id' => $sub->id,
                'status'              => EmailCampaignDelivery::STATUS_QUEUED,
            ]);
        }

        Bus::assertDispatched(SendEmailCampaignChunk::class);
    }

    #[Test]
    public function queue_job_excludes_globally_unsubscribed_and_list_unsubscribed(): void
    {
        Bus::fake();

        [$list, $subs] = $this->makeListWithSubscribers(3);
        $campaign = $this->makeCampaign($list);

        // Global unsubscribe
        $subs[0]->forceFill(['unsubscribed_at' => now()])->save();

        // List-level unsubscribe (pivot)
        $list->subscribers()->updateExistingPivot($subs[1]->id, [
            'unsubscribed_at' => now(),
        ]);

        (new QueueEmailCampaignSend($campaign->id))->handle();

        $this->assertDatabaseCount('email_campaign_deliveries', 1);

        $this->assertDatabaseHas('email_campaign_deliveries', [
            'email_campaign_id'   => $campaign->id,
            'email_subscriber_id' => $subs[2]->id,
            'status'              => EmailCampaignDelivery::STATUS_QUEUED,
        ]);
    }

    #[Test]
    public function queue_job_is_idempotent_and_does_not_duplicate_deliveries(): void
    {
        Bus::fake();

        [$list, $subs] = $this->makeListWithSubscribers(3);
        $campaign = $this->makeCampaign($list);

        (new QueueEmailCampaignSend($campaign->id))->handle();
        (new QueueEmailCampaignSend($campaign->id))->handle();

        $this->assertDatabaseCount('email_campaign_deliveries', 3);

        // It may dispatch chunks more than once if you run it twice, but rows must not duplicate.
        Bus::assertDispatched(SendEmailCampaignChunk::class);
    }

    #[Test]
    public function chunk_job_skips_if_unsubscribed_after_queuing_and_does_not_send(): void
    {
        Bus::fake([SendEmailCampaignChunk::class]);
        Mail::fake();

        [$list, $subs] = $this->makeListWithSubscribers(1);
        $campaign = $this->makeCampaign($list);

        (new QueueEmailCampaignSend($campaign->id))->handle();

        $delivery = EmailCampaignDelivery::query()->firstOrFail();

        // Unsubscribe AFTER the delivery exists
        $list->subscribers()->updateExistingPivot($subs[0]->id, [
            'unsubscribed_at' => now(),
        ]);

        $this->runJob(new SendEmailCampaignChunk($campaign->id, [$delivery->id]));

        $delivery->refresh();

        $this->assertSame(EmailCampaignDelivery::STATUS_SKIPPED, $delivery->status);
        $this->assertNull($delivery->sent_at);
        $this->assertNull($delivery->failed_at);

        Mail::assertNothingSent();
    }

    #[Test]
    public function chunk_job_marks_delivery_sent_on_success_and_stores_rendered_html(): void
    {
        Bus::fake([SendEmailCampaignChunk::class]);
        Mail::fake();

        [$list, $subs] = $this->makeListWithSubscribers(1);
        $campaign = $this->makeCampaign($list);

        (new QueueEmailCampaignSend($campaign->id))->handle();

        $delivery = EmailCampaignDelivery::query()->firstOrFail();

        $this->runJob(new SendEmailCampaignChunk($campaign->id, [$delivery->id]));

        $delivery->refresh();

        $this->assertSame(EmailCampaignDelivery::STATUS_SENT, $delivery->status);
        $this->assertNotNull($delivery->sent_at);
        $this->assertNull($delivery->failed_at);
        $this->assertTrue($delivery->attempts >= 1);
        $this->assertNotNull($delivery->body_html);

        Mail::assertSentCount(1);
    }

    #[Test]
    public function chunk_job_marks_delivery_failed_and_records_error_when_send_throws(): void
    {
        Bus::fake([SendEmailCampaignChunk::class]);

        $badEmail = 'fail@example.com';

        $sub = EmailSubscriber::factory()->create([
            'email' => $badEmail,
            'subscribed_at' => now(),
            'unsubscribed_at' => null,
        ]);

        $list = EmailList::factory()->create(['purpose' => 'marketing']);
        $list->subscribers()->attach($sub->id, [
            'subscribed_at' => now(),
            'unsubscribed_at' => null,
        ]);

        $campaign = $this->makeCampaign($list);

        (new QueueEmailCampaignSend($campaign->id))->handle();
        $delivery = EmailCampaignDelivery::query()->firstOrFail();

        $this->swapMailToThrowFor($badEmail, 'Kaboom');

        try {
            $this->runJob(new SendEmailCampaignChunk($campaign->id, [$delivery->id]));
        } finally {
            $this->restoreMail();
        }

        $delivery->refresh();

        $this->assertSame(EmailCampaignDelivery::STATUS_FAILED, $delivery->status);
        $this->assertNotNull($delivery->failed_at);
        $this->assertTrue($delivery->attempts >= 1);
        $this->assertNotEmpty($delivery->last_error);
    }

    #[Test]
    public function campaign_finishes_sent_when_all_deliveries_succeed(): void
    {
        Bus::fake([SendEmailCampaignChunk::class]);
        Mail::fake();

        [$list, $subs] = $this->makeListWithSubscribers(2);
        $campaign = $this->makeCampaign($list);

        (new QueueEmailCampaignSend($campaign->id))->handle();

        $deliveryIds = EmailCampaignDelivery::query()
            ->where('email_campaign_id', $campaign->id)
            ->pluck('id')
            ->all();

        $this->runJob(new SendEmailCampaignChunk($campaign->id, $deliveryIds));

        $campaign->refresh();

        $this->assertSame(EmailCampaign::STATUS_SENT, $campaign->status);
        $this->assertNotNull($campaign->sent_at);
    }

    #[Test]
    public function campaign_finishes_failed_when_any_delivery_fails(): void
    {
        Bus::fake([SendEmailCampaignChunk::class]);

        $goodEmail = 'ok@example.com';
        $badEmail  = 'fail@example.com';

        $list = EmailList::factory()->create(['purpose' => 'marketing']);

        $ok = EmailSubscriber::factory()->create([
            'email' => $goodEmail,
            'subscribed_at' => now(),
            'unsubscribed_at' => null,
        ]);

        $bad = EmailSubscriber::factory()->create([
            'email' => $badEmail,
            'subscribed_at' => now(),
            'unsubscribed_at' => null,
        ]);

        $list->subscribers()->attach($ok->id, [
            'subscribed_at' => now(),
            'unsubscribed_at' => null,
        ]);
        $list->subscribers()->attach($bad->id, [
            'subscribed_at' => now(),
            'unsubscribed_at' => null,
        ]);

        $campaign = $this->makeCampaign($list);

        (new QueueEmailCampaignSend($campaign->id))->handle();

        $deliveries = EmailCampaignDelivery::query()
            ->where('email_campaign_id', $campaign->id)
            ->orderBy('id')
            ->get();

        $this->swapMailToThrowFor($badEmail, 'Kaboom');

        try {
            $this->runJob(new SendEmailCampaignChunk($campaign->id, $deliveries->pluck('id')->all()));
        } finally {
            $this->restoreMail();
        }

        $campaign->refresh();

        $this->assertSame(EmailCampaign::STATUS_FAILED, $campaign->status);
        $this->assertNotNull($campaign->sent_at);

        $this->assertDatabaseHas('email_campaign_deliveries', [
            'email_campaign_id' => $campaign->id,
            'to_email'          => $badEmail,
            'status'            => EmailCampaignDelivery::STATUS_FAILED,
        ]);
    }
}

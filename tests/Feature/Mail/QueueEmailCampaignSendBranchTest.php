<?php

namespace Tests\Feature\Mail;

use App\Jobs\QueueEmailCampaignSend;
use App\Jobs\SendEmailCampaignChunk;
use App\Models\EmailCampaign;
use App\Models\EmailCampaignDelivery;
use App\Models\EmailList;
use App\Models\EmailSubscriber;
use App\Support\Email\EmailBodyCompiler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class QueueEmailCampaignSendBranchTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_marks_failed_if_list_is_not_marketing(): void
    {
        Bus::fake();

        // Must be marketing at creation time or EmailCampaign model throws
        $list = EmailList::factory()->create(['purpose' => 'marketing']);

        $campaign = EmailCampaign::factory()->create([
            'email_list_id' => $list->id,
            'status' => EmailCampaign::STATUS_DRAFT,
            'subject' => 'Hello',
            'editor' => 'rich',
            'body_html' => '<p>Hi</p>',
        ]);

        // simulate misconfiguration without triggering EmailList validation/events
        EmailList::whereKey($list->id)->update(['purpose' => 'transactional']);

        (new QueueEmailCampaignSend($campaign->id))->handle();

        $campaign->refresh();

        $this->assertSame(EmailCampaign::STATUS_FAILED, $campaign->status);
        $this->assertSame('Campaign can only send to marketing lists.', $campaign->last_error);

        $this->assertDatabaseCount('email_campaign_deliveries', 0);

        Bus::assertNotDispatched(SendEmailCampaignChunk::class);
    }

    #[Test]
    public function it_compiles_grapesjs_body_when_missing_and_design_is_present(): void
    {
        Bus::fake();

        config([
            'mail.from.address' => 'from@example.com',
            'mail.from.name' => 'From Name',
        ]);

        // Fake compiler so this test is deterministic
        app()->bind(EmailBodyCompiler::class, fn () => new class {
            public function compile(string $html, string $css): array
            {
                return ['html' => '<p>Compiled!</p>', 'text' => 'Compiled!'];
            }
        });

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
            'status' => EmailCampaign::STATUS_DRAFT,
            'subject' => 'Hello',
            'editor' => 'grapesjs',
            'body_html' => null,
            'body_text' => null,
            'design_html' => '<div>Hello</div>',
            'design_css' => 'div{color:red;}',
        ]);

        (new QueueEmailCampaignSend($campaign->id))->handle();

        $campaign->refresh();

        $this->assertSame('<p>Compiled!</p>', $campaign->body_html);
        $this->assertSame('Compiled!', $campaign->body_text);

        $this->assertDatabaseHas('email_campaign_deliveries', [
            'email_campaign_id' => $campaign->id,
            'email_subscriber_id' => $sub->id,
            'status' => EmailCampaignDelivery::STATUS_QUEUED,
        ]);

        Bus::assertDispatched(SendEmailCampaignChunk::class);
    }

    #[Test]
    public function it_marks_failed_if_body_is_blank_after_compilation_attempts(): void
    {
        Bus::fake();

        $list = EmailList::factory()->create(['purpose' => 'marketing']);

        $campaign = EmailCampaign::factory()->create([
            'email_list_id' => $list->id,
            'status' => EmailCampaign::STATUS_DRAFT,
            'subject' => 'Hello',
            'editor' => 'html',   // not grapesjs => no compile branch
            'body_html' => '',
        ]);

        (new QueueEmailCampaignSend($campaign->id))->handle();

        $campaign->refresh();

        $this->assertSame(EmailCampaign::STATUS_FAILED, $campaign->status);
        $this->assertSame('Campaign body is empty. Save the campaign before sending.', $campaign->last_error);

        Bus::assertNotDispatched(SendEmailCampaignChunk::class);
    }

    #[Test]
    public function it_marks_sent_immediately_when_there_are_zero_recipients(): void
    {
        Bus::fake();

        $list = EmailList::factory()->create(['purpose' => 'marketing']);

        $campaign = EmailCampaign::factory()->create([
            'email_list_id' => $list->id,
            'status' => EmailCampaign::STATUS_DRAFT,
            'subject' => 'Hello',
            'editor' => 'rich',
            'body_html' => '<p>Hello</p>',
        ]);

        (new QueueEmailCampaignSend($campaign->id))->handle();

        $campaign->refresh();

        $this->assertSame(EmailCampaign::STATUS_SENT, $campaign->status);
        $this->assertNotNull($campaign->sent_at);

        Bus::assertNotDispatched(SendEmailCampaignChunk::class);
    }

    #[Test]
    public function its_failed_hook_marks_campaign_failed_and_clears_pending_chunks(): void
    {
        $list = EmailList::factory()->create(['purpose' => 'marketing']);

        $campaign = EmailCampaign::factory()->create([
            'email_list_id' => $list->id,
            'status' => EmailCampaign::STATUS_SENDING,
            'pending_chunks' => 7,
        ]);

        $job = new QueueEmailCampaignSend($campaign->id);

        $job->failed(new \RuntimeException('Boom'));

        $campaign->refresh();

        $this->assertSame(EmailCampaign::STATUS_FAILED, $campaign->status);
        $this->assertSame('Boom', $campaign->last_error);
        $this->assertSame(0, (int) $campaign->pending_chunks);
    }
}

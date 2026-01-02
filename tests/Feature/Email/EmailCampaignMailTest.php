<?php

namespace Tests\Feature\Email;

use Tests\TestCase;
use App\Mail\EmailCampaignMail;
use App\Models\EmailList;
use App\Models\EmailSubscriber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;

class EmailCampaignMailTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_renders_manage_preferences_as_public_token_url(): void
    {
        $subscriber = EmailSubscriber::create([
            'email' => 'test@example.com',
            'unsubscribe_token' => str_repeat('m', 64),
            'subscribed_at' => now(),
            'unsubscribed_at' => null,
        ]);

        $list = EmailList::create([
            'key' => 'newsletter',
            'label' => 'Newsletter',
            'purpose' => 'marketing',
            'is_default' => true,
            'is_opt_outable' => true,
        ]);

        $mail = new EmailCampaignMail(
            subscriber: $subscriber,
            list: $list,
            subjectLine: 'Hello',
            bodyHtml: '<p>Hi there</p>',
        );

        $html = $mail->render();

        $this->assertStringContainsString(
            route('emails.preferences', ['token' => $subscriber->unsubscribe_token]),
            $html
        );
    }
}

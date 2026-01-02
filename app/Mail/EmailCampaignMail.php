<?php

namespace App\Mail;

use App\Models\EmailList;
use App\Models\EmailSubscriber;
use App\Support\EmailPreferenceUrls;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class EmailCampaignMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public EmailSubscriber $subscriber,
        public EmailList $list,
        public string $subjectLine,
        public string $bodyHtml,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->subjectLine,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.marketing.campaign',
            with: [
                'subscriber' => $this->subscriber,
                'list' => $this->list,
                'bodyHtml' => $this->bodyHtml,

                'unsubscribeAllUrl' => EmailPreferenceUrls::unsubscribeAll($this->subscriber),
                'unsubscribeThisUrl' => EmailPreferenceUrls::unsubscribeList($this->subscriber, $this->list),
                'managePreferencesUrl' => EmailPreferenceUrls::managePreferences(),
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}

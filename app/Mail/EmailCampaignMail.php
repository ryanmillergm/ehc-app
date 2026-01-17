<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\EmailList;
use App\Models\EmailSubscriber;
use App\Support\EmailPreferenceUrls;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address as MailAddress;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class EmailCampaignMail extends Mailable
{
    use Queueable, SerializesModels;

    protected ?string $fromEmailOverride = null;
    protected ?string $fromNameOverride = null;

    public function __construct(
        public EmailSubscriber $subscriber,
        public EmailList $list,
        public string $subjectLine,
        public string $bodyHtml,
    ) {
        $this->bodyHtml = (string) $this->bodyHtml;
    }

    /**
     * Safe override used by the delivery pipeline.
     * (Avoids relying on $this->from[] being hydrated at render time.)
     */
    public function usingFrom(string $email, ?string $name = null): static
    {
        $this->fromEmailOverride = $email;
        $this->fromNameOverride  = $name;

        return $this;
    }

    public function envelope(): Envelope
    {
        $fromEmail = $this->fromEmailOverride
            ?: (string) (config('mail.from.address') ?? 'hello@example.com');

        $fromName = $this->fromNameOverride
            ?: (string) (config('mail.from.name') ?? config('app.name'));

        return new Envelope(
            from: new MailAddress($fromEmail, $fromName),
            subject: $this->subjectLine,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.marketing.campaign',
            with: [
                'subscriber'            => $this->subscriber,
                'list'                  => $this->list,
                'bodyHtml'              => $this->bodyHtml,

                // Explicitly provided, even though MarketingMailable can also inject these.
                'unsubscribeAllUrl'     => EmailPreferenceUrls::unsubscribeAll($this->subscriber),
                'unsubscribeThisUrl'    => EmailPreferenceUrls::unsubscribeList($this->subscriber, $this->list->key),
                'managePreferencesUrl'  => EmailPreferenceUrls::managePreferences($this->subscriber),
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}

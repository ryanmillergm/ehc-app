<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\EmailSubscriber;
use App\Support\EmailCanonicalizer;
use App\Support\EmailPreferenceUrls;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address as MailAddress;
use Illuminate\Support\Str;

abstract class MarketingMailable extends Mailable
{
    protected ?string $emailListKey = null;

    public function forEmailList(string $key): static
    {
        $this->emailListKey = $key;

        return $this;
    }

    /**
     * Laravel calls this during rendering.
     * Works whether the concrete mailable uses build() OR envelope()/content().
     */
    final public function buildViewData(): array
    {
        return array_merge(
            parent::buildViewData(),
            $this->marketingViewData(),
        );
    }

    /**
     * Compute marketing-specific view data (unsubscribe links, prefs links).
     */
    protected function marketingViewData(): array
    {
        $toEmail = $this->getPrimaryToEmail();

        if (! $toEmail) {
            return [];
        }

        $canonical = EmailCanonicalizer::canonicalize($toEmail)
            ?? strtolower(trim($toEmail));

        $subscriber = $this->findOrCreateSubscriber($canonical);

        $data = [
            'unsubscribeAllUrl'    => EmailPreferenceUrls::unsubscribeAll($subscriber),
            'managePreferencesUrl' => EmailPreferenceUrls::managePreferences($subscriber),
        ];

        if ($this->emailListKey) {
            $data['unsubscribeThisUrl'] = EmailPreferenceUrls::unsubscribeList($subscriber, $this->emailListKey);
        }

        return $data;
    }

    protected function findOrCreateSubscriber(string $canonicalEmail): EmailSubscriber
    {
        $subscriber = EmailSubscriber::query()
            ->where('email_canonical', $canonicalEmail)
            ->orWhere('email', $canonicalEmail)
            ->first();

        if (! $subscriber) {
            return EmailSubscriber::create([
                'email'             => $canonicalEmail,
                'unsubscribe_token' => Str::random(64),
                'subscribed_at'     => now(),
                'preferences'       => [],
            ]);
        }

        // Keep canonical fields aligned (safe because email_canonical is unique)
        if ($subscriber->email !== $canonicalEmail) {
            $subscriber->forceFill(['email' => $canonicalEmail])->save();
        }

        return $subscriber;
    }

    protected function getPrimaryToEmail(): ?string
    {
        $first = $this->to[0] ?? null;

        if (! $first) {
            return null;
        }

        // Illuminate\Mail\Mailables\Address
        if ($first instanceof MailAddress) {
            return (string) $first->address;
        }

        // Some drivers / internals still expose objects with ->address
        if (is_object($first) && property_exists($first, 'address')) {
            return (string) $first->address;
        }

        // Some cases store an array shape
        if (is_array($first)) {
            return (string) ($first['address'] ?? $first['email'] ?? '');
        }

        return (string) $first;
    }
}

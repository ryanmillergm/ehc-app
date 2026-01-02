<?php

namespace App\Mail;

use Illuminate\Support\Str;
use Illuminate\Mail\Mailable;
use App\Models\EmailSubscriber;
use App\Support\EmailCanonicalizer;
use App\Support\EmailPreferenceUrls;

abstract class MarketingMailable extends Mailable
{
    protected ?string $emailListKey = null;

    public function forEmailList(string $key): static
    {
        $this->emailListKey = $key;
        return $this;
    }

    public function buildViewData(): array
    {
        $data = parent::buildViewData();

        $toEmail = $this->getPrimaryToEmail();
        if (! $toEmail) {
            return $data;
        }

        $canonical = EmailCanonicalizer::canonicalize($toEmail) ?? strtolower(trim($toEmail));

        $subscriber = EmailSubscriber::query()
            ->where('email_canonical', $canonical)
            ->orWhere('email', $canonical)
            ->first();

        if (! $subscriber) {
            $subscriber = EmailSubscriber::create([
                'email' => $canonical,
                'unsubscribe_token' => Str::random(64),
                'subscribed_at' => now(),
                'preferences' => [],
            ]);
        } elseif ($subscriber->email !== $canonical) {
            // keeps canonical fields aligned; safe because email_canonical is unique
            $subscriber->update(['email' => $canonical]);
        }

        $data['unsubscribeAllUrl'] = EmailPreferenceUrls::unsubscribeAll($subscriber);

        if ($this->emailListKey) {
            $data['unsubscribeThisUrl'] = route('emails.unsubscribe', [
                'token' => $subscriber->unsubscribe_token,
                'list'  => $this->emailListKey,
            ]);
        }

        $data['managePreferencesUrl'] = EmailPreferenceUrls::managePreferences();

        return $data;
    }

    protected function getPrimaryToEmail(): ?string
    {
        $first = $this->to[0] ?? null;

        if (! $first) {
            return null;
        }

        if (is_object($first) && property_exists($first, 'address')) {
            return (string) $first->address; // Illuminate\Mail\Mailables\Address
        }

        if (is_array($first)) {
            return (string) ($first['address'] ?? $first['email'] ?? '');
        }

        return (string) $first;
    }
}

<?php

namespace App\Mail;

use App\Models\EmailSubscriber;
use App\Support\EmailPreferenceUrls;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Str;

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

        $canonical = $this->canonicalizeEmail($toEmail);

        $subscriber = EmailSubscriber::query()->firstOrCreate(
            ['email' => $canonical],
            [
                'unsubscribe_token' => Str::random(64),
                'subscribed_at' => now(),
                'preferences' => [],
            ],
        );

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

    protected function canonicalizeEmail(string $email): string
    {
        $email = strtolower(trim($email));

        if (! str_contains($email, '@')) {
            return $email;
        }

        [$local, $domain] = explode('@', $email, 2);

        if ($domain === 'googlemail.com') {
            $domain = 'gmail.com';
        }

        if ($domain === 'gmail.com') {
            $local = explode('+', $local, 2)[0];   // strip plus tag
            $local = str_replace('.', '', $local); // strip dots
        }

        return $local . '@' . $domain;
    }
}

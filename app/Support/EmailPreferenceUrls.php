<?php

namespace App\Support;

use App\Models\EmailList;
use App\Models\EmailSubscriber;

class EmailPreferenceUrls
{
    public static function unsubscribeAll(EmailSubscriber $subscriber): string
    {
        return route('emails.unsubscribe', ['token' => $subscriber->unsubscribe_token]);
    }

    public static function unsubscribeList(EmailSubscriber $subscriber, EmailList|string $list): string
    {
        $key = $list instanceof EmailList ? $list->key : $list;

        return route('emails.unsubscribe', [
            'token' => $subscriber->unsubscribe_token,
            'list'  => $key,
        ]);
    }

    /**
     * If a subscriber is provided, return the public (token-based) page.
     * Otherwise fall back to the logged-in profile page (useful inside the app).
     */
    public static function managePreferences(?EmailSubscriber $subscriber = null): string
    {
        if ($subscriber) {
            return route('emails.preferences', ['token' => $subscriber->unsubscribe_token]);
        }

        return route('profile.show') . '#email-preferences';
    }
}

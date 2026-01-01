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

    public static function managePreferences(): string
    {
        // Jetstream Livewire profile page
        return route('profile.show') . '#email-preferences';
    }
}

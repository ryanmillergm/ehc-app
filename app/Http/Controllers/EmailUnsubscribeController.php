<?php

namespace App\Http\Controllers;

use App\Models\EmailList;
use App\Models\EmailSubscriber;
use Illuminate\Http\Request;

class EmailUnsubscribeController extends Controller
{
    public function __invoke(Request $request, string $token)
    {
        $subscriber = EmailSubscriber::query()
            ->where('unsubscribe_token', $token)
            ->firstOrFail();

        $now = now();

        // Optional list-specific unsubscribe via: /unsubscribe/{token}?list=newsletter
        $listKey = $request->string('list')->toString();

        if ($listKey !== '') {
            $list = EmailList::query()
                ->where('key', $listKey)
                ->firstOrFail();

            if (! $list->is_opt_outable) {
                return response()->view('emails.unsubscribe', [
                    'email'   => $subscriber->email,
                    'message' => 'That email type can’t be unsubscribed from.',
                ]);
            }

            // Idempotent: if pivot exists, updates it; if not, attaches it.
            $subscriber->lists()->syncWithoutDetaching([
                $list->id => [
                    'unsubscribed_at' => $now,
                ],
            ]);

            return response()->view('emails.unsubscribe', [
                'email'   => $subscriber->email,
                'message' => "You’ve been unsubscribed from {$list->label}.",
            ]);
        }

        // Global unsubscribe (marketing)
        $subscriber->update([
            'unsubscribed_at' => $now,
        ]);

        // Keep pivots tidy: mark any currently-attached opt-outable marketing lists unsubscribed too.
        $subscriber->lists()
            ->where('purpose', 'marketing')
            ->where('is_opt_outable', true)
            ->get(['email_lists.id'])
            ->each(function (EmailList $list) use ($subscriber, $now) {
                $subscriber->lists()->updateExistingPivot($list->id, [
                    'unsubscribed_at' => $now,
                ]);
            });

        return response()->view('emails.unsubscribe', [
            'email'   => $subscriber->email,
            'message' => 'You have been unsubscribed.',
        ]);
    }
}

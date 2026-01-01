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

        $listKey = trim((string) $request->query('list', ''));

        // List-specific unsubscribe
        if ($listKey !== '') {
            $list = EmailList::query()
                ->where('key', $listKey)
                ->firstOrFail();

            if (! $list->is_opt_outable) {
                return response()->view('emails.unsubscribe', [
                    'email' => $subscriber->email,
                    'message' => "That email type can't be unsubscribed from.",
                ]);
            }

            $now = now();

            // IMPORTANT:
            // syncWithoutDetaching will:
            // - update the pivot if it exists
            // - create the pivot row if it does NOT exist
            $subscriber->lists()->syncWithoutDetaching([
                $list->id => [
                    'unsubscribed_at' => $now,
                ],
            ]);

            return response()->view('emails.unsubscribe', [
                'email' => $subscriber->email,
                'message' => "You've been unsubscribed from {$list->label}.",
            ]);
        }

        // Global unsubscribe
        $now = now();

        $subscriber->update([
            'unsubscribed_at' => $now,
        ]);

        // Mark all *existing* marketing list subscriptions as unsubscribed for cleanliness
        // (uses rename: purpose = marketing|transactional)
        $subscriber->lists()
            ->where('purpose', 'marketing')
            ->each(function (EmailList $list) use ($subscriber, $now) {
                $subscriber->lists()->syncWithoutDetaching([
                    $list->id => [
                        'unsubscribed_at' => $now,
                    ],
                ]);
            });

        return response()->view('emails.unsubscribe', [
            'email' => $subscriber->email,
            'message' => 'You have been unsubscribed.',
        ]);
    }
}

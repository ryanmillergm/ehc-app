<?php

namespace App\Http\Controllers;

use App\Models\EmailSubscriber;
use Illuminate\Http\Request;

class EmailUnsubscribeController extends Controller
{
    public function __invoke(Request $request, string $token)
    {
        $subscriber = EmailSubscriber::query()
            ->where('unsubscribe_token', $token)
            ->firstOrFail();

        $subscriber->update([
            'unsubscribed_at' => now(),
        ]);

        return response()->view('emails.unsubscribe', [
            'email' => $subscriber->email,
        ]);
    }
}

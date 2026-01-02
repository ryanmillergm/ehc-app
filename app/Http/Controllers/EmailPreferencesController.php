<?php

namespace App\Http\Controllers;

use App\Models\EmailSubscriber;

class EmailPreferencesController extends Controller
{
    public function __invoke(string $token)
    {
        // 404 if token is invalid (avoid rendering a Livewire exception page)
        EmailSubscriber::query()
            ->where('unsubscribe_token', $token)
            ->firstOrFail();

        return view('emails.preferences', [
            'token' => $token,
        ]);
    }
}

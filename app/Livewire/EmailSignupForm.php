<?php

namespace App\Livewire;

use App\Models\EmailSubscriber;
use Illuminate\Support\Str;
use Livewire\Component;

class EmailSignupForm extends Component
{
    public string $email = '';
    public string $name  = '';

    public function rules(): array
    {
        return [
            'email' => ['required', 'email'],
            'name'  => ['nullable', 'string', 'max:255'],
        ];
    }

    public function submit(): void
    {
        $this->email = Str::lower(trim($this->email));
        $this->name  = trim($this->name);

        $this->validate();

        $subscriber = EmailSubscriber::query()->where('email', $this->email)->first();

        if (! $subscriber) {
            EmailSubscriber::create([
                'email' => $this->email,
                'name' => $this->name ?: null,
                'user_id' => auth()->id(),
                'preferences' => null, // add later (or set defaults in UI)
                'unsubscribe_token' => Str::random(64),
                'subscribed_at' => now(),
                'unsubscribed_at' => null,
            ]);
        } else {
            // Don’t duplicate. If they previously opted out, treat this as a resubscribe.
            $update = [
                'unsubscribed_at' => null,
            ];

            if (! $subscriber->subscribed_at || $subscriber->unsubscribed_at) {
                $update['subscribed_at'] = now();
            }

            // Only update name if provided and blank currently (avoids overwriting)
            if ($this->name && ! $subscriber->name) {
                $update['name'] = $this->name;
            }

            if (! $subscriber->unsubscribe_token) {
                $update['unsubscribe_token'] = Str::random(64);
            }

            if (! $subscriber->user_id && auth()->check()) {
                $update['user_id'] = auth()->id();
            }

            $subscriber->update($update);
        }

        $this->reset('email', 'name');
        session()->flash('email_signup_success', 'Thanks! You’re signed up.');
    }

    public function render()
    {
        return view('livewire.email-signup-form');
    }
}

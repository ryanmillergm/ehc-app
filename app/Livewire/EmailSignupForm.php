<?php

namespace App\Livewire;

use App\Models\EmailList;
use App\Models\EmailSubscriber;
use Illuminate\Support\Str;
use Livewire\Component;

class EmailSignupForm extends Component
{
    public string $variant = 'footer'; // 'footer' or 'page'

    public string $email = '';
    public string $first_name = '';
    public string $last_name  = '';

    public function rules(): array
    {
        $emailRule = app()->environment('production') ? 'email:rfc,dns' : 'email:rfc';

        $rules = [
            'email' => ['required', $emailRule],
        ];

        if ($this->variant === 'page') {
            $rules['first_name'] = ['required', 'string', 'max:255'];
            $rules['last_name']  = ['required', 'string', 'max:255'];
        } else {
            $rules['first_name'] = ['nullable', 'string', 'max:255'];
            $rules['last_name']  = ['nullable', 'string', 'max:255'];
        }

        return $rules;
    }

    public function submit(): void
    {
        $this->email = Str::lower(trim($this->email));
        $this->first_name = trim($this->first_name);
        $this->last_name  = trim($this->last_name);

        $this->validate();

        $canonicalEmail = $this->canonicalizeEmail($this->email);

        $subscriber = EmailSubscriber::query()
            ->where('email', $canonicalEmail)
            ->first();

        if (! $subscriber) {
            $subscriber = EmailSubscriber::create([
                'email' => $canonicalEmail,
                'first_name' => $this->first_name ?: null,
                'last_name' => $this->last_name ?: null,
                'user_id' => auth()->id(),
                'unsubscribe_token' => Str::random(64),
                'subscribed_at' => now(),
                'unsubscribed_at' => null,
            ]);
        } else {
            $update = [
                'unsubscribed_at' => null,
            ];

            if (! $subscriber->subscribed_at || $subscriber->unsubscribed_at) {
                $update['subscribed_at'] = now();
            }

            if ($this->first_name && ! $subscriber->first_name) {
                $update['first_name'] = $this->first_name;
            }
            if ($this->last_name && ! $subscriber->last_name) {
                $update['last_name'] = $this->last_name;
            }

            if (! $subscriber->unsubscribe_token) {
                $update['unsubscribe_token'] = Str::random(64);
            }

            if (! $subscriber->user_id && auth()->check()) {
                $update['user_id'] = auth()->id();
            }

            $subscriber->update($update);
        }

        // Subscribe to default marketing lists
        $defaultLists = EmailList::query()
            ->where('purpose', 'marketing')
            ->where('is_default', true)
            ->get();

        foreach ($defaultLists as $list) {
            $subscriber->lists()->syncWithoutDetaching([
                $list->id => [
                    'subscribed_at' => now(),
                    'unsubscribed_at' => null,
                ],
            ]);
        }

        $this->reset('email', 'first_name', 'last_name');
        session()->flash('email_signup_success', 'Thanks! You’re signed up.');
    }

    private function canonicalizeEmail(string $email): string
    {
        $email = Str::lower(trim($email));

        if (! str_contains($email, '@')) {
            return $email;
        }

        [$local, $domain] = explode('@', $email, 2);

        // Strip +tag for ALL domains (your requirement)
        if (str_contains($local, '+')) {
            $local = strstr($local, '+', true);
        }

        // Gmail dot-ignoring + googlemail normalization
        if (in_array($domain, ['gmail.com', 'googlemail.com'], true)) {
            $domain = 'gmail.com';
            $local = str_replace('.', '', $local);
        }

        return $local.'@'.$domain;
    }

    public function render()
    {
        return view('livewire.email-signup-form');
    }
}

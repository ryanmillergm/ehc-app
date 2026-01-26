<?php

namespace App\Livewire;

use App\Models\EmailList;
use App\Models\EmailSubscriber;
use App\Support\EmailCanonicalizer;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Livewire\Component;

class EmailSignupForm extends Component
{
    public ?string $turnstileToken = null; // token from widget
    public string $company = '';

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

        // Require Turnstile on public-facing variant(s)
        if (in_array($this->variant, ['footer', 'page'], true)) {
            $rules['turnstileToken'] = ['required', 'string'];
        }

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
        // 1) Honeypot (fail closed, pretend success)
        if ($this->company !== '') {
            session()->flash('email_signup_success', "Thanks! You’re signed up.");
            $this->reset('email', 'first_name', 'last_name', 'company', 'turnstileToken');
            $this->dispatch('turnstile-reset', id: 'tsEmailSignup_' . $this->getId());

            return;
        }

        // 2) Rate limit
        $key = 'email-signup:' . request()->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);
            session()->flash('email_signup_info', "Slow down, human. Try again in {$seconds} seconds.");
            return;
        }

        RateLimiter::hit($key, 60);

        // 3) Normalize input
        $this->email = trim($this->email);
        $this->first_name = trim($this->first_name);
        $this->last_name  = trim($this->last_name);

        // 4) Validate (includes turnstileToken required)
        $this->validate();

        // 5) Verify Turnstile
        if (! $this->verifyTurnstile((string) $this->turnstileToken)) {
            session()->flash('email_signup_info', 'Please verify you’re human and try again.');
            $this->dispatch('turnstile-reset', id: 'tsEmailSignup_' . $this->getId());

            $this->turnstileToken = null;
            return;
        }

        // 6) Canonicalize + find existing
        $canonicalEmail = EmailCanonicalizer::canonicalize($this->email) ?? Str::lower($this->email);

        $subscriber = EmailSubscriber::query()
            ->where('email_canonical', $canonicalEmail)
            ->orWhere('email', $canonicalEmail)
            ->first();

        // Already subscribed
        if ($subscriber && $subscriber->unsubscribed_at === null) {
            session()->flash('email_signup_info', 'You’re already subscribed — thanks for staying connected!');
            $this->reset('email', 'first_name', 'last_name', 'company', 'turnstileToken');
            $this->dispatch('turnstile-reset', id: 'tsEmailSignup_' . $this->getId());

            return;
        }

        // 7) Create or resubscribe
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

            if ($subscriber->email !== $canonicalEmail) {
                $update['email'] = $canonicalEmail;
            }

            $subscriber->update($update);
        }

        // 8) Subscribe to default lists
        $defaultLists = EmailList::query()
            ->where('purpose', 'marketing')
            ->where('is_default', true)
            ->get();

        $now = now();

        foreach ($defaultLists as $list) {
            $subscriber->lists()->syncWithoutDetaching([
                $list->id => [
                    'subscribed_at' => $now,
                    'unsubscribed_at' => null,
                ],
            ]);
        }

        // 9) Reset + success
        $this->reset('email', 'first_name', 'last_name', 'company', 'turnstileToken');
        $this->dispatch('turnstile-reset', id: 'tsEmailSignup_' . $this->getId());

        session()->flash('email_signup_success', 'Thanks! You’re signed up.');
    }

    protected function verifyTurnstile(string $token): bool
    {
        $secret = config('services.turnstile.secret');

        if (! $secret) {
            // If misconfigured locally, you can choose to fail-open in local only:
            return app()->environment('local');
        }

        $response = \Illuminate\Support\Facades\Http::asForm()->post(
            'https://challenges.cloudflare.com/turnstile/v0/siteverify',
            [
                'secret'   => $secret,
                'response' => $token,
                'remoteip' => request()->ip(),
            ]
        );

        if (! $response->ok()) {
            return false;
        }

        return (bool) data_get($response->json(), 'success', false);
    }

    public function render()
    {
        return view('livewire.email-signup-form');
    }
}

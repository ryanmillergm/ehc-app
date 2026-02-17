<?php

namespace App\Livewire;

use App\Models\EmailList;
use App\Models\EmailSubscriber;
use App\Support\EmailCanonicalizer;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Livewire\Component;

class EmailSignupForm extends Component
{
    public ?string $turnstileToken = null; // token from widget
    public bool $turnstileReady = false;
    public string $company = ''; // honeypot
    public int $bannerNonce = 0;

    public string $variant = 'footer'; // 'footer' | 'page'

    public string $email = '';
    public string $first_name = '';
    public string $last_name  = '';

    public ?string $bannerType = null;     // 'success' | 'info'
    public ?string $bannerMessage = null;

    public function rules(): array
    {
        $emailRule = app()->environment('production') ? 'email:rfc,dns' : 'email:rfc';

        $rules = [
            'email' => ['required', $emailRule],
        ];

        // Require Turnstile on public-facing variants
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

    protected function tsKey(): string
    {
        return 'tsEmailSignup_' . $this->getId() . '_' . $this->variant;
    }

    protected function banner(?string $type, ?string $message): void
    {
        $this->bannerType = $type;
        $this->bannerMessage = $message;
        $this->bannerNonce++;
    }

    public function submit(): void
    {
        $this->bannerType = null;
        $this->bannerMessage = null;

        $this->resetErrorBag();
        $this->resetValidation();

        $tsKey = $this->tsKey();

        Log::info('EmailSignupForm submit start', [
            'variant' => $this->variant,
            'email' => $this->email,
            'has_turnstile' => (bool) $this->turnstileToken,
            'ip' => request()->ip(),
        ]);

        // Honeypot
        if ($this->company !== '') {
            $this->reset('email', 'first_name', 'last_name', 'company', 'turnstileToken');
            $this->turnstileReady = false;
            $this->banner('success', "Thanks! You’re signed up.");
            $this->dispatch('turnstile-reset', id: $tsKey);
            return;
        }

        // Rate limit
        $rateKey = 'email-signup:' . request()->ip();

        if (RateLimiter::tooManyAttempts($rateKey, 5)) {
            $seconds = RateLimiter::availableIn($rateKey);
            $this->banner('info', "Slow down, human. Try again in {$seconds} seconds.");
            return;
        }

        // Normalize
        $email = Str::lower(trim($this->email));
        $first = trim($this->first_name);
        $last  = trim($this->last_name);

        $this->email = $email;
        $this->first_name = $first;
        $this->last_name = $last;

        // Validate
        $this->validate();

        // Verify Turnstile
        if (! $this->verifyTurnstile((string) $this->turnstileToken)) {
            $this->banner('info', 'Please verify you’re human and try again.');
            $this->turnstileToken = null;
            $this->turnstileReady = false;
            $this->dispatch('turnstile-reset', id: $tsKey);
            return;
        }

        RateLimiter::hit($rateKey, 60);

        // Lookup canonical
        $canonical = EmailCanonicalizer::canonicalize($email) ?? $email;

        $subscriber = EmailSubscriber::query()
            ->where('email_canonical', $canonical)
            ->orWhere('email', $email)
            ->first();

        // Already subscribed
        if ($subscriber && $subscriber->unsubscribed_at === null) {
            $this->reset('email', 'first_name', 'last_name', 'company', 'turnstileToken');
            $this->turnstileReady = false;
            $this->banner('info', 'You’re already subscribed — thanks for staying connected!');
            $this->dispatch('turnstile-reset', id: $tsKey);
            return;
        }

        // Create or resubscribe
        if (! $subscriber) {
            $subscriber = EmailSubscriber::create([
                'email'             => $email,
                'first_name'        => $first !== '' ? $first : null,
                'last_name'         => $last !== '' ? $last : null,
                'user_id'           => auth()->id(),
                'preferences'       => null,
                'unsubscribe_token' => Str::random(64),
                'subscribed_at'     => now(),
                'unsubscribed_at'   => null,
            ]);
        } else {
            $update = [
                'unsubscribed_at' => null,
            ];

            if (! $subscriber->subscribed_at || $subscriber->unsubscribed_at) {
                $update['subscribed_at'] = now();
            }

            if ($first !== '' && ! $subscriber->first_name) {
                $update['first_name'] = $first;
            }

            if ($last !== '' && ! $subscriber->last_name) {
                $update['last_name'] = $last;
            }

            if (! $subscriber->unsubscribe_token) {
                $update['unsubscribe_token'] = Str::random(64);
            }

            if (! $subscriber->user_id && auth()->check()) {
                $update['user_id'] = auth()->id();
            }

            if ($subscriber->email !== $email) {
                $update['email'] = $email; // model booted() updates email_canonical
            }

            $subscriber->update($update);
        }

        // Subscribe to default marketing lists
        $defaultLists = EmailList::query()
            ->where('purpose', 'marketing')
            ->where('is_default', true)
            ->get();

        $now = now();

        foreach ($defaultLists as $list) {
            $subscriber->lists()->syncWithoutDetaching([
                $list->id => [
                    'subscribed_at'   => $now,
                    'unsubscribed_at' => null,
                ],
            ]);
        }

        // Reset form
        $this->reset('email', 'first_name', 'last_name', 'company', 'turnstileToken');
        $this->turnstileReady = false;

        // Set banner AFTER reset
        $this->banner('success', "Thanks! You’re signed up.");

        // Reset widget (new attempt)
        $this->dispatch('turnstile-reset', id: $tsKey);

        Log::info('EmailSignupForm success', [
            'variant' => $this->variant,
            'subscriber_id' => $subscriber->id,
        ]);
    }

    protected function verifyTurnstile(string $token): bool
    {
        $secret = (string) config('services.turnstile.secret');

        if ($secret === '') {
            return app()->environment('local');
        }

        $response = Http::asForm()->post(
            'https://challenges.cloudflare.com/turnstile/v0/siteverify',
            [
                'secret'   => $secret,
                'response' => $token,
                'remoteip' => request()->ip(),
            ]
        );

        if (! $response->ok()) {
            Log::warning('Turnstile verify HTTP not ok', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            return false;
        }

        $json = $response->json();

        if (! (bool) data_get($json, 'success', false)) {
            Log::info('Turnstile verify failed', [
                'codes' => data_get($json, 'error-codes', []),
            ]);
            return false;
        }

        return true;
    }

    public function updatedEmail(): void
    {
        $this->bannerType = null;
        $this->bannerMessage = null;
    }

    public function updatedFirstName(): void
    {
        $this->bannerType = null;
        $this->bannerMessage = null;
    }

    public function updatedLastName(): void
    {
        $this->bannerType = null;
        $this->bannerMessage = null;
    }

    public function render()
    {
        return view('livewire.email-signup-form');
    }
}

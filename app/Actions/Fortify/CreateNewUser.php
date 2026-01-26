<?php

namespace App\Actions\Fortify;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\CreatesNewUsers;
use Laravel\Jetstream\Jetstream;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, mixed>  $input
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name'  => ['required', 'string', 'max:255'],

            // - rfc: must be a syntactically valid email
            // - dns: domain must have DNS records for mail
            'email'      => ['required', 'string', 'email:rfc,dns', 'max:255', 'unique:users', 'confirmed'],

            'password'   => $this->passwordRules(),
            'terms'      => Jetstream::hasTermsAndPrivacyPolicyFeature() ? ['accepted', 'required'] : [],

            // Turnstile token field
            'cf-turnstile-response' => ['required', 'string'],
        ], [
            'cf-turnstile-response.required' => 'Please confirm you are not a robot.',
        ])->validate();

        $this->verifyTurnstile($input['cf-turnstile-response']);

        return User::create([
            'first_name' => $input['first_name'],
            'last_name'  => $input['last_name'],
            'email'      => $input['email'],
            'password'   => Hash::make($input['password']),
        ]);
    }

    protected function verifyTurnstile(string $token): void
    {
        $secret = (string) config('services.turnstile.secret');

        // If misconfigured, fail closed so you don't silently accept bot signups.
        if (trim($secret) === '') {
            throw ValidationException::withMessages([
                'cf-turnstile-response' => 'Spam protection is temporarily unavailable. Please try again later.',
            ]);
        }

        try {
            $response = Http::asForm()
                ->timeout(5)
                ->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
                    'secret'   => $secret,
                    'response' => $token,
                    'remoteip' => request()->ip(), // optional but nice
                ]);
        } catch (\Throwable $e) {
            throw ValidationException::withMessages([
                'cf-turnstile-response' => 'Could not verify spam protection. Please try again.',
            ]);
        }

        $json = $response->json();

        if (!($json['success'] ?? false)) {
            // Optional: you can log $json['error-codes'] for debugging.
            throw ValidationException::withMessages([
                'cf-turnstile-response' => 'Spam check failed. Please try again.',
            ]);
        }
    }
}

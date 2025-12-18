<?php

namespace App\Http\Controllers\Donations;

use App\Http\Controllers\Controller;
use App\Models\Address;
use App\Models\Pledge;
use App\Models\Transaction;
use App\Services\StripeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Stripe\Exception\IdempotencyException;

class DonationsController extends Controller
{
    public function __construct(
        protected StripeService $stripe
    ) {}

    public function show()
    {
        return view('donations.give');
    }

    public function start(Request $request)
    {
        $data = $request->validate([
            'attempt_id' => ['nullable', 'string', 'max:80'],
            'amount'     => ['required', 'numeric', 'min:1'],
            'frequency'  => ['required', 'in:one_time,monthly'],
        ]);

        $clientAttemptId = $data['attempt_id'] ?? null;
        $amountCents     = (int) round($data['amount'] * 100);
        $frequency       = $data['frequency'];
        $user            = Auth::user();

        $donor = [
            'email' => $user?->email,
            'name'  => $user ? trim((string) $user->name) : null,
        ];

        // ---------------------------------------------------------------------
        // One-time (idempotent by *server-issued* attempt_id)
        // ---------------------------------------------------------------------
        if ($frequency === 'one_time') {
            $transaction = null;

            // Only accept a client attempt id if it maps to an existing tx with SAME amount.
            if ($clientAttemptId) {
                $transaction = Transaction::query()
                    ->where('attempt_id', $clientAttemptId)
                    ->where('type', 'one_time')
                    ->where('source', 'donation_widget')
                    ->latest('id')
                    ->first();

                if ($transaction && (int) $transaction->amount_cents !== $amountCents) {
                    // stale attempt id (amount changed) -> treat as new attempt
                    $transaction = null;
                }

                if (! $transaction) {
                    // client gave us an id we don't recognize -> rotate
                    $clientAttemptId = null;
                }
            }

            $attemptId = $transaction?->attempt_id ?: (string) Str::uuid();

            if (! $transaction) {
                $transaction = Transaction::create([
                    'attempt_id'   => $attemptId,
                    'user_id'      => $user?->id,
                    'amount_cents' => $amountCents,
                    'currency'     => 'usd',
                    'type'         => 'one_time',
                    'status'       => 'pending',
                    'source'       => 'donation_widget',
                    'metadata'     => [
                        'frequency' => 'one_time',
                    ],
                ]);
            }

            try {
                $pi = $this->stripe->createOneTimePaymentIntent($transaction, $donor);
            } catch (IdempotencyException $e) {
                // Stripe says this idempotency key was used with different params -> rotate + retry once
                Log::warning('Stripe idempotency collision on one-time start; rotating attempt_id', [
                    'transaction_id' => $transaction->id,
                    'attempt_id'     => $transaction->attempt_id,
                    'error'          => $e->getMessage(),
                ]);

                $attemptId = (string) Str::uuid();
                $transaction->attempt_id = $attemptId;
                $transaction->save();

                $pi = $this->stripe->createOneTimePaymentIntent($transaction, $donor);
            }

            return response()->json([
                'mode'          => 'payment',
                'attemptId'     => $attemptId, // server truth
                'transactionId' => $transaction->id,
                'clientSecret'  => $pi->client_secret,
            ]);
        }

        // ---------------------------------------------------------------------
        // Monthly pledge (idempotent by attempt_id)
        // ---------------------------------------------------------------------
        $attemptId = $clientAttemptId ?: (string) Str::uuid();

        $pledge = Pledge::query()
            ->where('attempt_id', $attemptId)
            ->where('interval', 'month')
            ->latest('id')
            ->first();

        if ($pledge && (int) $pledge->amount_cents !== $amountCents) {
            $attemptId = (string) Str::uuid();
            $pledge = null;
        }

        if (! $pledge) {
            $pledge = Pledge::create([
                'attempt_id'   => $attemptId,
                'user_id'      => $user?->id,
                'amount_cents' => $amountCents,
                'currency'     => 'usd',
                'interval'     => 'month',
                'status'       => 'incomplete',
                'donor_email'  => $donor['email'] ?? null,
                'donor_name'   => $donor['name'] ?? null,
                'metadata'     => [
                    'frequency' => 'monthly',
                ],
            ]);
        }

        $setupIntent = $this->stripe->createSetupIntentForPledge($pledge, $donor);

        return response()->json([
            'mode'         => 'subscription',
            'attemptId'    => $attemptId,
            'pledgeId'     => $pledge->id,
            'clientSecret' => $setupIntent->client_secret,
        ]);
    }

    public function complete(Request $request)
    {
        $data = $request->validate([
            'attempt_id' => ['nullable', 'string', 'max:80'],

            'mode'           => ['required', 'in:payment,subscription'],
            'transaction_id' => ['nullable', 'integer', 'required_if:mode,payment'],
            'pledge_id'      => ['nullable', 'integer', 'required_if:mode,subscription'],

            'payment_intent_id' => ['nullable', 'string'],
            'charge_id'         => ['nullable', 'string'],
            'payment_method_id' => ['nullable', 'string'],
            'receipt_url'       => ['nullable', 'url'],

            'donor_first_name' => ['nullable', 'string', 'max:100'],
            'donor_last_name'  => ['nullable', 'string', 'max:100'],
            'donor_email'      => ['nullable', 'email'],
            'donor_phone'      => ['nullable', 'string', 'max:50'],
            'address_line1'    => ['nullable', 'string', 'max:255'],
            'address_line2'    => ['nullable', 'string', 'max:255'],
            'address_city'     => ['nullable', 'string', 'max:100'],
            'address_state'    => ['nullable', 'string', 'max:100'],
            'address_postal'   => ['nullable', 'string', 'max:20'],
            'address_country'  => ['nullable', 'string', 'max:2'],
        ]);

        $attemptId = $data['attempt_id'] ?? null;

        // Update authenticated user + primary address
        if ($user = Auth::user()) {
            $user->fill([
                'first_name' => $data['donor_first_name'] ?? $user->first_name,
                'last_name'  => $data['donor_last_name']  ?? $user->last_name,
                'email'      => $data['donor_email']      ?? $user->email,
            ])->save();

            $hasAddressInput = ! empty($data['address_line1'])
                || ! empty($data['address_city'])
                || ! empty($data['address_postal']);

            if ($hasAddressInput) {
                $primary = Address::firstOrNew([
                    'user_id'    => $user->id,
                    'is_primary' => true,
                ]);

                if (! $primary->exists) {
                    $primary->label = 'Primary';
                }

                $primary->fill([
                    'first_name'  => $data['donor_first_name'] ?? $primary->first_name ?? $user->first_name,
                    'last_name'   => $data['donor_last_name']  ?? $primary->last_name  ?? $user->last_name,
                    'phone'       => $data['donor_phone']      ?? $primary->phone,
                    'line1'       => $data['address_line1']    ?? $primary->line1,
                    'line2'       => $data['address_line2']    ?? $primary->line2,
                    'city'        => $data['address_city']     ?? $primary->city,
                    'state'       => $data['address_state']    ?? $primary->state,
                    'postal_code' => $data['address_postal']   ?? $primary->postal_code,
                    'country'     => $data['address_country']  ?? $primary->country ?? 'US',
                    'is_primary'  => true,
                ])->save();
            }
        }

        $fullName = trim(($data['donor_first_name'] ?? '') . ' ' . ($data['donor_last_name'] ?? ''));

        // ---------------------------------------------------------------------
        // One-time completion (server-enriched)
        // ---------------------------------------------------------------------
        if ($data['mode'] === 'payment') {
            $transaction = Transaction::findOrFail($data['transaction_id']);

            if ($attemptId && empty($transaction->attempt_id)) {
                $transaction->attempt_id = $attemptId;
            }

            if ($user = Auth::user()) {
                $transaction->user_id ??= $user->id;
            }

            $transaction->fill([
                'payment_intent_id' => $data['payment_intent_id'] ?? $transaction->payment_intent_id,
                'payment_method_id' => $data['payment_method_id'] ?? $transaction->payment_method_id,
                'payer_email'       => $data['donor_email']       ?? $transaction->payer_email,
                'payer_name'        => $fullName ?: $transaction->payer_name,
            ])->save();

            $piId       = $transaction->payment_intent_id;
            $chargeId   = $data['charge_id'] ?? $transaction->charge_id;
            $receiptUrl = $data['receipt_url'] ?? $transaction->receipt_url;

            try {
                if ($piId) {
                    $pi = $this->stripe->retrievePaymentIntent($piId);

                    $transaction->customer_id ??= is_string($pi->customer ?? null)
                        ? $pi->customer
                        : ($pi->customer->id ?? null);

                    $transaction->payment_method_id ??= is_string($pi->payment_method ?? null)
                        ? $pi->payment_method
                        : ($pi->payment_method->id ?? null);

                    $chargeId ??= is_string($pi->latest_charge ?? null)
                        ? $pi->latest_charge
                        : ($pi->latest_charge->id ?? null);
                }

                if ($chargeId) {
                    $charge = $this->stripe->retrieveCharge($chargeId);

                    $receiptUrl ??= $charge->receipt_url ?? null;

                    // Fill payer info if missing (helps for 3DS / partial client payloads)
                    $transaction->payer_email ??= data_get($charge, 'billing_details.email');
                    $transaction->payer_name  ??= data_get($charge, 'billing_details.name');

                    $card = data_get($charge, 'payment_method_details.card');

                    $meta = is_array($transaction->metadata)
                        ? $transaction->metadata
                        : ((array) json_decode((string) $transaction->metadata, true) ?: []);

                    if ($card) {
                        $meta = array_merge($meta, array_filter([
                            'card_brand'   => $card->brand ?? null,
                            'card_last4'   => $card->last4 ?? null,
                            'card_country' => $card->country ?? null,
                            'card_funding' => $card->funding ?? null,
                        ]));
                    }

                    $transaction->metadata = $meta;
                }
            } catch (\Throwable $e) {
                Log::warning('Donation complete: Stripe enrichment failed', [
                    'transaction_id' => $transaction->id,
                    'payment_intent' => $piId,
                    'error'          => $e->getMessage(),
                ]);
            }

            $transaction->fill([
                'charge_id'   => $chargeId ?? $transaction->charge_id,
                'receipt_url' => $receiptUrl ?? $transaction->receipt_url,
                'status'      => 'succeeded',
                'paid_at'     => $transaction->paid_at ?? now(),
            ])->save();

            $request->session()->put('transaction_thankyou_id', $transaction->id);

            if ($request->wantsJson()) {
                return response()->json(['redirect' => route('donations.thankyou')]);
            }

            return redirect()->route('donations.thankyou')->with('success', 'Thank you for your donation!');
        }

        // ---------------------------------------------------------------------
        // Subscription completion
        // ---------------------------------------------------------------------
        $pledge = Pledge::findOrFail($data['pledge_id']);

        $attemptId ??= $pledge->attempt_id; // important fallback

        if ($attemptId && empty($pledge->attempt_id)) {
            $pledge->attempt_id = $attemptId;
        }

        if (! empty($data['donor_email'])) {
            $pledge->donor_email = $data['donor_email'];
        }
        if ($fullName !== '') {
            $pledge->donor_name = $fullName;
        }
        $pledge->save();

        $placeholder = Transaction::query()
            ->where('pledge_id', $pledge->id)
            ->whereIn('type', ['subscription_initial', 'subscription_recurring'])
            ->whereNull('payment_intent_id')
            ->whereNull('charge_id')
            ->latest('id')
            ->first();

        if (! $placeholder) {
            Transaction::create([
                'attempt_id'      => $attemptId,
                'user_id'         => $pledge->user_id,
                'pledge_id'       => $pledge->id,
                'subscription_id' => $pledge->stripe_subscription_id,
                'amount_cents'    => $pledge->amount_cents ?? 0,
                'currency'        => $pledge->currency ?? 'usd',
                'type'            => 'subscription_initial',
                'status'          => 'pending',
                'source'          => 'donation_widget',
                'payer_email'     => $pledge->donor_email,
                'payer_name'      => $pledge->donor_name,
                'metadata'        => [
                    'stage' => 'subscription_creation',
                ],
            ]);
        }

        $this->stripe->createSubscriptionForPledge($pledge, (string) $data['payment_method_id']);

        $request->session()->put('pledge_thankyou_id', $pledge->id);

        if ($request->wantsJson()) {
            return response()->json(['redirect' => route('donations.thankyou-subscription')]);
        }

        return redirect()->route('donations.thankyou-subscription')->with('success', 'Thank you for your monthly pledge!');
    }

    /**
     * Stripe redirect return
     * Must finish the flow because the widget may not call /complete in redirect scenarios.
     */
    public function stripeReturn(Request $request)
    {
        $piId = $request->query('payment_intent');
        $siId = $request->query('setup_intent');

        // One-time redirect completion
        if ($piId) {
            $tx = Transaction::where('payment_intent_id', $piId)->first();

            if (! $tx) {
                Log::warning('stripeReturn: transaction not found', ['payment_intent' => $piId]);
                return redirect()->route('donations.show')->withErrors('We could not find your donation attempt. Please try again.');
            }

            try {
                $pi = $this->stripe->retrievePaymentIntent($piId);

                if (($pi->status ?? null) !== 'succeeded') {
                    $tx->status = $pi->status ?: $tx->status;
                    $tx->save();

                    return redirect()->route('donations.show')->withErrors('Your payment was not completed. Please try again.');
                }

                $tx->status  = 'succeeded';
                $tx->paid_at = $tx->paid_at ?? now();

                $tx->customer_id ??= is_string($pi->customer ?? null)
                    ? $pi->customer
                    : ($pi->customer->id ?? null);

                $tx->payment_method_id ??= is_string($pi->payment_method ?? null)
                    ? $pi->payment_method
                    : ($pi->payment_method->id ?? null);

                $chargeId = is_string($pi->latest_charge ?? null)
                    ? $pi->latest_charge
                    : ($pi->latest_charge->id ?? null);

                if ($chargeId) {
                    $tx->charge_id ??= $chargeId;

                    $charge = $this->stripe->retrieveCharge($chargeId);

                    $tx->receipt_url ??= $charge->receipt_url ?? null;
                    $tx->payer_email ??= data_get($charge, 'billing_details.email');
                    $tx->payer_name  ??= data_get($charge, 'billing_details.name');
                }

                $tx->save();

                $request->session()->put('transaction_thankyou_id', $tx->id);

                return redirect()->route('donations.thankyou');
            } catch (\Throwable $e) {
                Log::warning('stripeReturn: PI finalize failed', [
                    'payment_intent' => $piId,
                    'error' => $e->getMessage(),
                ]);

                return redirect()->route('donations.show')->withErrors('We could not finalize your donation. Please try again.');
            }
        }

        // Subscription redirect completion
        if ($siId) {
            $pledge = Pledge::where('setup_intent_id', $siId)->first();

            if (! $pledge) {
                Log::warning('stripeReturn: pledge not found', ['setup_intent' => $siId]);
                return redirect()->route('donations.show')->withErrors('We could not find your pledge attempt. Please try again.');
            }

            try {
                $si = $this->stripe->retrieveSetupIntent($siId);

                if (($si->status ?? null) !== 'succeeded') {
                    return redirect()->route('donations.show')->withErrors('Your card setup was not completed. Please try again.');
                }

                $pmId = is_string($si->payment_method ?? null)
                    ? $si->payment_method
                    : ($si->payment_method->id ?? null);

                if (! $pmId) {
                    return redirect()->route('donations.show')->withErrors('Missing payment method. Please try again.');
                }

                // Pull billing details from the PaymentMethod
                try {
                    $pm = $this->stripe->retrievePaymentMethod($pmId);
                    $pledge->donor_email ??= data_get($pm, 'billing_details.email');
                    $pledge->donor_name  ??= data_get($pm, 'billing_details.name');
                    $pledge->save();
                } catch (\Throwable $e) {
                    Log::info('stripeReturn: could not read payment method billing details', [
                        'setup_intent' => $siId,
                        'payment_method' => $pmId,
                        'error' => $e->getMessage(),
                    ]);
                }

                $this->stripe->createSubscriptionForPledge($pledge, $pmId);

                $request->session()->put('pledge_thankyou_id', $pledge->id);

                return redirect()->route('donations.thankyou-subscription');
            } catch (\Throwable $e) {
                Log::warning('stripeReturn: subscription finalize failed', [
                    'setup_intent' => $siId,
                    'pledge_id' => $pledge->id,
                    'error' => $e->getMessage(),
                ]);

                return redirect()->route('donations.show')->withErrors('We could not finalize your monthly pledge. Please try again.');
            }
        }

        return redirect()->route('donations.show');
    }

    public function thankYou(Request $request)
    {
        $transactionId = $request->session()->pull('transaction_thankyou_id');
        if (! $transactionId) {
            abort(404);
        }

        $transaction = Transaction::findOrFail($transactionId);

        return view('donations.thankyou', compact('transaction'));
    }

    public function thankYouSubscription(Request $request)
    {
        $pledgeId = $request->session()->pull('pledge_thankyou_id');
        if (! $pledgeId) {
            abort(404);
        }

        $pledge = Pledge::findOrFail($pledgeId);

        $subscriptionTransaction = Transaction::where('pledge_id', $pledge->id)
            ->whereIn('type', ['subscription_initial', 'subscription_recurring'])
            ->orderByDesc('paid_at')
            ->orderByDesc('id')
            ->first();

        return view('donations.thankyou-subscription', [
            'pledge'                  => $pledge,
            'subscriptionTransaction' => $subscriptionTransaction,
        ]);
    }
}

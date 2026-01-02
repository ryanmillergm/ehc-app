<?php

namespace App\Http\Controllers\Donations;

use Throwable;
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
        // CANARY
        Log::error('CANARY: DonationsController@start reached', [
            'time' => now()->toDateTimeString(),
        ]);

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

        $this->sdbg('start: validated', [
            'frequency'         => $frequency,
            'amount_cents'      => $amountCents,
            'client_attempt_id' => $clientAttemptId,
            'user_id'           => $user?->id,
            'donor_email'       => $donor['email'] ?? null,
        ]);

        // ---------------------------------------------------------------------
        // One-time
        // ---------------------------------------------------------------------
        if ($frequency === 'one_time') {
            $transaction = null;

            if ($clientAttemptId) {
                $transaction = Transaction::query()
                    ->where('attempt_id', $clientAttemptId)
                    ->where('type', 'one_time')
                    ->where('source', 'donation_widget')
                    ->latest('id')
                    ->first();

                if ($transaction && (int) $transaction->amount_cents !== $amountCents) {
                    $this->sdbg('start(one_time): client attempt id amount mismatch, ignoring', [
                        'tx_id'             => $transaction->id,
                        'tx_amount_cents'   => $transaction->amount_cents,
                        'req_amount_cents'  => $amountCents,
                        'client_attempt_id' => $clientAttemptId,
                    ]);
                    $transaction = null;
                }

                if (! $transaction) {
                    $this->sdbg('start(one_time): client attempt id not recognized, rotating', [
                        'client_attempt_id' => $clientAttemptId,
                    ]);
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

                $this->sdbg('start(one_time): created tx', $this->txSnap($transaction));
            } else {
                $this->sdbg('start(one_time): using existing tx', $this->txSnap($transaction));
            }

            try {
                $pi = $this->stripe->createOneTimePaymentIntent($transaction, $donor);
            } catch (IdempotencyException $e) {
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

            $this->sdbg('start(one_time): PI ready', [
                'tx_id'             => $transaction->id,
                'attempt_id'        => $attemptId,
                'payment_intent_id' => $pi->id ?? null,
                'pi_status'         => $pi->status ?? null,
            ]);

            return response()->json([
                'mode'          => 'payment',
                'attemptId'     => $attemptId,
                'transactionId' => $transaction->id,
                'clientSecret'  => $pi->client_secret,
            ]);
        }

        // ---------------------------------------------------------------------
        // Monthly pledge
        // ---------------------------------------------------------------------
        $attemptId = $clientAttemptId ?: (string) Str::uuid();

        $pledge = Pledge::query()
            ->where('attempt_id', $attemptId)
            ->where('interval', 'month')
            ->latest('id')
            ->first();

        if ($pledge && (int) $pledge->amount_cents !== $amountCents) {
            $this->sdbg('start(monthly): pledge exists but amount mismatch, rotating attempt', [
                'pledge_id'           => $pledge->id,
                'pledge_amount_cents' => $pledge->amount_cents,
                'req_amount_cents'    => $amountCents,
                'attempt_id'          => $attemptId,
            ]);

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

            $this->sdbg('start(monthly): created pledge', $this->pledgeSnap($pledge));
        } else {
            $this->sdbg('start(monthly): using existing pledge', $this->pledgeSnap($pledge));
        }

        $setupIntent = $this->stripe->createSetupIntentForPledge($pledge, $donor);

        $this->sdbg('start(monthly): setup intent ready', [
            'pledge_id'       => $pledge->id,
            'attempt_id'      => $attemptId,
            'setup_intent_id' => $setupIntent->id ?? null,
            'si_status'       => $setupIntent->status ?? null,
            'customer'        => is_string($setupIntent->customer ?? null) ? $setupIntent->customer : ($setupIntent->customer->id ?? null),
        ]);

        return response()->json([
            'mode'         => 'subscription',
            'attemptId'    => $attemptId,
            'pledgeId'     => $pledge->id,
            'clientSecret' => $setupIntent->client_secret,
        ]);
    }

    public function complete(Request $request)
    {
        // CANARY
        Log::error('CANARY: DonationsController@complete reached', [
            'time' => now()->toDateTimeString(),
        ]);

        $data = $request->validate([
            'attempt_id' => ['nullable', 'string', 'max:80'],

            'mode'           => ['required', 'in:payment,subscription'],
            'transaction_id' => ['nullable', 'integer', 'required_if:mode,payment'],
            'pledge_id'      => ['nullable', 'integer', 'required_if:mode,subscription'],

            'payment_intent_id' => ['nullable', 'string'],
            'charge_id'         => ['nullable', 'string'],
            // ✅ CHANGE: required for subscription completion
            'payment_method_id' => ['nullable', 'string', 'required_if:mode,subscription'],
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

        $this->sdbg('complete: validated', [
            'mode'           => $data['mode'],
            'attempt_id'     => $attemptId,
            'transaction_id' => $data['transaction_id'] ?? null,
            'pledge_id'      => $data['pledge_id'] ?? null,
            'pi'             => $data['payment_intent_id'] ?? null,
            'pm'             => $data['payment_method_id'] ?? null,
            'charge'         => $data['charge_id'] ?? null,
        ]);

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
        // One-time completion
        // ---------------------------------------------------------------------
        if ($data['mode'] === 'payment') {
            $transaction = Transaction::findOrFail($data['transaction_id']);

            $this->sdbg('complete(payment): tx before', $this->txSnap($transaction));

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

                    $this->sdbg('complete(payment): PI retrieved', [
                        'pi'        => $piId,
                        'pi_status' => $pi->status ?? null,
                        'customer'  => $transaction->customer_id,
                        'pm'        => $transaction->payment_method_id,
                        'charge'    => $chargeId,
                    ]);
                }

                if ($chargeId) {
                    $charge = $this->stripe->retrieveCharge($chargeId);

                    $receiptUrl ??= $charge->receipt_url ?? null;

                    $transaction->payer_email ??= data_get($charge, 'billing_details.email');
                    $transaction->payer_name  ??= data_get($charge, 'billing_details.name');

                    $card = data_get($charge, 'payment_method_details.card');

                    $meta = is_array($transaction->metadata)
                        ? $transaction->metadata
                        : ((array) json_decode((string) $transaction->metadata, true) ?: []);

                    if ($card) {
                        // include exp month/year
                        $meta = array_merge($meta, array_filter([
                            'card_brand'     => $card->brand ?? null,
                            'card_last4'     => $card->last4 ?? null,
                            'card_country'   => $card->country ?? null,
                            'card_funding'   => $card->funding ?? null,
                            'card_exp_month' => $card->exp_month ?? null,
                            'card_exp_year'  => $card->exp_year ?? null,
                        ]));
                    }

                    $transaction->metadata = $meta;

                    $this->sdbg('complete(payment): Charge retrieved', [
                        'charge'      => $chargeId,
                        'receipt_url' => $receiptUrl,
                    ]);
                }
            } catch (Throwable $e) {
                Log::warning('Donation complete: Stripe enrichment failed', [
                    'transaction_id' => $transaction->id,
                    'payment_intent' => $piId,
                    'error'          => $e->getMessage(),
                ]);

                $this->sdbg('complete(payment): enrichment failed', [
                    'tx_id' => $transaction->id,
                    'error' => $e->getMessage(),
                ]);
            }

            $transaction->fill([
                'charge_id'   => $chargeId ?? $transaction->charge_id,
                'receipt_url' => $receiptUrl ?? $transaction->receipt_url,
                'status'      => 'succeeded',
                'paid_at'     => $transaction->paid_at ?? now(),
            ])->save();

            $this->sdbg('complete(payment): tx after', $this->txSnap($transaction));

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

        $this->sdbg('complete(subscription): pledge before', $this->pledgeSnap($pledge));

        $attemptId = $data['attempt_id']
            ?? $pledge->attempt_id
            ?? (string) Str::uuid();

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
            $placeholder = Transaction::create([
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

            $this->sdbg('complete(subscription): placeholder created', $this->txSnap($placeholder));
        } else {
            $this->sdbg('complete(subscription): placeholder exists', $this->txSnap($placeholder));
        }

        $pmId = (string) ($data['payment_method_id'] ?? '');
        $this->sdbg('complete(subscription): calling createSubscriptionForPledge', [
            'pledge_id' => $pledge->id,
            'pm_id'     => $pmId,
        ]);

        $subscription = $this->stripe->createSubscriptionForPledge($pledge, $pmId);

        $pledge->refresh();

        if ((bool) config('services.stripe.debug_state', false)) {
            try {
                $itemPeriodStart = data_get($subscription, 'items.data.0.current_period_start');
                $itemPeriodEnd   = data_get($subscription, 'items.data.0.current_period_end');

                $latestInvoice   = data_get($subscription, 'latest_invoice');
                $latestInvoiceId = is_string($latestInvoice)
                    ? $latestInvoice
                    : (is_object($latestInvoice) ? ($latestInvoice->id ?? null) : null);

                $this->sdbg('complete(subscription): returned from StripeService', [
                    'subscription_id'     => data_get($subscription, 'id'),
                    'subscription_status' => data_get($subscription, 'status'),
                    'item_period_start'   => $itemPeriodStart,
                    'item_period_end'     => $itemPeriodEnd,
                    'latest_invoice'      => $latestInvoiceId,
                ]);

                $this->sdbg('complete(subscription): pledge after StripeService', $this->pledgeSnap($pledge));
            } catch (Throwable $e) {
                // Debug should never take down the request (or tests).
                $this->sdbg('complete(subscription): debug extract failed', [
                    'error'              => $e->getMessage(),
                    'subscription_class' => is_object($subscription) ? get_class($subscription) : gettype($subscription),
                ]);
            }
        }

        $request->session()->put('pledge_thankyou_id', $pledge->id);

        if ($request->wantsJson()) {
            return response()->json(['redirect' => route('donations.thankyou-subscription')]);
        }

        return redirect()->route('donations.thankyou-subscription')->with('success', 'Thank you for your monthly pledge!');
    }

    /**
     * Stripe redirect return
     */
    public function stripeReturn(Request $request)
    {
        $piId = $request->query('payment_intent');
        $siId = $request->query('setup_intent');

        $this->sdbg('stripeReturn: hit', [
            'payment_intent' => $piId,
            'setup_intent'   => $siId,
        ]);

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

                    // write card metadata here too (including exp month/year)
                    $card = data_get($charge, 'payment_method_details.card');

                    $meta = is_array($tx->metadata)
                        ? $tx->metadata
                        : ((array) json_decode((string) $tx->metadata, true) ?: []);

                    if (! isset($meta['frequency'])) {
                        $meta['frequency'] = 'one_time';
                    }

                    if ($card) {
                        $meta = array_merge($meta, array_filter([
                            'card_brand'     => $card->brand ?? null,
                            'card_last4'     => $card->last4 ?? null,
                            'card_country'   => $card->country ?? null,
                            'card_funding'   => $card->funding ?? null,
                            'card_exp_month' => $card->exp_month ?? null,
                            'card_exp_year'  => $card->exp_year ?? null,
                        ]));
                    }

                    $tx->metadata = $meta;
                }

                $tx->save();

                $request->session()->put('transaction_thankyou_id', $tx->id);

                return redirect()->route('donations.thankyou');
            } catch (Throwable $e) {
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

                try {
                    $pm = $this->stripe->retrievePaymentMethod($pmId);
                    $pledge->donor_email ??= data_get($pm, 'billing_details.email');
                    $pledge->donor_name  ??= data_get($pm, 'billing_details.name');
                    $pledge->save();
                } catch (Throwable $e) {
                    Log::info('stripeReturn: could not read payment method billing details', [
                        'setup_intent' => $siId,
                        'payment_method' => $pmId,
                        'error' => $e->getMessage(),
                    ]);
                }

                $this->stripe->createSubscriptionForPledge($pledge, $pmId);

                $request->session()->put('pledge_thankyou_id', $pledge->id);

                return redirect()->route('donations.thankyou-subscription');
            } catch (Throwable $e) {
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

    // -------------------------------------------------------------------------
    // DEBUG HELPERS
    // -------------------------------------------------------------------------

    protected function sdbg(string $message, array $context = []): void
    {
        if (! (bool) config('services.stripe.debug_state', false)) {
            return;
        }

        Log::error('[STRIPE-DBG] ' . $message, $context);
    }

    protected function pledgeSnap(Pledge $pledge): array
    {
        return [
            'pledge_id'               => $pledge->id,
            'attempt_id'              => $pledge->attempt_id,
            'user_id'                 => $pledge->user_id,
            'status'                  => $pledge->status,
            'stripe_customer_id'      => $pledge->stripe_customer_id,
            'stripe_subscription_id'  => $pledge->stripe_subscription_id,
            'stripe_price_id'         => $pledge->stripe_price_id,
            'setup_intent_id'         => $pledge->setup_intent_id,
            'latest_invoice_id'       => $pledge->latest_invoice_id,
            'latest_payment_intent_id'=> $pledge->latest_payment_intent_id,
            'current_period_start'    => optional($pledge->current_period_start)->toDateTimeString(),
            'current_period_end'      => optional($pledge->current_period_end)->toDateTimeString(),
            'last_pledge_at'          => optional($pledge->last_pledge_at)->toDateTimeString(),
            'next_pledge_at'          => optional($pledge->next_pledge_at)->toDateTimeString(),
            'updated_at'              => optional($pledge->updated_at)->toDateTimeString(),
        ];
    }

    protected function txSnap(Transaction $tx): array
    {
        return [
            'tx_id'             => $tx->id,
            'attempt_id'        => $tx->attempt_id,
            'user_id'           => $tx->user_id,
            'pledge_id'         => $tx->pledge_id,
            'type'              => $tx->type,
            'status'            => $tx->status,
            'payment_intent_id' => $tx->payment_intent_id,
            'subscription_id'   => $tx->subscription_id,
            'charge_id'         => $tx->charge_id,
            'customer_id'       => $tx->customer_id,
            'payment_method_id' => $tx->payment_method_id,
            'amount_cents'      => $tx->amount_cents,
            'currency'          => $tx->currency,
            'paid_at'           => optional($tx->paid_at)->toDateTimeString(),
            'updated_at'        => optional($tx->updated_at)->toDateTimeString(),
        ];
    }
}

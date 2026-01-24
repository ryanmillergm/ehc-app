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
use App\Support\Stripe\TransactionInvoiceLinker;
use App\Support\Stripe\TransactionResolver;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;

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
        try {
            // CANARY
            Log::error('CANARY: DonationsController@start reached', [
                'time' => now()->toDateTimeString(),
            ]);

            $data = $request->validate([
                'attempt_id' => ['nullable', 'string', 'max:80'],
                'amount'     => ['required', 'numeric', 'min:1'],
                'frequency'  => ['required', 'in:one_time,monthly'],

                // allow donor info from widget (guest flow)
                'donor'        => ['nullable', 'array'],
                'donor.email'  => ['nullable', 'email'],
                'donor.name'   => ['nullable', 'string', 'max:255'],
            ]);

            $user = Auth::user();

            $clientAttemptId = $data['attempt_id'] ?? null;
            $request->session()->put('donation_attempt_id', $clientAttemptId);

            $amountCents     = (int) round($data['amount'] * 100);
            $frequency       = $data['frequency'];

            $payloadDonorEmail = data_get($data, 'donor.email');
            $payloadDonorName  = data_get($data, 'donor.name');

            $donor = [
                // Prefer payload; fallback to authed user
                'email' => $payloadDonorEmail ?: $user?->email,
                'name'  => $payloadDonorName  ?: ($user ? trim((string) $user->name) : null),
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
                        'metadata' => [
                            'frequency' => 'one_time',
                            'stage'     => 'started',
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

                // StripeService may already persist PI id/status on the tx (preferred).
                // If it didn't, only set it if it won't violate the unique constraint.
                if (empty($transaction->payment_intent_id) && ! empty($pi->id)) {
                    $already = Transaction::query()
                        ->where('payment_intent_id', $pi->id)
                        ->whereKeyNot($transaction->id)
                        ->exists();

                    if (! $already) {
                        $transaction->update(['payment_intent_id' => $pi->id]);
                    } else {
                        $this->sdbg('start(one_time): PI id already claimed; leaving tx.payment_intent_id null', [
                            'tx_id' => $transaction->id,
                            'pi'    => $pi->id,
                        ], 'warning');
                    }
                }

                $this->sdbg('start(one_time): PI ready', [
                    'tx_id'             => $transaction->id,
                    'attempt_id'        => $attemptId,
                    'payment_intent_id' => $pi->id ?? null,
                    'pi_status'         => $pi->status ?? null,
                ]);

                return response()->json([
                    'mode' => 'payment',

                    // snake_case (tests / backend)
                    'attempt_id'        => $attemptId,
                    'transaction_id'    => $transaction->id,
                    'payment_intent_id' => $pi->id ?? null,
                    'client_secret'     => $pi->client_secret ?? null,

                    // camelCase (frontend/back-compat)
                    'attemptId'       => $attemptId,
                    'transactionId'   => $transaction->id,
                    'paymentIntentId' => $pi->id ?? null,
                    'clientSecret'    => $pi->client_secret ?? null,
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
                        'stage'     => 'started',
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
                'mode' => 'subscription',

                // snake_case (tests / backend)
                'attempt_id'      => $attemptId,
                'pledge_id'       => $pledge->id,
                'setup_intent_id' => $setupIntent->id ?? null,
                'client_secret'   => $setupIntent->client_secret ?? null,

                // camelCase (frontend/back-compat)
                'attemptId'     => $attemptId,
                'pledgeId'      => $pledge->id,
                'setupIntentId' => $setupIntent->id ?? null,
                'clientSecret'  => $setupIntent->client_secret ?? null,
            ]);
        } catch (Throwable $e) {
            return $this->donationFailure(
                $request,
                'We could not start your donation. Please try again.',
                $e,
                [
                    'attempt_id' => $request->input('attempt_id'),
                    'route'      => 'donations.start',
                ]
            );
        }
    }


    public function complete(Request $request)
    {
        try {
            // CANARY
            Log::error('CANARY: DonationsController@complete reached', [
                'time' => now()->toDateTimeString(),
            ]);

            $data = $request->validate([
                'attempt_id' => ['nullable', 'string', 'max:80'],

                'mode'           => ['required', 'in:payment,subscription'],
                'transaction_id' => ['nullable', 'integer', 'required_if:mode,payment'],
                'pledge_id'      => ['nullable', 'integer', 'required_if:mode,subscription'],

                'payment_intent_id'  => ['nullable', 'string'],
                'charge_id'          => ['nullable', 'string'],
                'payment_method_id'  => ['nullable', 'string', 'required_if:mode,subscription'],
                'receipt_url'        => ['nullable', 'url'],

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

                $transaction->setStage('details_submitted', save: true);

                $this->sdbg('complete(payment): tx before', $this->txSnap($transaction));

                if ($attemptId && empty($transaction->attempt_id)) {
                    $transaction->attempt_id = $attemptId;
                }

                if ($user = Auth::user()) {
                    $transaction->user_id ??= $user->id;
                }

                // Donor-confirmed data: overwrite
                if (! empty($data['donor_email'])) {
                    $transaction->payer_email = $data['donor_email'];
                }
                if ($fullName !== '') {
                    $transaction->payer_name = $fullName;
                }
                $transaction->save();

                // IMPORTANT:
                // - JSON clients (widget) want enrichment: retrieve PI + Charge and add card metadata.
                // - Form posts (feature tests) can be "offline": if they provide IDs/receipt, we do NOT call Stripe.
                $isJsonFlow = $request->wantsJson();

                $paymentIntentId = (string) ($request->input('payment_intent_id') ?: $transaction->payment_intent_id);
                $chargeIdInput   = (string) ($request->input('charge_id') ?: $transaction->charge_id);
                $pmIdInput       = (string) ($request->input('payment_method_id') ?: $transaction->payment_method_id);
                $receiptUrlInput = (string) ($request->input('receipt_url') ?: $transaction->receipt_url);

                if ($paymentIntentId !== '' && empty($transaction->payment_intent_id)) {
                    $already = Transaction::query()
                        ->where('payment_intent_id', $paymentIntentId)
                        ->whereKeyNot($transaction->id)
                        ->exists();

                    if (! $already) {
                        $transaction->payment_intent_id = $paymentIntentId;
                    }
                }

                // Base fields always set from the request when present (non-stomp)
                if ($chargeIdInput !== '' && empty($transaction->charge_id)) {
                    $transaction->charge_id = $chargeIdInput;
                }
                if ($pmIdInput !== '' && empty($transaction->payment_method_id)) {
                    $transaction->payment_method_id = $pmIdInput;
                }
                if ($receiptUrlInput !== '' && empty($transaction->receipt_url)) {
                    $transaction->receipt_url = $receiptUrlInput;
                }

                $transaction->save();

                if ($isJsonFlow) {
                    $pi = $this->stripe->retrievePaymentIntent($paymentIntentId);

                    $transaction->status = $pi->status ?? $transaction->status;

                    // If the PaymentIntent isn't paid yet, return status info to the widget.
                    if (($pi->status ?? null) !== 'succeeded') {
                        $transaction->save();

                        return response()->json([
                            'ok'     => false,
                            'status' => $transaction->status,
                        ]);
                    }

                    // Mark paid (authoritative finalization happens in StripeService)
                    $transaction->paid_at = $transaction->paid_at ?: now();
                    $transaction->save();

                    // Optional enrichment from Charge (best-effort)
                    $chargeId = $this->extractId($pi->latest_charge ?? null) ?: $transaction->charge_id;

                    if ($chargeId) {
                        try {
                            $charge = $this->stripe->retrieveCharge($chargeId);

                            if (empty($transaction->charge_id) && ! empty($charge->id)) {
                                $transaction->charge_id = $charge->id;
                            }
                            if (empty($transaction->customer_id)) {
                                $transaction->customer_id = $this->extractId($charge->customer ?? null);
                            }
                            if (empty($transaction->payment_method_id)) {
                                $transaction->payment_method_id = $this->extractId($charge->payment_method ?? null);
                            }

                            if (empty($transaction->amount_cents)) {
                                $transaction->amount_cents = (int) (data_get($charge, 'amount') ?? 0);
                            }
                            if (empty($transaction->currency)) {
                                $transaction->currency = (string) (data_get($charge, 'currency') ?? 'usd');
                            }
                            if (empty($transaction->receipt_url)) {
                                $transaction->receipt_url = (string) (data_get($charge, 'receipt_url') ?? '');
                            }

                            if (empty($transaction->payer_email)) {
                                $transaction->payer_email = data_get($charge, 'billing_details.email');
                            }
                            if (empty($transaction->payer_name)) {
                                $transaction->payer_name = data_get($charge, 'billing_details.name');
                            }

                            $cardMeta = array_filter([
                                'card_brand'     => data_get($charge, 'payment_method_details.card.brand'),
                                'card_last4'     => data_get($charge, 'payment_method_details.card.last4'),
                                'card_country'   => data_get($charge, 'payment_method_details.card.country'),
                                'card_funding'   => data_get($charge, 'payment_method_details.card.funding'),
                                'card_exp_month' => data_get($charge, 'payment_method_details.card.exp_month'),
                                'card_exp_year'  => data_get($charge, 'payment_method_details.card.exp_year'),
                            ], static fn ($v) => $v !== null && $v !== '');

                            $transaction->metadata = $this->mergeMetadata($transaction->metadata, array_merge([
                                'frequency' => 'one_time',
                            ], $cardMeta));

                            $transaction->save();
                        } catch (Throwable) {
                            $transaction->metadata = $this->mergeMetadata($transaction->metadata, [
                                'frequency' => 'one_time',
                            ]);
                            $transaction->save();
                        }
                    } else {
                        $transaction->metadata = $this->mergeMetadata($transaction->metadata, [
                            'frequency' => 'one_time',
                        ]);
                        $transaction->save();
                    }

                    $this->stripe->finalizeTransactionFromPaymentIntent($transaction, $pi);
                    $transaction->refresh();

                    $request->session()->put('transaction_thankyou_id', $transaction->id);
                    if ($attemptId) {
                        $request->session()->put('donation_attempt_id', $attemptId);
                    }

                    return response()->json([
                        'ok'       => true,
                        'redirect' => route('donations.thankyou'),
                    ]);
                }

                // Offline completion (form posts)
                $transaction->status  = 'succeeded';
                $transaction->paid_at = $transaction->paid_at ?: now();
                $transaction->source  = $transaction->source ?: 'donation_widget';

                $transaction->metadata = $this->mergeMetadata($transaction->metadata, [
                    'frequency' => 'one_time',
                    'stage'     => 'complete_payment_offline',
                ]);

                $transaction->save();

                $request->session()->put('transaction_thankyou_id', $transaction->id);
                if ($attemptId) {
                    $request->session()->put('donation_attempt_id', $attemptId);
                }

                return redirect()->route('donations.thankyou', ['attempt_id' => $transaction->attempt_id]);
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

            // Donor-confirmed data: overwrite
            if (! empty($data['donor_email'])) {
                $pledge->donor_email = $data['donor_email'];
            }
            if ($fullName !== '') {
                $pledge->donor_name = $fullName;
            }
            $pledge->save();

            // Find-or-create the subscription_initial placeholder transaction for this pledge/attempt.
            $placeholder = Transaction::query()
                ->where('pledge_id', $pledge->id)
                ->where('source', 'donation_widget')
                ->where('type', 'subscription_initial')
                ->where('attempt_id', $attemptId)
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

            $placeholder->setStage('subscription_creation', save: true);

            $pmId = (string) ($data['payment_method_id'] ?? '');
            $this->sdbg('complete(subscription): calling createSubscriptionForPledge', [
                'pledge_id' => $pledge->id,
                'pm_id'     => $pmId,
            ]);

            $subscription = $this->stripe->createSubscriptionForPledge($pledge, $pmId);

            // StripeService syncs authoritative IDs onto the pledge (invoice/pi/sub/etc.)
            $pledge->refresh();

            // CRITICAL: reload tx after the service, so we don't overwrite service writes with stale in-memory values.
            $placeholder->refresh();

            /** @var TransactionResolver $resolver */
            $resolver = app(TransactionResolver::class);

            /** @var TransactionInvoiceLinker $invoiceLinker */
            $invoiceLinker = app(TransactionInvoiceLinker::class);

            // ------------------------------
            // Robust ID extraction (Stripe SDK objects + strings)
            // ------------------------------
            $subId = $pledge->stripe_subscription_id
                ?: $this->extractId($subscription->id ?? null);

            $latestInvoice = $subscription->latest_invoice ?? null;

            $invoiceId = $pledge->latest_invoice_id
                ?: $this->extractId($latestInvoice);

            $latestInvoicePi = is_object($latestInvoice) ? ($latestInvoice->payment_intent ?? null) : null;

            $piId = $pledge->latest_payment_intent_id
                ?: $this->extractId($latestInvoicePi);

            $hostedInvoiceUrl = is_object($latestInvoice)
                ? ($latestInvoice->hosted_invoice_url ?? null)
                : null;

            // Prefer the canonical transaction for this pledge+invoice (webhook may have already created it).
            $resolvedTx = $resolver->resolveForInvoice($pledge, $invoiceId, $piId);
            if ($resolvedTx) {
                $placeholder = $resolvedTx;
            }

            // Ensure we never violate the (pledge_id, stripe_invoice_id) unique constraint.
            $placeholder = $invoiceLinker->adoptOwnerIfInvoiceClaimed($placeholder, (int) $pledge->id, $invoiceId);

            // Claim invoice id safely (returns canonical owner if already owned elsewhere)
            $placeholder = $invoiceLinker->claimInvoiceId($placeholder, (int) $pledge->id, $invoiceId);

            // ✅ NOW we know $placeholder is canonical: enrich without stomping.
            if (empty($placeholder->subscription_id) && $subId) {
                $placeholder->subscription_id = $subId;
            }

            if (empty($placeholder->payment_intent_id) && $piId) {
                $placeholder = $invoiceLinker->claimPaymentIntentId($placeholder, $piId);
            }

            // Charge id from invoice (if expanded)
            if (empty($placeholder->charge_id) && is_object($latestInvoice)) {
                $chFromInvoice =
                    $this->extractId($latestInvoice->charge ?? null)
                    ?: $this->extractId(is_object($latestInvoice->payment_intent ?? null) ? ($latestInvoice->payment_intent->latest_charge ?? null) : null);

                if ($chFromInvoice) {
                    $placeholder = $invoiceLinker->claimChargeId($placeholder, $chFromInvoice);
                }
            }

            // Best-effort receipt URL
            if (empty($placeholder->receipt_url) && is_string($hostedInvoiceUrl) && $hostedInvoiceUrl !== '') {
                $placeholder->receipt_url = $hostedInvoiceUrl;
            }

            // Leave as pending; invoice/webhooks flip to succeeded.
            if ($placeholder->status !== 'succeeded') {
                $placeholder->status  = 'pending';
                $placeholder->paid_at = null;
            }

            $placeholder->payer_email ??= $pledge->donor_email;
            $placeholder->payer_name  ??= $pledge->donor_name;

            $meta = is_array($placeholder->metadata)
                ? $placeholder->metadata
                : (json_decode((string) $placeholder->metadata, true) ?: []);

            $meta['stage'] = $meta['stage'] ?? 'subscription_created';
            if ($invoiceId) {
                $meta['stripe_invoice_id'] = $meta['stripe_invoice_id'] ?? $invoiceId;
            }
            if ($subId) {
                $meta['stripe_subscription_id'] = $meta['stripe_subscription_id'] ?? $subId;
            }

            $placeholder->metadata = $meta;
            $placeholder->save();

            $this->sdbg('complete(subscription): canonical tx saved after enrichment', $this->txSnap($placeholder));

            $placeholder->setStage('subscription_created', save: true);

            // Pledge stage just means “we’re waiting for Stripe invoice event”
            $pledge->setStage('awaiting_invoice', save: true);
            $pledge->refresh();

            $redirectUrl = app('router')->has('donations.thankyou-subscription')
                ? route('donations.thankyou-subscription')
                : url('/donations/thank-you-subscription');

            $request->session()->put('pledge_thankyou_id', $pledge->id);

            if ($request->wantsJson()) {
                return response()->json(['redirect' => $redirectUrl], 200);
            }

            return redirect()
                ->to($redirectUrl)
                ->with('success', 'Thank you for your monthly pledge!');

        } catch (ValidationException $e) {
            // ✅ CRITICAL: do not swallow validation (tests expect 422, and clients deserve it)
            throw $e;

        } catch (ModelNotFoundException $e) {
            // Let Laravel render a 404 (or JSON 404 for postJson)
            throw $e;

        } catch (Throwable $e) {
            return $this->donationFailure(
                $request,
                'We could not complete your donation. Please try again.',
                $e,
                [
                    'attempt_id' => $request->input('attempt_id'),
                    'mode'       => $request->input('mode'),
                    'route'      => 'donations.complete',
                ]
            );
        }
    }

    /**
     * Stripe redirect return
     */
    public function stripeReturn(Request $request)
    {
        try {
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

                        if (is_object($card)) {
                            $meta = array_merge($meta, array_filter([
                                'card_brand'     => $card->brand ?? null,
                                'card_last4'     => $card->last4 ?? null,
                                'card_country'   => $card->country ?? null,
                                'card_funding'   => $card->funding ?? null,
                                'card_exp_month' => $card->exp_month ?? null,
                                'card_exp_year'  => $card->exp_year ?? null,
                            ]));
                        } elseif (is_array($card)) {
                            $meta = array_merge($meta, array_filter([
                                'card_brand'     => $card['brand'] ?? null,
                                'card_last4'     => $card['last4'] ?? null,
                                'card_country'   => $card['country'] ?? null,
                                'card_funding'   => $card['funding'] ?? null,
                                'card_exp_month' => $card['exp_month'] ?? null,
                                'card_exp_year'  => $card['exp_year'] ?? null,
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

                $sessionAttempt = $request->session()->get('donation_attempt_id');

                if (! $sessionAttempt || ($pledge && $pledge->attempt_id !== $sessionAttempt)) {
                    // Hard stop: return URL hit without the browser that started the flow
                    Log::warning('stripeReturn: attempt mismatch', [
                        'session_attempt' => $sessionAttempt,
                        'pledge_attempt' => $pledge?->attempt_id,
                        'setup_intent' => $siId,
                    ]);

                    return redirect()->route('donations.show')
                        ->withErrors('This return link is no longer valid. Please restart your pledge.');
                }

                if ($pledge?->stripe_subscription_id) {
                    // Already created — idempotent exit
                    $request->session()->put('pledge_thankyou_id', $pledge->id);
                    return redirect()->route('donations.thankyou-subscription');
                }

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
        } catch (Throwable $e) {
            Log::error('[STRIPE-RETURN-FAIL]', [
                'payment_intent' => $request->query('payment_intent'),
                'setup_intent'   => $request->query('setup_intent'),
                'error'          => $e->getMessage(),
            ]);

            return redirect()
                ->route('donations.show')
                ->withErrors('We could not finalize your donation. Please try again.');
        }
    }

    public function thankYou(Request $request)
    {
        $transactionId = $request->session()->pull('transaction_thankyou_id');

        if (! $transactionId) {
            abort(404);
        }

        $transaction = Transaction::findOrFail($transactionId);

        return view('donations.thankyou', [
            'transaction' => $transaction,
        ]);
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

    protected function sdbg(string $message, array $context = [], string $level = 'error'): void
    {
        if (! (bool) config('services.stripe.debug_state', false)) {
            return;
        }

        Log::log($level, '[STRIPE-DBG] ' . $message, $context);
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

    // ---------------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------------

    protected function extractId($value): ?string
    {
        if (is_string($value)) {
            return $value;
        }

        if (is_object($value)) {
            return $value->id ?? null;
        }

        return null;
    }

    protected function mergeMetadata($existing, array $extra): array
    {
        $base = [];

        if (is_array($existing)) {
            $base = $existing;
        } elseif (is_string($existing) && $existing !== '') {
            $decoded = json_decode($existing, true);
            if (is_array($decoded)) {
                $base = $decoded;
            }
        }

        return array_merge($base, $extra);
    }

    protected function donationFailure(Request $request, string $publicMessage, Throwable $e, array $context = [])
    {
        Log::error('[DONATION-FAIL]', array_merge($context, [
            'exception' => get_class($e),
            'message'   => $e->getMessage(),
            'trace'     => $e->getTraceAsString(),
        ]));

        if ($request->wantsJson()) {
            return response()->json([
                'ok'      => false,
                'error'   => $publicMessage,
            ], 500);
        }

        return redirect()
            ->route('donations.show')
            ->withErrors($publicMessage);
    }
}
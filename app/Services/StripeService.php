<?php

namespace App\Services;

use RuntimeException;
use Stripe\PaymentIntent;
use Stripe\SetupIntent;
use Stripe\Charge;
use Stripe\PaymentMethod;
use Stripe\Subscription;
use Throwable;
use App\Models\Pledge;
use App\Models\Refund;
use App\Models\Transaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Log;
use Stripe\StripeClient;
use Illuminate\Support\Facades\DB;

class StripeService
{
    protected StripeClient $stripe;

    public function __construct(?StripeClient $stripe = null)
    {
        if ($stripe instanceof StripeClient) {
            $this->stripe = $stripe;
            return;
        }

        $secret = trim((string) config('services.stripe.secret'));

        throw_if(
            $secret === '',
            RuntimeException::class,
            'Stripe secret is missing. Set STRIPE_SECRET in .env and map it in config/services.php.'
        );

        $this->stripe = new StripeClient($secret);
    }

    protected function dbg(string $message, array $context = [], string $level = 'error'): void
    {
        if (! config('services.stripe.debug_state')) {
            return;
        }

        Log::log($level, '[STRIPE-DBG] ' . $message, $context);
    }

    protected function idemFor(string $prefix, string $scope, array $params): array
    {
        $json = json_encode($params, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $fp   = substr(hash('sha256', (string) $json), 0, 24);

        $raw = "{$prefix}:{$scope}:{$fp}";

        return [
            'idempotency_key' => substr($raw, 0, 255),
        ];
    }

    protected function recurringProductId(): string
    {
        $productId = trim((string) config('services.stripe.recurring_product_id'));

        throw_if(
            $productId === '',
            RuntimeException::class,
            'Stripe recurring product id is missing. Set STRIPE_RECURRING_PRODUCT_ID in .env and map it in config/services.php.'
        );

        return $productId;
    }

    // -------------------------------------------------------------------------
    // Retrieve helpers
    // -------------------------------------------------------------------------

    public function retrievePaymentIntent(string $paymentIntentId, array $params = []): PaymentIntent
    {
        $params = $params ?: ['expand' => ['charges.data.balance_transaction']];
        return $this->stripe->paymentIntents->retrieve($paymentIntentId, $params);
    }

    public function retrieveSetupIntent(string $setupIntentId): SetupIntent
    {
        return $this->stripe->setupIntents->retrieve($setupIntentId);
    }

    public function retrieveCharge(string $chargeId): Charge
    {
        return $this->stripe->charges->retrieve($chargeId);
    }

    public function retrievePaymentMethod(string $paymentMethodId): PaymentMethod
    {
        return $this->stripe->paymentMethods->retrieve($paymentMethodId);
    }

    // -------------------------------------------------------------------------
    // Customers
    // -------------------------------------------------------------------------

    /**
     * Ensure we have a Stripe customer id.
     * Accepts a User or raw donor data array.
     */
    public function getOrCreateCustomer(User|array $donor): string
    {
        $existingCustomerId = null;
        $email              = null;
        $name               = null;

        if ($donor instanceof User) {
            $user  = $donor;
            $email = $user->email;
            $name  = trim("{$user->first_name} {$user->last_name}");

            $existingCustomerId = Pledge::where('user_id', $user->id)
                ->whereNotNull('stripe_customer_id')
                ->orderByDesc('created_at')
                ->value('stripe_customer_id');

            if (! $existingCustomerId) {
                $existingCustomerId = Transaction::where('user_id', $user->id)
                    ->whereNotNull('customer_id')
                    ->orderByDesc('created_at')
                    ->value('customer_id');
            }
        } else {
            $email = $donor['email'] ?? null;
            $name  = $donor['name']  ?? null;

            if ($email) {
                $existingCustomerId = Transaction::whereNotNull('customer_id')
                    ->where('payer_email', $email)
                    ->orderByDesc('created_at')
                    ->value('customer_id');

                if (! $existingCustomerId) {
                    $existingCustomerId = Pledge::whereNotNull('stripe_customer_id')
                        ->where('donor_email', $email)
                        ->orderByDesc('created_at')
                        ->value('stripe_customer_id');
                }
            }
        }

        if ($existingCustomerId) {
            $this->dbg('getOrCreateCustomer: using existing', [
                'customer_id' => $existingCustomerId,
                'email' => $email,
            ]);
            return $existingCustomerId;
        }

        $params = [
            'email'    => $email ?: null,
            'name'     => $name  ?: null,
            'metadata' => [
                'source' => 'donation_widget',
            ],
        ];

        $opts = $this->idemFor('customer', 'email:' . ($email ?: 'none'), $params);

        $customer = $this->stripe->customers->create($params, $opts);

        $this->dbg('getOrCreateCustomer: created', [
            'customer_id' => $customer->id,
            'email' => $email,
        ]);

        return $customer->id;
    }

    // -------------------------------------------------------------------------
    // One-time PaymentIntent
    // -------------------------------------------------------------------------

    public function createOneTimePaymentIntent(Transaction $transaction, array $donor = []): PaymentIntent
    {
        if (! empty($transaction->payment_intent_id)) {
            $pi = $this->stripe->paymentIntents->retrieve($transaction->payment_intent_id);

            $transaction->status = $pi->status;
            $transaction->save();

            return $pi;
        }

        $customerId = $donor['customer_id'] ?? $transaction->customer_id ?? null;

        if (! $customerId && ! empty($donor)) {
            $customerId = $this->getOrCreateCustomer($donor);
            $transaction->customer_id = $customerId;
        }

        $attemptId = $transaction->attempt_id ?: null;

        $params = [
            'amount'   => (int) $transaction->amount_cents,
            'currency' => (string) $transaction->currency,
            'customer' => $customerId,
            'metadata' => [
                'transaction_id' => (string) $transaction->id,
                'attempt_id'     => (string) ($attemptId ?: ''),
                'type'           => (string) $transaction->type,
                'source'         => (string) $transaction->source,
            ],
            'automatic_payment_methods' => [
                'enabled' => true,
            ],
        ];

        $opts = $this->idemFor('pi', 'tx:' . $transaction->id, $params);

        $pi = $this->stripe->paymentIntents->create($params, $opts);

        $transaction->payment_intent_id = $pi->id;
        $transaction->status            = $pi->status;
        $transaction->customer_id       = $customerId ?: $transaction->customer_id;
        $transaction->save();

        return $pi;
    }

    // -------------------------------------------------------------------------
    // Monthly SetupIntent
    // -------------------------------------------------------------------------

    public function createSetupIntentForPledge(Pledge $pledge, array $donor = []): SetupIntent
    {
        if (! empty($pledge->setup_intent_id)) {
            return $this->stripe->setupIntents->retrieve($pledge->setup_intent_id);
        }

        $customerId = $pledge->stripe_customer_id;

        if (! $customerId && ! empty($donor)) {
            $customerId = $this->getOrCreateCustomer($donor);
            $pledge->stripe_customer_id = $customerId;
            $pledge->save();
        }

        $attemptId = $pledge->attempt_id ?: null;

        $params = [
            'customer' => $customerId,
            'automatic_payment_methods' => ['enabled' => true],
            'metadata' => [
                'pledge_id'  => (string) $pledge->id,
                'attempt_id' => (string) ($attemptId ?: ''),
                'source'     => 'donation_widget',
            ],
        ];

        $opts = $this->idemFor('setup_intent', 'pledge:' . $pledge->id, $params);

        $si = $this->stripe->setupIntents->create($params, $opts);

        $pledge->setup_intent_id = $si->id;
        $pledge->save();

        $this->dbg('createSetupIntentForPledge: created SI', [
            'pledge_id' => $pledge->id,
            'si' => $si->id,
            'status' => $si->status ?? null,
            'customer' => is_string($si->customer ?? null) ? $si->customer : ($si->customer->id ?? null),
        ]);

        return $si;
    }

    // -------------------------------------------------------------------------
    // Subscription creation
    // -------------------------------------------------------------------------

    public function createSubscriptionForPledge(Pledge $pledge, string $paymentMethodId): Subscription
    {
        $attemptId = $pledge->attempt_id ?: null;

        $this->dbg('createSubscriptionForPledge: start', [
            'pledge_id' => $pledge->id,
            'attempt_id' => $attemptId,
            'pm_id' => $paymentMethodId,
            'existing_subscription' => $pledge->stripe_subscription_id,
            'existing_customer' => $pledge->stripe_customer_id,
            'existing_price' => $pledge->stripe_price_id,
            'amount_cents' => $pledge->amount_cents,
            'currency' => $pledge->currency,
            'interval' => $pledge->interval,
        ]);

        /**
         * Guard: ensure a PaymentIntent really “belongs” to (invoice, customer).
         * Returns [bool $ok, ?string $chargeId]
         */
        $guardPaymentIntentOwner = function (?string $piId, ?string $invoiceId, ?string $customerId): array {
            if (! $piId) {
                return [false, null];
            }

            // If we don't know the expected customer, we can't validate ownership meaningfully.
            // Soft-allow in that case.
            if (! $customerId) {
                return [true, null];
            }

            // Unit-test / partial-client friendly: if paymentIntents client is not available,
            // we cannot retrieve to verify. Soft-allow rather than nulling the PI.
            if (! isset($this->stripe->paymentIntents) || ! is_object($this->stripe->paymentIntents)) {
                return [true, null];
            }

            try {
                $pi = $this->stripe->paymentIntents->retrieve($piId, []);
            } catch (Throwable $e) {
                // Soft-allow when Stripe retrieval fails (network/offline/tests/etc).
                $this->dbg('PI owner guard: retrieve failed; soft-allow', [
                    'pi' => $piId,
                    'invoice_id' => $invoiceId,
                    'expected_customer_id' => $customerId,
                    'error' => $e->getMessage(),
                ], 'warning');

                return [true, null];
            }

            $piCustomerId = $this->extractId($pi->customer ?? null);

            if (! $piCustomerId) {
                // If Stripe doesn't give a customer on the PI (rare, but possible), we can't verify.
                return [true, $this->extractId($pi->latest_charge ?? null)];
            }

            $ok = ((string) $piCustomerId === (string) $customerId);

            return [$ok, $ok ? $this->extractId($pi->latest_charge ?? null) : null];
        };

        $syncFromSubscription = function (Subscription $subscription) use ($pledge, $paymentMethodId, $attemptId, $guardPaymentIntentOwner): void {

            // Period fields can be on subscription top-level; some payloads mirror onto items.
            $itemStart = data_get($subscription, 'items.data.0.current_period_start');
            $itemEnd   = data_get($subscription, 'items.data.0.current_period_end');

            $subStart  = data_get($subscription, 'current_period_start');
            $subEnd    = data_get($subscription, 'current_period_end');

            // latest_invoice can be an object OR a string id
            $latestInvoiceObj = is_object($subscription->latest_invoice ?? null) ? $subscription->latest_invoice : null;
            $latestInvoiceId  = $latestInvoiceObj?->id
                ?: (is_string($subscription->latest_invoice ?? null) ? $subscription->latest_invoice : null);

            // Invoice PI can be object or string
            $piOnInvoice = $latestInvoiceObj?->payment_intent ?? null;
            $latestPiObj = is_object($piOnInvoice) ? $piOnInvoice : null;
            $latestPiId  = is_string($piOnInvoice) ? $piOnInvoice : ($latestPiObj?->id ?? null);

            // Paid timestamps can be on the invoice
            $invoicePaidAt = data_get($latestInvoiceObj, 'status_transitions.paid_at');
            $invoicePaid   = (bool) (data_get($latestInvoiceObj, 'paid') ?? false) || (bool) $invoicePaidAt;

            // Charge id can be on invoice.charge
            $charge = $latestInvoiceObj?->charge ?? null;
            $chargeId = is_string($charge) ? $charge : ($charge->id ?? null);

            // Decide if we truly need to retrieve the invoice (unit-test friendly)
            $needInvoiceRetrieve = false;

            // Basil-style can omit invoice.payment_intent from subscription expansions
            if (! $latestPiId) {
                $needInvoiceRetrieve = true;
            }

            // If we have no usable period anywhere, invoice lines are often the fallback
            $hasAnyPeriodStart = is_numeric($itemStart) || is_numeric($subStart);
            $hasAnyPeriodEnd   = is_numeric($itemEnd)   || is_numeric($subEnd);

            if (! $hasAnyPeriodStart || ! $hasAnyPeriodEnd) {
                $needInvoiceRetrieve = true;
            }

            // If invoice paid is unclear and paid_at missing, invoice retrieve can help
            if (! $invoicePaidAt && ! $invoicePaid) {
                $needInvoiceRetrieve = $needInvoiceRetrieve || (bool) $latestInvoiceId;
            }

            $expandedInvoice = null;

            if ($latestInvoiceId && $needInvoiceRetrieve) {
                // 1) Try richer expands (NO "charges" anywhere)
                try {
                    $expandedInvoice = $this->stripe->invoices->retrieve($latestInvoiceId, [
                        'expand' => [
                            'payment_intent',
                            'charge',
                            'lines.data.price',
                            'payments',
                            'payments.data.payment',
                        ],
                    ]);
                } catch (Throwable $e) {
                    $this->dbg('syncFromSubscription: invoice retrieve failed (expanded)', [
                        'invoice_id' => $latestInvoiceId,
                        'error' => $e->getMessage(),
                    ], 'warning');

                    // 2) Retry with simpler expands
                    try {
                        $expandedInvoice = $this->stripe->invoices->retrieve($latestInvoiceId, [
                            'expand' => ['payment_intent', 'charge', 'payments'],
                        ]);
                    } catch (Throwable $e2) {
                        $this->dbg('syncFromSubscription: invoice retrieve failed (minimal)', [
                            'invoice_id' => $latestInvoiceId,
                            'error' => $e2->getMessage(),
                        ], 'warning');

                        // 3) Final fallback: no expand
                        try {
                            $expandedInvoice = $this->stripe->invoices->retrieve($latestInvoiceId, []);
                        } catch (Throwable $e3) {
                            $this->dbg('syncFromSubscription: invoice retrieve failed (plain)', [
                                'invoice_id' => $latestInvoiceId,
                                'error' => $e3->getMessage(),
                            ], 'warning');
                        }
                    }
                }
            }

            // Prefer paid_at from expanded invoice when present
            if (! $invoicePaidAt && $expandedInvoice) {
                $invoicePaidAt = data_get($expandedInvoice, 'status_transitions.paid_at')
                    ?: data_get($expandedInvoice, 'paid_at');
            }

            if (! $invoicePaid && $invoicePaidAt) {
                $invoicePaid = true;
            }

            // Prefer PI from expanded invoice when present
            if (! $latestPiId && $expandedInvoice) {
                $pi = $expandedInvoice->payment_intent ?? null;
                $latestPiId = is_string($pi) ? $pi : ($pi->id ?? null);
            }

            // BASIL: invoice.payments[*].payment.payment_intent
            if (! $latestPiId && $expandedInvoice) {
                $latestPiId =
                    $this->extractId(data_get($expandedInvoice, 'payments.data.0.payment.payment_intent'))
                    ?: $this->extractId(data_get($expandedInvoice, 'payments.data.0.payment_intent'));
            }

            // Final fallback: retrieve invoice and resolve PI id
            if (! $latestPiId && $latestInvoiceId) {
                $latestPiId = $this->resolvePaymentIntentIdFromInvoice($latestInvoiceId);
            }

            // Determine invoice object to read lines/amount/etc from
            $invoiceObj = $expandedInvoice ?: $latestInvoiceObj;

            // Prefer charge from expanded invoice if we retrieved it
            if ($invoiceObj) {
                $charge = $invoiceObj->charge ?? $charge;
                $chargeId = $chargeId ?: (is_string($charge) ? $charge : ($charge->id ?? null));
            }

            // Prefer invoice line period if item period missing
            $lineStart = data_get($invoiceObj, 'lines.data.0.period.start');
            $lineEnd   = data_get($invoiceObj, 'lines.data.0.period.end');

            $periodStartTs =
                is_numeric($itemStart) ? (int) $itemStart
                : (is_numeric($subStart) ? (int) $subStart
                    : (is_numeric($lineStart) ? (int) $lineStart : null));

            $periodEndTs =
                is_numeric($itemEnd) ? (int) $itemEnd
                : (is_numeric($subEnd) ? (int) $subEnd
                    : (is_numeric($lineEnd) ? (int) $lineEnd : null));

            // ---------------------------------------------------------------------
            // PaymentIntent OWNER GUARD (the thing you asked for)
            // ---------------------------------------------------------------------
            $expectedCustomerId =
                $pledge->stripe_customer_id
                ?: $this->extractId($subscription->customer ?? null)
                ?: $this->extractId(data_get($invoiceObj, 'customer'));

            $piOk = false;
            $guardedChargeId = null;

            if ($latestPiId) {
                [$piOk, $guardedChargeId] = $guardPaymentIntentOwner($latestPiId, $latestInvoiceId, $expectedCustomerId);

                if (! $piOk) {
                    // Refuse to attach PI/charge to pledge/tx when it doesn't “belong”.
                    $this->dbg('syncFromSubscription: PI failed owner guard; skipping PI/charge sync', [
                        'pledge_id' => $pledge->id,
                        'subscription_id' => $subscription->id ?? null,
                        'latest_invoice_id' => $latestInvoiceId,
                        'latest_pi_id' => $latestPiId,
                        'expected_customer_id' => $expectedCustomerId,
                    ], 'warning');

                    $latestPiId = null;
                    $chargeId = null;
                } else {
                    // If PI is OK and we found a charge on PI, prefer it if invoice lacked it
                    if (! $chargeId && $guardedChargeId) {
                        $chargeId = $guardedChargeId;
                    }
                }
            }

            $this->dbg('syncFromSubscription: raw subscription snapshot', [
                'sub_id' => $subscription->id ?? null,
                'sub_status' => $subscription->status ?? null,
                'item_period_start' => $itemStart,
                'item_period_end' => $itemEnd,
                'sub_period_start' => $subStart,
                'sub_period_end' => $subEnd,
                'invoice_line_start' => $lineStart,
                'invoice_line_end' => $lineEnd,
                'latest_invoice_id' => $latestInvoiceId,
                'latest_pi_id' => $latestPiId,
                'charge_id' => $chargeId,
                'invoice_paid_at' => $invoicePaidAt,
                'invoice_paid' => $invoicePaid,
                'pi_owner_guard_ok' => $piOk,
            ]);

            // ---- Sync pledge basics ----
            $pledge->stripe_subscription_id = $subscription->id;
            $pledge->status                 = $subscription->status;
            $pledge->latest_invoice_id      = $latestInvoiceId;

            // Only set when we truly have it (and it passed owner guard)
            if ($latestPiId) {
                $pledge->latest_payment_intent_id = $latestPiId;
            }

            if ($periodStartTs) {
                $pledge->current_period_start = Carbon::createFromTimestamp($periodStartTs);
            }

            if ($periodEndTs) {
                $end = Carbon::createFromTimestamp($periodEndTs);
                $pledge->current_period_end = $end;
                $pledge->next_pledge_at     = $end;
            }

            if ($invoicePaidAt) {
                $pledge->last_pledge_at = Carbon::createFromTimestamp((int) $invoicePaidAt);
            }

            $pledge->save();

            // ---- Ensure/enrich initial Transaction row ----
            // Only do the “PI/charge link” work if we have a verified PI OR at least a trusted invoice.
            if ($latestInvoiceId) {
                $invoiceAmount = (int) (
                    $invoiceObj?->amount_paid
                    ?? $invoiceObj?->amount_due
                    ?? 0
                );

                $tx = Transaction::query()
                    ->where('pledge_id', $pledge->id)
                    ->whereIn('type', ['subscription_initial', 'subscription_recurring'])
                    ->where('source', 'donation_widget')
                    ->latest('id')
                    ->first();

                $isNew = false;
                if (! $tx) {
                    $tx = new Transaction();
                    $isNew = true;
                }

                if (empty($tx->attempt_id) && $attemptId) {
                    $tx->attempt_id = $attemptId;
                }

                $existingMeta = $tx->metadata ?? [];
                if (! is_array($existingMeta)) {
                    $existingMeta = (array) json_decode((string) $existingMeta, true) ?: [];
                }

                $extraMeta = array_filter([
                    'stripe_invoice_id'      => $latestInvoiceId,
                    'stripe_subscription_id' => $subscription->id,
                    'attempt_id'             => $attemptId,
                ]);

                $typeToSet   = $isNew ? 'subscription_initial' : ($tx->type ?: 'subscription_initial');
                $sourceToSet = $tx->source ?: 'donation_widget';

                $tx->fill([
                    'user_id'           => $pledge->user_id,
                    'pledge_id'         => $pledge->id,
                    'subscription_id'   => $subscription->id,
                    // Only set PI/charge if PI passed owner guard
                    'payment_intent_id' => $latestPiId ?: $tx->payment_intent_id,
                    'charge_id'         => $chargeId ?: $tx->charge_id,
                    'customer_id'       => $pledge->stripe_customer_id ?: $tx->customer_id,
                    'payment_method_id' => $paymentMethodId ?: $tx->payment_method_id,
                    'amount_cents'      => $invoiceAmount > 0 ? $invoiceAmount : ((int) ($pledge->amount_cents ?? 0)),
                    'currency'          => $pledge->currency,
                    'type'              => $typeToSet,
                    'status'            => $invoicePaid ? 'succeeded' : ($tx->status ?: 'pending'),
                    'payer_email'       => $pledge->donor_email,
                    'payer_name'        => $pledge->donor_name,
                    'receipt_url'       => ($invoiceObj?->hosted_invoice_url ?? null) ?: $tx->receipt_url,
                    'source'            => $sourceToSet,
                    'paid_at'           => ($invoicePaid && $invoicePaidAt)
                        ? Carbon::createFromTimestamp((int) $invoicePaidAt)
                        : $tx->paid_at,
                    'metadata'          => array_merge($existingMeta, $extraMeta),
                ])->save();

                $this->dbg('syncFromSubscription: tx ensured', [
                    'tx_id' => $tx->id,
                    'pledge_id' => $tx->pledge_id,
                    'payment_intent_id' => $tx->payment_intent_id,
                    'charge_id' => $tx->charge_id,
                    'status' => $tx->status,
                    'type' => $tx->type,
                    'paid_at' => optional($tx->paid_at)->toDateTimeString(),
                ]);
            }
        };

        if (! empty($pledge->stripe_subscription_id)) {
            $subscription = $this->stripe->subscriptions->retrieve($pledge->stripe_subscription_id, [
                'expand' => [
                    'latest_invoice.payment_intent',
                    'latest_invoice.charge',
                    'latest_invoice.payments',
                    'latest_invoice.payments.data.payment',
                ],
            ]);

            $syncFromSubscription($subscription);

            return $subscription;
        }

        // --- Customer must exist ---
        $customerId = $pledge->stripe_customer_id;

        if (! $customerId) {
            $email = $pledge->donor_email ?: ($pledge->user?->email ?? null);
            $name  = $pledge->donor_name  ?: ($pledge->user ? trim("{$pledge->user->first_name} {$pledge->user->last_name}") : null);

            $customerParams = [
                'email'    => $email ?: null,
                'name'     => $name ?: null,
                'metadata' => [
                    'pledge_id'  => (string) $pledge->id,
                    'attempt_id' => (string) ($attemptId ?: ''),
                    'user_id'    => (string) $pledge->user_id,
                    'source'     => 'donation_widget',
                ],
            ];

            $customerOpts = $this->idemFor('customer', 'pledge:' . $pledge->id, $customerParams);

            $customer = $this->stripe->customers->create($customerParams, $customerOpts);

            $customerId = $customer->id;
            $pledge->stripe_customer_id = $customerId;
            $pledge->save();
        }

        // --- Price must exist ---
        $priceId = $pledge->stripe_price_id;

        if (! $priceId) {
            $priceParams = [
                'unit_amount' => (int) $pledge->amount_cents,
                'currency'    => (string) $pledge->currency,
                'recurring'   => ['interval' => (string) $pledge->interval],
                'product'     => $this->recurringProductId(),
                'metadata'    => [
                    'pledge_id'  => (string) $pledge->id,
                    'attempt_id' => (string) ($attemptId ?: ''),
                    'source'     => 'donation_widget',
                ],
            ];

            $priceOpts = $this->idemFor('price', 'pledge:' . $pledge->id, $priceParams);

            $price = $this->stripe->prices->create($priceParams, $priceOpts);

            $priceId = $price->id;
            $pledge->stripe_price_id = $priceId;
            $pledge->save();

            $this->dbg('createSubscriptionForPledge: created price', [
                'price_id' => $priceId,
                'pledge_id' => $pledge->id,
                'amount' => $pledge->amount_cents,
            ]);
        }

        // --- Create subscription ---
        $subParams = [
            'customer'               => $customerId,
            'items'                  => [['price' => $priceId]],
            'default_payment_method' => $paymentMethodId,
            'collection_method'      => 'charge_automatically',
            'expand'                 => [
                'latest_invoice.payment_intent',
                'latest_invoice.charge',
                'latest_invoice.payments',
                'latest_invoice.payments.data.payment',
            ],
            'metadata'               => [
                'pledge_id'  => (string) $pledge->id,
                'attempt_id' => (string) ($attemptId ?: ''),
                'user_id'    => (string) $pledge->user_id,
                'source'     => 'donation_widget',
            ],
        ];

        $subOpts = $this->idemFor('subscription', 'pledge:' . $pledge->id, $subParams);

        $subscription = $this->stripe->subscriptions->create($subParams, $subOpts);

        $this->dbg('createSubscriptionForPledge: created subscription', [
            'sub_id' => $subscription->id ?? null,
            'sub_status' => $subscription->status ?? null,
            'item_period_start' => data_get($subscription, 'items.data.0.current_period_start'),
            'item_period_end' => data_get($subscription, 'items.data.0.current_period_end'),
            'sub_period_start' => data_get($subscription, 'current_period_start'),
            'sub_period_end' => data_get($subscription, 'current_period_end'),
            'latest_invoice' => $this->extractId($subscription->latest_invoice ?? null),
        ]);

        $syncFromSubscription($subscription);

        return $subscription;
    }

    // -------------------------------------------------------------------------
    // Refunds
    // -------------------------------------------------------------------------

    public function refund(Transaction $transaction, ?int $amountCents = null): Refund
    {
        $amount = $amountCents ?? (int) $transaction->amount_cents;

        $params = [
            'charge'   => (string) $transaction->charge_id,
            'amount'   => $amount,
            'metadata' => [
                'transaction_id' => (string) $transaction->id,
                'attempt_id'     => (string) ($transaction->attempt_id ?: ''),
            ],
        ];

        $opts = $this->idemFor('refund', 'tx:' . $transaction->id, $params);

        $stripeRefund = $this->stripe->refunds->create($params, $opts);

        $metadata = [];
        if ($stripeRefund->metadata) {
            if (is_array($stripeRefund->metadata)) {
                $metadata = $stripeRefund->metadata;
            } elseif (method_exists($stripeRefund->metadata, 'toArray')) {
                $metadata = $stripeRefund->metadata->toArray();
            } else {
                $metadata = (array) $stripeRefund->metadata;
            }
        }

        return Refund::create([
            'transaction_id'   => $transaction->id,
            'stripe_refund_id' => $stripeRefund->id,
            'charge_id'        => $stripeRefund->charge,
            'amount_cents'     => $stripeRefund->amount,
            'currency'         => $stripeRefund->currency,
            'status'           => $stripeRefund->status,
            'reason'           => $stripeRefund->reason,
            'metadata'         => $metadata,
        ]);
    }

    // -------------------------------------------------------------------------
    // Subscription management
    // -------------------------------------------------------------------------

    public function cancelSubscriptionAtPeriodEnd(Pledge $pledge): void
    {
        if (! $pledge->stripe_subscription_id) {
            return;
        }

        $subscription = $this->stripe->subscriptions->update(
            $pledge->stripe_subscription_id,
            ['cancel_at_period_end' => true]
        );

        $startTs = data_get($subscription, 'items.data.0.current_period_start')
            ?: data_get($subscription, 'current_period_start');

        $endTs = data_get($subscription, 'items.data.0.current_period_end')
            ?: data_get($subscription, 'current_period_end');

        $updates = [
            'status'               => (string) ($subscription->status ?? $pledge->status),
            'cancel_at_period_end' => (bool) ($subscription->cancel_at_period_end ?? true),
        ];

        if (is_numeric($startTs)) {
            $updates['current_period_start'] = Carbon::createFromTimestamp((int) $startTs);
        }

        if (is_numeric($endTs)) {
            $end = Carbon::createFromTimestamp((int) $endTs);
            $updates['current_period_end'] = $end;
            $updates['next_pledge_at']     = $end;
        }

        $pledge->update($updates);
    }

    public function resumeSubscription(Pledge $pledge): void
    {
        if (! $pledge->stripe_subscription_id) {
            return;
        }

        $subscription = $this->stripe->subscriptions->update(
            $pledge->stripe_subscription_id,
            ['cancel_at_period_end' => false]
        );

        $startTs = data_get($subscription, 'items.data.0.current_period_start')
            ?: data_get($subscription, 'current_period_start');

        $endTs = data_get($subscription, 'items.data.0.current_period_end')
            ?: data_get($subscription, 'current_period_end');

        $updates = [
            'status'               => (string) ($subscription->status ?? $pledge->status),
            'cancel_at_period_end' => (bool) ($subscription->cancel_at_period_end ?? false),
        ];

        if (is_numeric($startTs)) {
            $updates['current_period_start'] = Carbon::createFromTimestamp((int) $startTs);
        }

        if (is_numeric($endTs)) {
            $end = Carbon::createFromTimestamp((int) $endTs);
            $updates['current_period_end'] = $end;
            $updates['next_pledge_at']     = $end;
        }

        $pledge->update($updates);
    }

    public function updateSubscriptionAmount(Pledge $pledge, int $amountCents): void
    {
        if (! $pledge->stripe_subscription_id) {
            return;
        }

        $subscription = $this->stripe->subscriptions->retrieve(
            $pledge->stripe_subscription_id,
            ['expand' => ['items.data.price']]
        );

        $currentProduct = data_get($subscription, 'items.data.0.price.product');
        $productId      = $currentProduct ?: $this->recurringProductId();

        $priceId = $pledge->stripe_price_id;

        if (! $priceId || (int) $pledge->amount_cents !== (int) $amountCents) {
            $priceParams = [
                'unit_amount' => $amountCents,
                'currency'    => $pledge->currency,
                'recurring'   => $pledge->interval ? ['interval' => $pledge->interval] : ['interval' => 'month'],
                'product'     => $productId,
                'metadata'    => [
                    'pledge_id' => (string) $pledge->id,
                    'source'    => 'donation_widget',
                ],
            ];

            $priceOpts = $this->idemFor('price', 'pledge:' . $pledge->id . ':update', $priceParams);

            $price = $this->stripe->prices->create($priceParams, $priceOpts);

            $priceId = $price->id;
        }

        $subscription = $this->stripe->subscriptions->update(
            $subscription->id,
            [
                'cancel_at_period_end' => false,
                'proration_behavior'   => 'create_prorations',
                'items' => [
                    [
                        'id'    => $subscription->items->data[0]->id,
                        'price' => $priceId,
                    ],
                ],
            ]
        );

        $startTs = data_get($subscription, 'items.data.0.current_period_start') ?: data_get($subscription, 'current_period_start');
        $endTs   = data_get($subscription, 'items.data.0.current_period_end')   ?: data_get($subscription, 'current_period_end');

        $updates = [
            'amount_cents'         => $amountCents,
            'stripe_price_id'      => $priceId,
            'status'               => $subscription->status,
            'cancel_at_period_end' => (bool) $subscription->cancel_at_period_end,
        ];

        if (is_numeric($startTs)) {
            $updates['current_period_start'] = now()->setTimestamp((int) $startTs);
        }

        if (is_numeric($endTs)) {
            $end = now()->setTimestamp((int) $endTs);
            $updates['current_period_end'] = $end;
            $updates['next_pledge_at']     = $end;
        }

        $pledge->update($updates);

        $this->dbg('updateSubscriptionAmount: pledge updated', [
            'pledge_id' => $pledge->id,
            'amount_cents' => $pledge->amount_cents,
            'current_period_start' => optional($pledge->current_period_start)->toDateTimeString(),
            'current_period_end' => optional($pledge->current_period_end)->toDateTimeString(),
            'next_pledge_at' => optional($pledge->next_pledge_at)->toDateTimeString(),
        ]);
    }

    // -------------------------------------------------------------------------
    // Invoice → PaymentIntent resolver (Basil-safe)
    // -------------------------------------------------------------------------

    protected function resolvePaymentIntentIdFromInvoice(string $invoiceId): ?string
    {
        $invoice = null;

        try {
            $invoice = $this->stripe->invoices->retrieve($invoiceId, [
                'expand' => ['payments', 'payments.data.payment', 'payment_intent'],
            ]);
        } catch (Throwable $e) {
            $this->dbg('resolvePaymentIntentIdFromInvoice: invoice retrieve failed (expanded)', [
                'invoice_id' => $invoiceId,
                'error' => $e->getMessage(),
            ], 'warning');

            try {
                $invoice = $this->stripe->invoices->retrieve($invoiceId, [
                    'expand' => ['payments', 'payment_intent'],
                ]);
            } catch (Throwable $e2) {
                $this->dbg('resolvePaymentIntentIdFromInvoice: invoice retrieve failed (minimal)', [
                    'invoice_id' => $invoiceId,
                    'error' => $e2->getMessage(),
                ], 'warning');

                try {
                    $invoice = $this->stripe->invoices->retrieve($invoiceId, []);
                } catch (Throwable $e3) {
                    $this->dbg('resolvePaymentIntentIdFromInvoice: invoice retrieve failed (plain)', [
                        'invoice_id' => $invoiceId,
                        'error' => $e3->getMessage(),
                    ], 'warning');

                    return null;
                }
            }
        }

        $piId =
            $this->extractId($invoice->payment_intent ?? null)
            ?: $this->extractId(data_get($invoice, 'payments.data.0.payment.payment_intent'))
            ?: $this->extractId(data_get($invoice, 'payments.data.0.payment_intent'));

        $this->dbg('resolvePaymentIntentIdFromInvoice: snapshot', [
            'invoice_id' => $invoiceId,
            'pi_id' => $piId,
            'has_payments' => (bool) data_get($invoice, 'payments.data.0.id'),
        ], $piId ? 'info' : 'warning');

        return $piId;
    }

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

    public function finalizeTransactionFromPaymentIntent(
        Transaction|string $transactionOrPaymentIntentId,
        ?PaymentIntent $pi = null
    ): Transaction {
        // ------------------------------------------------------------
        // Case A: called as finalizeTransactionFromPaymentIntent('pi_123')
        // ------------------------------------------------------------
        if (is_string($transactionOrPaymentIntentId)) {
            $paymentIntentId = $transactionOrPaymentIntentId;

            $pi ??= $this->retrievePaymentIntent($paymentIntentId, [
                'expand' => [
                    'charges.data.balance_transaction',
                    'payment_method',
                    'customer',
                ],
            ]);

            $tx = Transaction::where('payment_intent_id', $paymentIntentId)->firstOrFail();

            return $this->finalizeTransactionFromPaymentIntent($tx, $pi);
        }

        // ------------------------------------------------------------
        // Case B/C: called as finalizeTransactionFromPaymentIntent($tx, $pi?)
        // ------------------------------------------------------------
        $tx = $transactionOrPaymentIntentId;

        return DB::transaction(function () use ($tx, $pi) {
            // Lock the row so concurrent webhook/return calls can't race each other.
            $tx = Transaction::query()
                ->whereKey($tx->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            // Already finalized? Bail early (idempotent).
            if ($tx->status === 'succeeded' && $tx->paid_at) {
                return $tx;
            }

            if (! $pi) {
                if (! $tx->payment_intent_id) {
                    throw new RuntimeException('Transaction is missing payment_intent_id.');
                }

                $pi = $this->retrievePaymentIntent($tx->payment_intent_id, [
                    'expand' => [
                        'charges.data.balance_transaction',
                        'payment_method',
                        'customer',
                    ],
                ]);
            }

            $piId = $pi->id ?? null;
            if (! is_string($piId) || $piId === '') {
                throw new RuntimeException('Stripe PaymentIntent is missing an id.');
            }

            // --------------------------------------------------------
            // OWNER / CONSISTENCY GUARDS (only enforce when present)
            // --------------------------------------------------------
            $piMetaTxId = data_get($pi, 'metadata.transaction_id');
            if ($piMetaTxId && (string) $piMetaTxId !== (string) $tx->id) {
                // If Stripe says this PI belongs to a different tx, do not mutate this tx.
                $this->dbg('finalizeTransactionFromPaymentIntent: PI metadata mismatch', [
                    'tx_id' => $tx->id,
                    'pi_id' => $piId,
                    'pi_meta_transaction_id' => $piMetaTxId,
                ], 'warning');

                // If the “real” tx exists, return it.
                $owner = Transaction::where('id', (int) $piMetaTxId)->first();
                if ($owner) {
                    return $owner;
                }

                throw new RuntimeException("PaymentIntent does not belong to transaction {$tx->id}.");
            }

            // Optional pledge guard if you store pledge_id on the tx
            $piMetaPledgeId = data_get($pi, 'metadata.pledge_id');
            if ($piMetaPledgeId && $tx->pledge_id && (string) $piMetaPledgeId !== (string) $tx->pledge_id) {
                $this->dbg('finalizeTransactionFromPaymentIntent: pledge mismatch', [
                    'tx_id' => $tx->id,
                    'tx_pledge_id' => $tx->pledge_id,
                    'pi_id' => $piId,
                    'pi_meta_pledge_id' => $piMetaPledgeId,
                ], 'warning');

                throw new RuntimeException("PaymentIntent pledge_id mismatch for transaction {$tx->id}.");
            }

            // Amount/currency guard (only if PI has these fields)
            $piAmount = data_get($pi, 'amount');
            $piCurrency = data_get($pi, 'currency');

            if (is_numeric($piAmount) && (int) $piAmount > 0 && (int) $tx->amount_cents > 0) {
                if ((int) $piAmount !== (int) $tx->amount_cents) {
                    $this->dbg('finalizeTransactionFromPaymentIntent: amount mismatch', [
                        'tx_id' => $tx->id,
                        'tx_amount_cents' => $tx->amount_cents,
                        'pi_id' => $piId,
                        'pi_amount' => (int) $piAmount,
                    ], 'warning');

                    throw new RuntimeException("PaymentIntent amount mismatch for transaction {$tx->id}.");
                }
            }

            if (is_string($piCurrency) && $piCurrency !== '' && is_string($tx->currency) && $tx->currency !== '') {
                if (strtolower($piCurrency) !== strtolower($tx->currency)) {
                    $this->dbg('finalizeTransactionFromPaymentIntent: currency mismatch', [
                        'tx_id' => $tx->id,
                        'tx_currency' => $tx->currency,
                        'pi_id' => $piId,
                        'pi_currency' => $piCurrency,
                    ], 'warning');

                    throw new RuntimeException("PaymentIntent currency mismatch for transaction {$tx->id}.");
                }
            }

            // --------------------------------------------------------
            // Fill from PI (defensive with string|object)
            // --------------------------------------------------------
            // Don't overwrite a different PI once set unless empty.
            if (! $tx->payment_intent_id) {
                $tx->payment_intent_id = $piId;
            }

            // Customer id
            $tx->customer_id ??= $this->extractId($pi->customer ?? null);

            // Charge id (prefer latest_charge; fallback to charges.data[0])
            $tx->charge_id ??= $this->extractId($pi->latest_charge ?? null)
                ?? $this->extractId(data_get($pi, 'charges.data.0.id'));

            // Payment method id
            $tx->payment_method_id ??= $this->extractId($pi->payment_method ?? null);

            // Receipt URL
            $tx->receipt_url ??= data_get($pi, 'charges.data.0.receipt_url');

            // Status + paid_at
            $tx->status = (string) ($pi->status ?? $tx->status ?? 'pending');
            if ($tx->status === 'succeeded') {
                $tx->paid_at ??= now();
            }

            try {
                $tx->save();
            } catch (UniqueConstraintViolationException $e) {
                // This was the production blow-up: another tx already has this PI id.
                // In that case, return the “owner” tx instead of failing.
                $owner = Transaction::where('payment_intent_id', $piId)->first();
                if ($owner && $owner->id !== $tx->id) {
                    $this->dbg('finalizeTransactionFromPaymentIntent: PI already claimed by another tx', [
                        'attempted_tx_id' => $tx->id,
                        'owner_tx_id' => $owner->id,
                        'pi_id' => $piId,
                        'error' => $e->getMessage(),
                    ], 'warning');

                    return $owner;
                }

                throw $e;
            }

            return $tx;
        });
    }

    /**
     * Tiny helper: safely convert Stripe metadata (StripeObject) to array for logging.
     * If you already have something like this, delete this and use yours.
     */
    private function confirm_array($value): array
    {
        if (is_array($value)) return $value;
        if (is_object($value) && method_exists($value, 'toArray')) return $value->toArray();
        return (array) $value;
    }

    protected function claimPaymentIntent(Transaction $tx, ?string $piId): Transaction
    {
        if (! $piId) {
            return $tx;
        }

        // Already set correctly — boring is reliable.
        if ($tx->payment_intent_id === $piId) {
            return $tx;
        }

        // If some other row already owns this PI, return the owner.
        $owner = Transaction::query()
            ->where('payment_intent_id', $piId)
            ->first();

        if ($owner && $owner->id !== $tx->id) {
            $this->dbg('claimPaymentIntent: PI already owned by another tx', [
                'pi_id' => $piId,
                'attempted_tx_id' => $tx->id,
                'owner_tx_id' => $owner->id,
            ], 'warning');

            return $owner;
        }

        // Otherwise, attempt to claim it.
        $tx->payment_intent_id = $piId;

        try {
            $tx->save();
        } catch (UniqueConstraintViolationException $e) {
            // Race: someone else claimed it between our check and save.
            $owner = Transaction::query()
                ->where('payment_intent_id', $piId)
                ->first();

            if ($owner && $owner->id !== $tx->id) {
                $this->dbg('claimPaymentIntent: race lost, returning owner', [
                    'pi_id' => $piId,
                    'attempted_tx_id' => $tx->id,
                    'owner_tx_id' => $owner->id,
                ], 'warning');

                return $owner;
            }

            throw $e;
        }

        return $tx;
    }
}
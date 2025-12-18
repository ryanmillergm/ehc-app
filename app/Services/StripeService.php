<?php

namespace App\Services;

use App\Models\Pledge;
use App\Models\Refund;
use App\Models\Transaction;
use App\Models\User;
use Stripe\StripeClient;

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
            \RuntimeException::class,
            'Stripe secret is missing. Set STRIPE_SECRET in .env and map it in config/services.php.'
        );

        $this->stripe = new StripeClient($secret);
    }

    /**
     * Build a Stripe idempotency opts array (<= 255 chars) that is:
     * - Stable for the same entity + same params
     * - Different when params differ (prevents Stripe IdempotencyException)
     */
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
            \RuntimeException::class,
            'Stripe recurring product id is missing. Set STRIPE_RECURRING_PRODUCT_ID in .env and map it in config/services.php.'
        );

        return $productId;
    }

    // -------------------------------------------------------------------------
    // Retrieve helpers
    // -------------------------------------------------------------------------

    public function retrievePaymentIntent(string $paymentIntentId): \Stripe\PaymentIntent
    {
        return $this->stripe->paymentIntents->retrieve($paymentIntentId);
    }

    public function retrieveSetupIntent(string $setupIntentId): \Stripe\SetupIntent
    {
        return $this->stripe->setupIntents->retrieve($setupIntentId);
    }

    public function retrieveCharge(string $chargeId): \Stripe\Charge
    {
        return $this->stripe->charges->retrieve($chargeId);
    }

    public function retrievePaymentMethod(string $paymentMethodId): \Stripe\PaymentMethod
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
            return $existingCustomerId;
        }

        $params = [
            'email'    => $email ?: null,
            'name'     => $name  ?: null,
            'metadata' => [
                'source' => 'donation_widget',
            ],
        ];

        // Customer creation is not "money-moving", but idempotency keeps Stripe clean on retries.
        $opts = $this->idemFor('customer', 'email:' . ($email ?: 'none'), $params);

        $customer = $this->stripe->customers->create($params, $opts);

        return $customer->id;
    }

    // -------------------------------------------------------------------------
    // One-time PaymentIntent
    // -------------------------------------------------------------------------

    public function createOneTimePaymentIntent(Transaction $transaction, array $donor = []): \Stripe\PaymentIntent
    {
        // If we already created one, just retrieve/sync.
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

        // Use an idempotency key scoped to THIS transaction + full params fingerprint.
        // This prevents collisions when the same attempt_id is reused with a different amount.
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

    public function createSetupIntentForPledge(Pledge $pledge, array $donor = []): \Stripe\SetupIntent
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

        return $si;
    }

    // -------------------------------------------------------------------------
    // Subscription creation
    // -------------------------------------------------------------------------

    public function createSubscriptionForPledge(Pledge $pledge, string $paymentMethodId): \Stripe\Subscription
    {
        $attemptId = $pledge->attempt_id ?: null;

        $syncFromSubscription = function (\Stripe\Subscription $subscription) use ($pledge, $paymentMethodId, $attemptId): void {
            $latestInvoice = is_object($subscription->latest_invoice ?? null) ? $subscription->latest_invoice : null;
            $latestPi      = is_object($latestInvoice?->payment_intent ?? null) ? $latestInvoice->payment_intent : null;

            // ---- Sync pledge basics ----
            $pledge->stripe_subscription_id = $subscription->id;
            $pledge->status                 = $subscription->status;

            if (! empty($subscription->current_period_start)) {
                $pledge->current_period_start = now()->setTimestamp((int) $subscription->current_period_start);
            }

            if (! empty($subscription->current_period_end)) {
                $end = now()->setTimestamp((int) $subscription->current_period_end);
                $pledge->current_period_end = $end;
                $pledge->next_pledge_at     = $end;
            }

            $pledge->latest_invoice_id        = $latestInvoice?->id;
            $pledge->latest_payment_intent_id = $latestPi?->id;

            $invoicePaidAt = data_get($latestInvoice, 'status_transitions.paid_at');
            if ($invoicePaidAt) {
                $pledge->last_pledge_at = now()->setTimestamp((int) $invoicePaidAt);
            }

            $pledge->save();

            // ---- Ensure/enrich initial Transaction row ----
            if ($latestInvoice) {
                $invoiceAmount = (int) (
                    $latestInvoice->amount_paid
                    ?? $latestInvoice->amount_due
                    ?? 0
                );

                $paymentIntentId = $latestPi?->id
                    ?? (is_string($latestInvoice->payment_intent ?? null) ? $latestInvoice->payment_intent : null);

                $chargeId = $latestInvoice->charge ?? null;
                if (! $chargeId && $latestPi && isset($latestPi->latest_charge)) {
                    $chargeId = $latestPi->latest_charge;
                }

                $tx = Transaction::query()
                    ->where('pledge_id', $pledge->id)
                    ->whereIn('type', ['subscription_initial', 'subscription_recurring'])
                    ->where('source', 'donation_widget')
                    ->latest('id')
                    ->first();

                if (! $tx) {
                    $tx = new Transaction();
                }

                if (empty($tx->attempt_id) && $attemptId) {
                    $tx->attempt_id = $attemptId;
                }

                $existingMeta = $tx->metadata ?? [];
                if (! is_array($existingMeta)) {
                    $existingMeta = (array) json_decode((string) $existingMeta, true) ?: [];
                }

                $extraMeta = array_filter([
                    'stripe_invoice_id'      => $latestInvoice->id ?? null,
                    'stripe_subscription_id' => $subscription->id,
                    'attempt_id'             => $attemptId,
                ]);

                $paidAt = data_get($latestInvoice, 'status_transitions.paid_at');
                $invoicePaid = (bool) ($latestInvoice->paid ?? false);

                $tx->fill([
                    'user_id'           => $pledge->user_id,
                    'pledge_id'         => $pledge->id,
                    'subscription_id'   => $subscription->id,
                    'payment_intent_id' => $paymentIntentId ?: $tx->payment_intent_id,
                    'charge_id'         => $chargeId ?: $tx->charge_id,
                    'customer_id'       => $pledge->stripe_customer_id ?: $tx->customer_id,
                    'payment_method_id' => $paymentMethodId ?: $tx->payment_method_id,
                    'amount_cents'      => $invoiceAmount > 0 ? $invoiceAmount : ($pledge->amount_cents ?? 0),
                    'currency'          => $pledge->currency,
                    'type'              => 'subscription_initial',
                    'status'            => $invoicePaid ? 'succeeded' : ($tx->status ?: 'pending'),
                    'payer_email'       => $pledge->donor_email,
                    'payer_name'        => $pledge->donor_name,
                    'receipt_url'       => $latestInvoice->hosted_invoice_url ?? $tx->receipt_url,
                    'source'            => 'donation_widget',
                    'paid_at'           => $invoicePaid && $paidAt ? now()->setTimestamp((int) $paidAt) : $tx->paid_at,
                    'metadata'          => array_merge($existingMeta, $extraMeta),
                ])->save();
            }
        };

        if (! empty($pledge->stripe_subscription_id)) {
            $subscription = $this->stripe->subscriptions->retrieve($pledge->stripe_subscription_id, [
                'expand' => ['latest_invoice.payment_intent'],
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
        }

        // --- Create subscription ---
        $subParams = [
            'customer'               => $customerId,
            'items'                  => [['price' => $priceId]],
            'default_payment_method' => $paymentMethodId,
            'collection_method'      => 'charge_automatically',
            'expand'                 => ['latest_invoice.payment_intent'],
            'metadata'               => [
                'pledge_id'  => (string) $pledge->id,
                'attempt_id' => (string) ($attemptId ?: ''),
                'user_id'    => (string) $pledge->user_id,
                'source'     => 'donation_widget',
            ],
        ];

        $subOpts = $this->idemFor('subscription', 'pledge:' . $pledge->id, $subParams);

        $subscription = $this->stripe->subscriptions->create($subParams, $subOpts);

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

        $pledge->update([
            'status'               => $subscription->status,
            'cancel_at_period_end' => (bool) $subscription->cancel_at_period_end,
        ]);
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

        $pledge->update([
            'status'               => $subscription->status,
            'cancel_at_period_end' => (bool) $subscription->cancel_at_period_end,
        ]);
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
                'recurring'   => ['interval' => $pledge->interval],
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

        $updates = [
            'amount_cents'         => $amountCents,
            'stripe_price_id'      => $priceId,
            'status'               => $subscription->status,
            'cancel_at_period_end' => (bool) $subscription->cancel_at_period_end,
        ];

        if ($subscription->current_period_start) {
            $updates['current_period_start'] = now()->setTimestamp($subscription->current_period_start);
        }

        if ($subscription->current_period_end) {
            $end = now()->setTimestamp($subscription->current_period_end);
            $updates['current_period_end'] = $end;
            $updates['next_pledge_at']     = $end;
        }

        $pledge->update($updates);
    }
}

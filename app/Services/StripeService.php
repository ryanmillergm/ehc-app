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
        $this->stripe = $stripe ?: new StripeClient(config('services.stripe.secret'));
    }

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

            // 1) Try pledges for this user
            $existingCustomerId = Pledge::where('user_id', $user->id)
                ->whereNotNull('stripe_customer_id')
                ->orderByDesc('created_at')
                ->value('stripe_customer_id');

            // 2) Fallback to transactions for this user
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
                // 1) Try transactions by payer_email
                $existingCustomerId = Transaction::whereNotNull('customer_id')
                    ->where('payer_email', $email)
                    ->orderByDesc('created_at')
                    ->value('customer_id');

                // 2) Fallback to pledges by donor_email
                if (! $existingCustomerId) {
                    $existingCustomerId = Pledge::whereNotNull('stripe_customer_id')
                        ->where('donor_email', $email)
                        ->orderByDesc('created_at')
                        ->value('stripe_customer_id');
                }
            }
        }

        // If we already have a Stripe customer for this donor, reuse it
        if ($existingCustomerId) {
            return $existingCustomerId;
        }

        // Otherwise create a new one in Stripe
        $customer = $this->stripe->customers->create([
            'email'    => $email ?: null,
            'name'     => $name  ?: null,
            'metadata' => [
                'source' => 'donation_widget',
            ],
        ]);

        return $customer->id;
    }

    /**
     * One-time donation PaymentIntent.
     */
    public function createOneTimePaymentIntent(Transaction $transaction, array $donor = []): \Stripe\PaymentIntent
    {
        $customerId = $donor['customer_id'] ?? null;

        if (! $customerId && ! empty($donor)) {
            $customerId = $this->getOrCreateCustomer($donor);
            $transaction->customer_id = $customerId;
            $transaction->save();
        }

        $pi = $this->stripe->paymentIntents->create([
            'amount'   => $transaction->amount_cents,
            'currency' => $transaction->currency,
            'customer' => $customerId,
            'metadata' => [
                'transaction_id' => (string) $transaction->id,
                'type'           => $transaction->type,
                'source'         => $transaction->source,
            ],
            'automatic_payment_methods' => [
                'enabled' => true,
            ],
        ]);

        $transaction->payment_intent_id = $pi->id;
        $transaction->status            = $pi->status;
        $transaction->save();

        return $pi;
    }

    /**
     * SetupIntent used for monthly pledges (save card, create subscription later).
     */
    public function createSetupIntentForPledge(Pledge $pledge, array $donor = []): \Stripe\SetupIntent
    {
        $customerId = $pledge->stripe_customer_id;

        if (! $customerId && ! empty($donor)) {
            $customerId = $this->getOrCreateCustomer($donor);
            $pledge->stripe_customer_id = $customerId;
            $pledge->save();
        }

        return $this->stripe->setupIntents->create([
            'customer' => $customerId,
            'automatic_payment_methods' => ['enabled' => true],
            'metadata' => [
                'pledge_id' => (string) $pledge->id,
            ],
        ]);
    }

    /**
     * After a SetupIntent succeeds, create the Subscription for the pledge.
     *
     * Uses an inline price based on pledge amount/interval.
     * Also updates the pledge with subscription + latest invoice/payment intent info,
     * and creates the *first* subscription Transaction row.
     */
    public function createSubscriptionForPledge(Pledge $pledge, string $paymentMethodId): \Stripe\Subscription
    {
        $customerId = $pledge->stripe_customer_id;

        // Safety: if we somehow don't have a customer id yet, create one now.
        if (! $customerId) {
            $email = $pledge->donor_email;
            $name  = $pledge->donor_name;

            if (! $email || ! $name) {
                $user = $pledge->user ?? null;
                if ($user instanceof User) {
                    $email = $email ?: $user->email;
                    $name  = $name ?: trim("{$user->first_name} {$user->last_name}");
                }
            }

            $customer = $this->stripe->customers->create([
                'email'    => $email ?: null,
                'name'     => $name ?: null,
                'metadata' => [
                    'pledge_id' => (string) $pledge->id,
                    'user_id'   => (string) $pledge->user_id,
                ],
            ]);

            $customerId = $customer->id;
            $pledge->stripe_customer_id = $customerId;
            $pledge->save();
        }

        // Reuse existing price if present; otherwise create a new inline price.
        $priceId = $pledge->stripe_price_id;

        if (! $priceId) {
            $price = $this->stripe->prices->create([
                'unit_amount' => $pledge->amount_cents,
                'currency'    => $pledge->currency,
                'recurring'   => ['interval' => $pledge->interval],
                'product_data' => [
                    'name' => 'Monthly donation',
                ],
            ]);

            $priceId = $price->id;
            $pledge->stripe_price_id = $priceId;
            $pledge->save();
        }

        $subscription = $this->stripe->subscriptions->create([
            'customer'               => $customerId,
            'items'                  => [['price' => $priceId]],
            'default_payment_method' => $paymentMethodId,
            'collection_method'      => 'charge_automatically',
            'expand'                 => ['latest_invoice.payment_intent'],
            'metadata'               => [
                'pledge_id' => (string) $pledge->id,
                'user_id'   => (string) $pledge->user_id,
            ],
        ]);

        $latestInvoice = is_object($subscription->latest_invoice ?? null)
            ? $subscription->latest_invoice
            : null;

        $latestPi = is_object($latestInvoice?->payment_intent ?? null)
            ? $latestInvoice->payment_intent
            : null;

        // ---- Sync pledge with subscription + period dates ----
        $pledge->stripe_subscription_id = $subscription->id;
        $pledge->status                 = $subscription->status;

        if ($subscription->current_period_start) {
            $pledge->current_period_start = now()->setTimestamp($subscription->current_period_start);
        }

        if ($subscription->current_period_end) {
            $periodEnd = now()->setTimestamp($subscription->current_period_end);
            $pledge->current_period_end = $periodEnd;
            $pledge->next_pledge_at     = $periodEnd;
        }

        $pledge->latest_invoice_id        = $latestInvoice?->id;
        $pledge->latest_payment_intent_id = $latestPi?->id;
        $pledge->save();

        // ---- Initial subscription Transaction (first charge) ----
        if ($latestInvoice && $latestInvoice->amount_paid > 0) {
            $paymentIntentId = $latestPi?->id ?? ($latestInvoice->payment_intent ?? null);

            $chargeId = $latestInvoice->charge ?? null;
            if (! $chargeId && $latestPi && isset($latestPi->latest_charge)) {
                $chargeId = $latestPi->latest_charge;
            }

            $lookup = [];
            if ($paymentIntentId) {
                $lookup['payment_intent_id'] = $paymentIntentId;
            } else {
                $lookup['subscription_id'] = $subscription->id;
                if ($chargeId) {
                    $lookup['charge_id'] = $chargeId;
                }
            }

            $tx = Transaction::firstOrNew($lookup);

            $existingMeta = $tx->metadata ?? [];
            if (! is_array($existingMeta)) {
                $existingMeta = (array) json_decode($existingMeta, true) ?: [];
            }

            $extraMeta = [
                'stripe_invoice_id'      => $latestInvoice->id,
                'stripe_subscription_id' => $subscription->id,
            ];

            $tx->fill([
                'user_id'         => $pledge->user_id,
                'pledge_id'       => $pledge->id,
                'subscription_id' => $subscription->id,
                'charge_id'       => $chargeId ?: $tx->charge_id,
                'amount_cents'    => $latestInvoice->amount_paid,
                'currency'        => $pledge->currency,
                'type'            => 'subscription_recurring',
                'status'          => 'succeeded',
                'payer_email'     => $pledge->donor_email,
                'payer_name'      => $pledge->donor_name,
                'receipt_url'     => $latestInvoice->hosted_invoice_url ?? $tx->receipt_url,
                'source'          => 'donation_widget',
                'paid_at'         => $tx->paid_at ?? now(),
                'metadata'        => array_merge($existingMeta, $extraMeta),
            ])->save();
        }

        return $subscription;
    }

    /**
     * Refund a transaction (full or partial).
     */
    public function refund(Transaction $transaction, ?int $amountCents = null): Refund
    {
        $amount = $amountCents ?? $transaction->amount_cents;

        $stripeRefund = $this->stripe->refunds->create([
            'charge'  => $transaction->charge_id,
            'amount'  => $amount,
            'metadata'=> [
                'transaction_id' => (string) $transaction->id,
            ],
        ]);

        return Refund::create([
            'transaction_id'   => $transaction->id,
            'stripe_refund_id' => $stripeRefund->id,
            'charge_id'        => $stripeRefund->charge,
            'amount_cents'     => $stripeRefund->amount,
            'currency'         => $stripeRefund->currency,
            'status'           => $stripeRefund->status,
            'reason'           => $stripeRefund->reason,
            'metadata'         => $stripeRefund->metadata ? $stripeRefund->metadata->toArray() : [],
        ]);
    }

    /**
     * Cancel a subscription at period end and sync pledge fields.
     *
     * We intentionally DO NOT touch current_period_start / current_period_end here,
     * to avoid wiping them out when Stripe doesn't send them.
     * Webhooks (invoice + subscription.updated) keep those in sync.
     */
    public function cancelSubscriptionAtPeriodEnd(Pledge $pledge): void
    {
        if (! $pledge->stripe_subscription_id) {
            return;
        }

        $subscription = $this->stripe->subscriptions->update(
            $pledge->stripe_subscription_id,
            [
                'cancel_at_period_end' => true,
            ]
        );

        // Only update status + cancel flag; keep period dates as-is.
        $pledge->update([
            'status'               => $subscription->status,
            'cancel_at_period_end' => (bool) $subscription->cancel_at_period_end,
        ]);
    }

    /**
     * Change the recurring amount for a pledge's subscription.
     * Creates a new Price if needed, updates the Stripe subscription,
     * and syncs the pledge.
     */
    public function updateSubscriptionAmount(Pledge $pledge, int $amountCents): void
    {
        if (! $pledge->stripe_subscription_id) {
            return;
        }

        $subscription = $this->stripe->subscriptions->retrieve(
            $pledge->stripe_subscription_id,
            ['expand' => ['items.data.price']]
        );

        // Reuse existing price when amount matches; otherwise create a new one.
        $priceId = $pledge->stripe_price_id;

        if (! $priceId || $pledge->amount_cents !== $amountCents) {
            $price = $this->stripe->prices->create([
                'unit_amount' => $amountCents,
                'currency'    => $pledge->currency,
                'recurring'   => ['interval' => $pledge->interval],
                'product'     => $subscription->items->data[0]->price->product ?? null,
            ]);

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

        // Only update period fields if Stripe actually sends them.
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

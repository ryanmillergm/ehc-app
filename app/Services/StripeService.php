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

    public function __construct()
    {
        $this->stripe = new StripeClient(config('services.stripe.secret'));
    }

    /**
     * Ensure we have a Stripe customer id.
     * Accepts a User or raw donor data array.
     */
    public function getOrCreateCustomer(User|array $donor): string
    {
        if ($donor instanceof User) {
            $email = $donor->email;
            $name  = trim("{$donor->first_name} {$donor->last_name}");
        } else {
            $email = $donor['email'] ?? null;
            $name  = $donor['name'] ?? null;
        }

        // In a real app you might store stripe_customer_id on User.
        // For now we just create a new one every time for simplicity.
        $customer = $this->stripe->customers->create([
            'email' => $email,
            'name'  => $name,
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
                'transaction_id' => $transaction->id,
                'type'           => $transaction->type,
                'source'         => $transaction->source,
            ],
            'automatic_payment_methods' => [
                'enabled' => true,
            ],
        ]);

        $transaction->payment_intent_id = $pi->id;
        $transaction->status            = $pi->status; // e.g. 'requires_payment_method'
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

        $setupIntent = $this->stripe->setupIntents->create([
            'customer' => $customerId,
            'automatic_payment_methods' => ['enabled' => true],
            'metadata' => [
                'pledge_id' => $pledge->id,
            ],
        ]);

        return $setupIntent;
    }

    /**
     * After a SetupIntent succeeds, create the Subscription for the pledge.
     */
    public function createSubscriptionForPledge(Pledge $pledge, string $paymentMethodId): \Stripe\Subscription
    {
        $customerId = $pledge->stripe_customer_id;

        // Create an inline price (amount + interval) if you don't have a catalog product.
        $price = $this->stripe->prices->create([
            'unit_amount' => $pledge->amount_cents,
            'currency'    => $pledge->currency,
            'recurring'   => ['interval' => $pledge->interval],
            'product_data' => [
                'name' => 'Monthly donation',
            ],
        ]);

        $pledge->stripe_price_id = $price->id;
        $pledge->save();

        $subscription = $this->stripe->subscriptions->create([
            'customer' => $customerId,
            'items'    => [
                ['price' => $price->id],
            ],
            'default_payment_method' => $paymentMethodId,
            'expand' => ['latest_invoice.payment_intent'],
            'metadata' => [
                'pledge_id' => $pledge->id,
            ],
        ]);

        $pledge->stripe_subscription_id = $subscription->id;
        $pledge->status                 = $subscription->status;
        $pledge->latest_invoice_id      = $subscription->latest_invoice?->id;
        $pledge->latest_payment_intent_id = $subscription->latest_invoice?->payment_intent?->id;
        $pledge->save();

        return $subscription;
    }

    /**
     * Refund a transaction (full or partial).
     */
    public function refund(Transaction $transaction, ?int $amountCents = null): Refund
    {
        $amount = $amountCents ?? $transaction->amount_cents;

        $stripeRefund = $this->stripe->refunds->create([
            'charge' => $transaction->charge_id,
            'amount' => $amount,
            'metadata' => [
                'transaction_id' => $transaction->id,
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
}

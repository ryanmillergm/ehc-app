<?php

namespace App\Http\Controllers;

use App\Models\Pledge;
use App\Models\Refund;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Stripe\Webhook;
use Symfony\Component\HttpFoundation\Response;

class StripeWebhookController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $payload   = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');
        $secret    = config('services.stripe.webhook_secret');

        try {
            $event = Webhook::constructEvent($payload, $sigHeader, $secret);

            Log::info('Stripe webhook received', [
                'id'   => $event->id ?? null,
                'type' => $event->type ?? null,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Stripe webhook signature verification failed', [
                'error' => $e->getMessage(),
            ]);

            return response('Invalid signature', 400);
        }

        try {
            $this->handleEvent($event);
        } catch (\Throwable $e) {
            Log::error('Stripe webhook handler exception', [
                'type'  => $event->type ?? null,
                'error' => $e->getMessage(),
            ]);
        }

        return response()->json(['received' => true]);
    }

    public function handleEvent(object $event): void
    {
        $type = $event->type ?? null;

        switch ($type) {
            case 'payment_intent.succeeded':
                $this->handlePaymentIntentSucceeded($event);
                break;

            case 'payment_intent.payment_failed':
                $this->handlePaymentIntentFailed($event);
                break;

            case 'charge.succeeded':
                $this->handleChargeSucceeded($event);
                break;

            case 'invoice.paid':
            case 'invoice.payment_succeeded':
                $this->handleInvoicePaid($event);
                break;

            case 'invoice.payment_failed':
                $this->handleInvoicePaymentFailed($event);
                break;

            case 'customer.subscription.updated':
            case 'customer.subscription.deleted':
                $this->handleSubscriptionUpdated($event);
                break;

            case 'charge.refunded':
                $this->handleChargeRefunded($event);
                break;

            default:
                // ignore other events
                break;
        }
    }

    /**
     * PaymentIntent succeeded: ensure status / paid_at are set.
     * We do NOT rely on this to get card/receipt details anymore.
     */
    public function handlePaymentIntentSucceeded(object $event): void
    {
        $pi = $event->data->object;

        /** @var \App\Models\Transaction|null $tx */
        $tx = Transaction::where('payment_intent_id', $pi->id)->first();

        if (! $tx) {
            Log::info('PI succeeded but no transaction found', [
                'payment_intent_id' => $pi->id,
            ]);
            return;
        }

        $tx->update([
            'status'  => 'succeeded',
            'paid_at' => $tx->paid_at ?? now(),
        ]);
    }

    /**
     * Charge succeeded: enrich transaction with charge + card details.
     */
    public function handleChargeSucceeded(object $event): void
    {
        $charge = $event->data->object;

        $paymentIntentId = $charge->payment_intent ?? null;
        if (! $paymentIntentId) {
            Log::info('Charge succeeded without payment_intent', [
                'charge_id' => $charge->id ?? null,
            ]);
            return;
        }

        /** @var \App\Models\Transaction|null $tx */
        $tx = Transaction::where('payment_intent_id', $paymentIntentId)->first();

        if (! $tx) {
            Log::info('Charge succeeded but no transaction found', [
                'payment_intent_id' => $paymentIntentId,
                'charge_id'         => $charge->id ?? null,
            ]);
            return;
        }

        $billing = $charge->billing_details ?? null;
        $card    = $charge->payment_method_details->card ?? null;

        // Ensure metadata is an array
        $existingMeta = $tx->metadata ?? [];
        if (! is_array($existingMeta)) {
            $existingMeta = (array) json_decode($existingMeta, true) ?: [];
        }

        $extraMeta = array_filter([
            'card_brand'   => $card->brand   ?? null,
            'card_last4'   => $card->last4   ?? null,
            'card_country' => $card->country ?? null,
            'card_funding' => $card->funding ?? null,
            'charge_id'    => $charge->id    ?? null,
        ]);

        $tx->fill([
            'status'      => 'succeeded',
            'charge_id'   => $charge->id          ?? $tx->charge_id,
            'receipt_url' => $charge->receipt_url ?? $tx->receipt_url,
            'payer_email' => $billing->email      ?? $tx->payer_email,
            'payer_name'  => $billing->name       ?? $tx->payer_name,
            'paid_at'     => $tx->paid_at        ?? now(),
            'metadata'    => array_merge($existingMeta, $extraMeta),
        ])->save();

        Log::info('Transaction enriched from charge.succeeded', [
            'transaction_id'    => $tx->id,
            'payment_intent_id' => $paymentIntentId,
            'charge_id'         => $charge->id ?? null,
        ]);
    }

    public function handlePaymentIntentFailed(object $event): void
    {
        $pi = $event->data->object;

        $tx = Transaction::where('payment_intent_id', $pi->id)->first();

        if (! $tx) {
            return;
        }

        $tx->update(['status' => 'failed']);
    }

    public function handleInvoicePaid(object $event): void
    {
        $invoice = $event->data->object;

        $subscriptionId = $invoice->subscription;
        if (! $subscriptionId) return;

        $pledge = Pledge::where('stripe_subscription_id', $subscriptionId)->first();
        if (! $pledge) return;

        $pledge->update([
            'status'            => 'active',
            'last_pledge_at'    => now(),
            'next_pledge_at'    => now()->addSeconds($invoice->lines->data[0]?->period?->end - $invoice->lines->data[0]?->period?->start ?? 0),
            'latest_invoice_id' => $invoice->id,
        ]);

        $charge      = $invoice->charge ? $invoice->charge : ($invoice->charges->data[0]->id ?? null);
        $amountCents = $invoice->amount_paid;

        Transaction::firstOrCreate(
            [
                'subscription_id' => $subscriptionId,
                'charge_id'       => $charge,
                'amount_cents'    => $amountCents,
            ],
            [
                'user_id'           => $pledge->user_id,
                'pledge_id'         => $pledge->id,
                'payment_intent_id' => $invoice->payment_intent ?? null,
                'currency'          => $invoice->currency,
                'type'              => 'subscription_recurring',
                'status'            => 'succeeded',
                'payer_email'       => $invoice->customer_email ?? $pledge->donor_email,
                'payer_name'        => $pledge->donor_name,
                'receipt_url'       => $invoice->hosted_invoice_url ?? null,
                'source'            => 'stripe_webhook',
                'paid_at'           => now(),
            ]
        );
    }

    public function handleInvoicePaymentFailed(object $event): void
    {
        $invoice = $event->data->object;

        $subscriptionId = $invoice->subscription;
        if (! $subscriptionId) return;

        $pledge = Pledge::where('stripe_subscription_id', $subscriptionId)->first();
        if (! $pledge) return;

        $pledge->update([
            'status' => 'past_due',
        ]);
    }

    public function handleSubscriptionUpdated(object $event): void
    {
        $sub = $event->data->object;

        $pledge = Pledge::where('stripe_subscription_id', $sub->id)->first();
        if (! $pledge) return;

        $pledge->update([
            'status'               => $sub->status,
            'cancel_at_period_end' => (bool) $sub->cancel_at_period_end,
            'current_period_start' => $sub->current_period_start ? now()->setTimestamp($sub->current_period_start) : null,
            'current_period_end'   => $sub->current_period_end ? now()->setTimestamp($sub->current_period_end) : null,
        ]);
    }

    public function handleChargeRefunded(object $event): void
    {
        $charge = $event->data->object;

        $tx = Transaction::where('charge_id', $charge->id)->first();
        if (! $tx) return;

        $tx->update([
            'status' => 'refunded',
        ]);

        foreach ($charge->refunds->data as $stripeRefund) {
            Refund::updateOrCreate(
                ['stripe_refund_id' => $stripeRefund->id],
                [
                    'transaction_id'   => $tx->id,
                    'charge_id'        => $charge->id,
                    'amount_cents'     => $stripeRefund->amount,
                    'currency'         => $stripeRefund->currency,
                    'status'           => $stripeRefund->status,
                    'reason'           => $stripeRefund->reason,
                    'metadata'         => $stripeRefund->metadata ? $stripeRefund->metadata->toArray() : [],
                ]
            );
        }
    }
}

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

            case 'invoice.payment_succeeded':
            case 'invoice.paid':
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

            case 'charge.succeeded':
                $this->handleChargeSucceeded($event);
                break;

            default:
                // ignore everything else
                break;
        }
    }

    // ------------------------------------------------------------------
    // PAYMENT INTENT (one-time) HELPERS
    // ------------------------------------------------------------------

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

        $tx->fill([
            'status'  => 'succeeded',
            'paid_at' => $tx->paid_at ?? now(),
        ])->save();
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

    // ------------------------------------------------------------------
    // INVOICES / SUBSCRIPTIONS (monthly)
    // ------------------------------------------------------------------

    public function handleInvoicePaid(object $event): void
    {
        $invoice = $event->data->object;

        // Try multiple places for the subscription id (defensive)
        $subscriptionIdTop  = $invoice->subscription ?? null;
        $line0              = $invoice->lines->data[0] ?? null;
        $subscriptionIdLine = $line0->subscription ?? null;

        $subscriptionId = $subscriptionIdTop ?: $subscriptionIdLine;

        Log::info('handleInvoicePaid entry', [
            'event_type'     => $event->type ?? null,
            'invoice_id'     => $invoice->id ?? null,
            'sub_top'        => $subscriptionIdTop,
            'sub_line'       => $subscriptionIdLine,
            'sub_final'      => $subscriptionId,
            'customer'       => $invoice->customer ?? null,
            'amount_paid'    => $invoice->amount_paid ?? null,
            'status'         => $invoice->status ?? null,
        ]);

        if (! $subscriptionId) {
            Log::info('handleInvoicePaid: missing subscription id; skipping', [
                'invoice_id' => $invoice->id ?? null,
            ]);
            return;
        }

        /** @var \App\Models\Pledge|null $pledge */
        $pledge = Pledge::where('stripe_subscription_id', $subscriptionId)->first();

        if (! $pledge) {
            Log::warning('handleInvoicePaid: pledge not found for subscription', [
                'subscription_id' => $subscriptionId,
                'invoice_id'      => $invoice->id ?? null,
            ]);
            return;
        }

        // Work out next_pledge_at from the line period if possible
        $period          = $line0?->period ?? null;
        $intervalSeconds = 0;

        if ($period?->start && $period?->end) {
            $intervalSeconds = $period->end - $period->start;
        }

        $pledge->update([
            'status'                   => 'active',
            'last_pledge_at'           => now(),
            'next_pledge_at'           => $intervalSeconds > 0 ? now()->addSeconds($intervalSeconds) : null,
            'latest_invoice_id'        => $invoice->id,
            'latest_payment_intent_id' => $invoice->payment_intent ?? $pledge->latest_payment_intent_id,
        ]);

        // ---------- Transaction row for this invoice ----------

        $paymentIntentId = $invoice->payment_intent ?? null;
        $chargeId        = $invoice->charge ?: ($invoice->charges->data[0]->id ?? null);
        $amountCents     = $invoice->amount_paid ?? $invoice->amount_due ?? 0;

        if ($paymentIntentId) {
            $tx = Transaction::firstOrNew([
                'payment_intent_id' => $paymentIntentId,
            ]);
        } else {
            $tx = Transaction::firstOrNew([
                'subscription_id' => $subscriptionId,
                'charge_id'       => $chargeId,
            ]);
        }

        $existingMeta = $tx->metadata ?? [];
        if (! is_array($existingMeta)) {
            $existingMeta = (array) json_decode($existingMeta, true) ?: [];
        }

        $extraMeta = array_filter([
            'stripe_invoice_id'      => $invoice->id,
            'stripe_subscription_id' => $subscriptionId,
        ]);

        $tx->fill([
            'user_id'         => $pledge->user_id,
            'pledge_id'       => $pledge->id,
            'subscription_id' => $subscriptionId,
            'charge_id'       => $chargeId,
            'amount_cents'    => $amountCents,
            'currency'        => $invoice->currency,
            'type'            => 'subscription_recurring',
            'status'          => 'succeeded',
            'payer_email'     => $invoice->customer_email ?? $pledge->donor_email,
            'payer_name'      => $pledge->donor_name,
            'receipt_url'     => $invoice->hosted_invoice_url ?? $tx->receipt_url,
            'source'          => 'stripe_webhook',
            'paid_at'         => $tx->paid_at ?? now(),
            'metadata'        => array_merge($existingMeta, $extraMeta),
        ])->save();

        Log::info('Invoice paid transaction upserted', [
            'transaction_id'    => $tx->id,
            'pledge_id'         => $pledge->id,
            'subscription_id'   => $subscriptionId,
            'payment_intent_id' => $paymentIntentId,
            'charge_id'         => $chargeId,
            'amount_cents'      => $amountCents,
        ]);
    }

    public function handleInvoicePaymentFailed(object $event): void
    {
        $invoice = $event->data->object;

        $subscriptionId = $invoice->subscription ?? null;
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

    // ------------------------------------------------------------------
    // REFUNDS
    // ------------------------------------------------------------------

    public function handleChargeRefunded(object $event): void
    {
        $charge = $event->data->object;

        $tx = Transaction::where('charge_id', $charge->id)->first();
        if (! $tx) return;

        $tx->update(['status' => 'refunded']);

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

    // ------------------------------------------------------------------
    // CHARGE SUCCEEDED ENRICHMENT (mostly for one-time)
    // ------------------------------------------------------------------

    public function handleChargeSucceeded(object $event): void
    {
        $charge = $event->data->object;

        $paymentIntentId = $charge->payment_intent ?? null;
        if (! $paymentIntentId) {
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
            'charge_id'   => $charge->id          ?? $tx->charge_id,
            'receipt_url' => $charge->receipt_url ?? $tx->receipt_url,
            'payer_email' => $billing->email      ?? $tx->payer_email,
            'payer_name'  => $billing->name       ?? $tx->payer_name,
            'metadata'    => array_merge($existingMeta, $extraMeta),
        ])->save();

        Log::info('Transaction enriched from charge.succeeded', [
            'transaction_id'    => $tx->id,
            'payment_intent_id' => $tx->payment_intent_id,
            'charge_id'         => $tx->charge_id,
        ]);
    }
}

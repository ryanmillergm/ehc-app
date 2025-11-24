<?php

namespace App\Http\Controllers;

use App\Models\Pledge;
use App\Models\Refund;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;
use UnexpectedValueException;

class StripeWebhookController extends Controller
{
    /**
     * Stripe webhook endpoint.
     */
    public function __invoke(Request $request)
    {
        $payload   = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature', '');
        $secret    = config('services.stripe.webhook_secret');

        try {
            $event = $secret
                ? Webhook::constructEvent($payload, $sigHeader, $secret)
                : json_decode($payload);
        } catch (UnexpectedValueException|SignatureVerificationException $e) {
            Log::warning('Stripe webhook invalid', ['error' => $e->getMessage()]);
            return response('Invalid payload', 400);
        }

        Log::info('Stripe webhook received', [
            'id'   => $event->id ?? null,
            'type' => $event->type ?? null,
        ]);

        // ------------------------------------------------------------------
        // Optional: log full real webhook payload for fixture capture.
        // Enable with STRIPE_LOG_WEBHOOK_PAYLOAD=true (see note below).
        // ------------------------------------------------------------------
        if (config('services.stripe.log_webhook_payload', false)) {
            // Log as array so it's easy to copy/paste into fixtures.
            // (This is the exact raw webhook JSON.)
            // $decoded = json_decode($payload, true);

            // Log::debug('stripe_fixture', [
            //     'type'    => $event->type ?? null,
            //     'payload' => $decoded,
            // ]);

            $dir = storage_path('logs/stripe-fixtures');
            if (! is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            $type = $event->type ?? 'unknown';
            $id   = $event->id ?? uniqid();

            file_put_contents("{$dir}/{$type}.{$id}.json", $payload);
        }

        $this->handleEvent($event);

        return response()->json(['ok' => true]);
    }

    /**
     * Used by tests and by __invoke().
     */
    public function handleEvent(object $event): void
    {
        $type = $event->type ?? null;
        if (! $type) {
            return;
        }

        $object = data_get($event, 'data.object');

        switch ($type) {
            case 'payment_intent.succeeded':
                $this->handlePaymentIntentSucceeded($object);
                break;

            case 'payment_intent.payment_failed':
            case 'payment_intent.failed':
                $this->handlePaymentIntentFailed($object);
                break;

            case 'invoice.paid':
            case 'invoice.payment_succeeded':
            case 'invoice_payment.paid':
                $this->handleInvoicePaid($object, $type);
                break;

            case 'invoice.payment_failed':
                $this->handleInvoicePaymentFailed($object);
                break;

            case 'charge.succeeded':
                $this->handleChargeSucceeded($object);
                break;

            case 'charge.refunded':
                $this->handleChargeRefunded($object);
                break;

            case 'customer.subscription.created':
            case 'customer.subscription.updated':
            case 'customer.subscription.deleted':
                $this->handleSubscriptionUpdated($object, $type);
                break;

            default:
                Log::debug('Stripe webhook ignored', ['type' => $type]);
        }
    }

    // -------------------------------------------------------------------------
    // PAYMENT INTENTS
    // -------------------------------------------------------------------------

    protected function handlePaymentIntentSucceeded(object $pi): void
    {
        $piId = $pi->id ?? null;
        if (! $piId) {
            return;
        }

        $tx = Transaction::where('payment_intent_id', $piId)->first();

        if (! $tx) {
            // If this PI is tied to a subscription invoice and we can resolve a pledge,
            // create the recurring transaction now (Stripe can send PI before invoice.paid).
            $this->ensureRecurringTransactionFromPaymentIntent($pi);

            Log::info('PI succeeded but no transaction found', [
                'payment_intent_id' => $piId,
            ]);
            return;
        }

        $tx->status  = 'succeeded';
        $tx->paid_at = $tx->paid_at ?? now();

        // latest_charge is optional on PI webhooks
        $latestCharge = $pi->latest_charge ?? null;
        if ($latestCharge && empty($tx->charge_id)) {
            $tx->charge_id = $this->extractId($latestCharge);
        }

        // Backfill only if missing
        if (empty($tx->payment_method_id) && ! empty($pi->payment_method)) {
            $tx->payment_method_id = $this->extractId($pi->payment_method);
        }
        if (empty($tx->customer_id) && ! empty($pi->customer)) {
            $tx->customer_id = $this->extractId($pi->customer);
        }

        $tx->save();
    }

    protected function handlePaymentIntentFailed(object $pi): void
    {
        $piId = $pi->id ?? null;
        if (! $piId) {
            return;
        }

        $tx = Transaction::where('payment_intent_id', $piId)->first();
        if (! $tx) {
            return;
        }

        $tx->status = 'failed';
        $tx->save();
    }

    // -------------------------------------------------------------------------
    // INVOICES (recurring)
    // -------------------------------------------------------------------------
    protected function handleInvoicePaid(object $invoice, string $eventType = 'invoice.paid'): void
    {
        $invoiceId  = $invoice->id ?? null;
        $customerId = $this->extractId($invoice->customer ?? null);

        // subscription can be on invoice.subscription,
        // or lines[0].subscription,
        // OR (real Stripe) lines[0].parent.subscription_item_details.subscription,
        // OR parent.subscription_details.subscription
        $subTop        = $this->extractId($invoice->subscription ?? null);
        $subLine       = $this->extractId(data_get($invoice, 'lines.data.0.subscription'));
        $subLineParent = $this->extractId(data_get($invoice, 'lines.data.0.parent.subscription_item_details.subscription'));
        $subParent     = $this->extractId(data_get($invoice, 'parent.subscription_details.subscription'));

        $subscriptionId = $subTop ?: $subLine ?: $subLineParent ?: $subParent;

        // payment_intent is usually a string unless expanded (and may be missing)
        $paymentIntentId = $this->extractId($invoice->payment_intent ?? null);

        // charge can be a string, or null; charges.data[0].id is a valid fallback (may also be missing)
        $chargeId = $this->extractId($invoice->charge ?? null);
        if (! $chargeId) {
            $chargeId = $this->extractId(data_get($invoice, 'charges.data.0.id'));
        }

        $amountPaid = $invoice->amount_paid ?? $invoice->amount_due ?? null;
        $currency   = $invoice->currency ?? 'usd';

        $hostedInvoiceUrl = $invoice->hosted_invoice_url ?? null;
        $payerEmail       = $invoice->customer_email ?? null;
        $payerName        = null; // prefer pledge donor_name below

        Log::info('handleInvoicePaid entry', [
            'event_type' => $eventType,
            'invoice_id' => $invoiceId,
            'sub_top'    => $subTop,
            'sub_line'   => $subLine,
            'sub_parent' => $subParent,
            'sub_final'  => $subscriptionId,
            'customer'   => $customerId,
            'amount_paid'=> $amountPaid,
            'status'     => $invoice->status ?? null,
        ]);

        // --------------------------------------------------------
        // Resolve pledge by subscription first, then customer fallback
        // --------------------------------------------------------
        $pledge = null;

        if ($subscriptionId) {
            $pledge = Pledge::where('stripe_subscription_id', $subscriptionId)->first();
        }

        if (! $pledge && $customerId) {
            Log::info('handleInvoicePaid: attempting customer fallback', [
                'invoice_id'      => $invoiceId,
                'subscription_id' => $subscriptionId,
                'customer_id'     => $customerId,
            ]);

            $pledge = Pledge::where('stripe_customer_id', $customerId)
                ->latest('id')
                ->first();

            if ($pledge && ! $subscriptionId) {
                $subscriptionId = $pledge->stripe_subscription_id;

                Log::info('handleInvoicePaid: pledge resolved via customer', [
                    'pledge_id'       => $pledge->id,
                    'customer_id'     => $customerId,
                    'subscription_id' => $subscriptionId,
                ]);
            }
        }

        if (! $pledge) {
            Log::warning('handleInvoicePaid: pledge not found', [
                'invoice_id'      => $invoiceId,
                'subscription_id' => $subscriptionId,
                'customer_id'     => $customerId,
            ]);
            return;
        }

        // ---------------- Pledge updates ----------------
        $pledgeUpdates = ['status' => 'active'];

        if ($invoiceId) {
            $pledgeUpdates['latest_invoice_id'] = $invoiceId;
        }

        if ($paymentIntentId) {
            $pledgeUpdates['latest_payment_intent_id'] = $paymentIntentId;
        }

        $periodStartTs = data_get($invoice, 'lines.data.0.period.start');
        $periodEndTs   = data_get($invoice, 'lines.data.0.period.end');

        if ($periodStartTs && $periodEndTs) {
            $startCarbon = Carbon::createFromTimestamp($periodStartTs);
            $endCarbon   = Carbon::createFromTimestamp($periodEndTs);

            Log::info('handleInvoicePaid: updating pledge periods', [
                'pledge_id'          => $pledge->id,
                'subscription_id'    => $subscriptionId,
                'period_start_ts'    => $periodStartTs,
                'period_end_ts'      => $periodEndTs,
                'period_start_carbon'=> $startCarbon->toDateTimeString(),
                'period_end_carbon'  => $endCarbon->toDateTimeString(),
            ]);

            $pledgeUpdates['current_period_start'] = $startCarbon;
            $pledgeUpdates['current_period_end']   = $endCarbon;
            $pledgeUpdates['last_pledge_at']        = $endCarbon;
            $pledgeUpdates['next_pledge_at']        = $endCarbon;
        }

        $pledge->fill($pledgeUpdates)->save();

        // --------------------------------------------------------
        // Transaction upsert (single source of truth)
        // --------------------------------------------------------

        // payment_method can be on expanded PI, or default_payment_method on invoice
        $invoicePiPm      = $this->extractId(data_get($invoice, 'payment_intent.payment_method'));
        $defaultInvoicePm = $this->extractId($invoice->default_payment_method ?? null);
        $paymentMethodId  = $invoicePiPm ?: $defaultInvoicePm;

        // Find an existing tx in priority order:
        $existingTx = null;

        if ($paymentIntentId) {
            $existingTx = Transaction::where('payment_intent_id', $paymentIntentId)->first();
        }

        if (! $existingTx && $chargeId) {
            $existingTx = Transaction::where('charge_id', $chargeId)->first();
        }

        if (! $existingTx && $subscriptionId) {
            $existingTx = Transaction::query()
                ->where('pledge_id', $pledge->id)
                ->where('type', 'subscription_recurring')
                ->where(function ($q) use ($invoiceId, $subscriptionId) {
                    if ($invoiceId) {
                        $q->where('metadata->stripe_invoice_id', $invoiceId);
                    }
                    $q->orWhere('subscription_id', $subscriptionId);
                })
                ->latest('id')
                ->first();
        }

        // Heuristic: if invoice has NO PI/charge (real life),
        // attach it to the most recent "early charge" tx for this pledge.
        if (! $existingTx && ! $paymentIntentId && ! $chargeId) {
            $existingTx = Transaction::query()
                ->where('pledge_id', $pledge->id)
                ->where('type', 'subscription_recurring')
                ->whereNull('subscription_id')
                ->whereNotNull('payment_intent_id') // early charge usually has PI
                ->where('created_at', '>=', now()->subMinutes(30))
                ->latest('id')
                ->first();
        }

        $baseMetadata = array_filter([
            'stripe_invoice_id'      => $invoiceId,
            'stripe_subscription_id' => $subscriptionId,
        ]);

        if ($existingTx) {
            // Always-updated fields (safe)
            $existingTx->amount_cents = $amountPaid ?? $existingTx->amount_cents ?? $pledge->amount_cents;
            $existingTx->currency     = $currency ?? $existingTx->currency;
            $existingTx->status       = 'succeeded';
            $existingTx->source       = 'stripe_webhook';
            $existingTx->receipt_url  = $hostedInvoiceUrl ?: $existingTx->receipt_url;
            $existingTx->paid_at      = $existingTx->paid_at ?? now();

            // Attach subscription if early tx didn't have it
            if (empty($existingTx->subscription_id) && ! empty($subscriptionId)) {
                $existingTx->subscription_id = $subscriptionId;
            }

            // ✅ Only backfill IDs if tx is missing them and Stripe sent something
            if (empty($existingTx->payment_intent_id) && ! empty($paymentIntentId)) {
                $existingTx->payment_intent_id = $paymentIntentId;
            }
            if (empty($existingTx->charge_id) && ! empty($chargeId)) {
                $existingTx->charge_id = $chargeId;
            }
            if (empty($existingTx->customer_id) && ! empty($customerId)) {
                $existingTx->customer_id = $customerId;
            }
            if (empty($existingTx->payment_method_id) && ! empty($paymentMethodId)) {
                $existingTx->payment_method_id = $paymentMethodId;
            }

            // Payer details
            $existingTx->payer_email = $existingTx->payer_email ?? ($payerEmail ?: $pledge->donor_email);
            $existingTx->payer_name  = $existingTx->payer_name ?? ($pledge->donor_name ?? $payerName);

            // Merge metadata
            $existingTx->metadata = array_merge($existingTx->metadata ?? [], $baseMetadata);

            $existingTx->save();
            $tx = $existingTx;
        } else {
            $tx = Transaction::create([
                'user_id'           => $pledge->user_id,
                'pledge_id'         => $pledge->id,
                'subscription_id'   => $subscriptionId,
                'payment_intent_id' => $paymentIntentId,
                'charge_id'         => $chargeId,
                'customer_id'       => $customerId,
                'payment_method_id' => $paymentMethodId,
                'amount_cents'      => $amountPaid ?? $pledge->amount_cents,
                'currency'          => $currency,
                'type'              => 'subscription_recurring',
                'status'            => 'succeeded',
                'source'            => 'stripe_webhook',
                'receipt_url'       => $hostedInvoiceUrl,
                'payer_email'       => $payerEmail ?: $pledge->donor_email,
                'payer_name'        => $pledge->donor_name,
                'metadata'          => $baseMetadata,
                'paid_at'           => now(),
            ]);
        }

        Log::info('Invoice paid transaction upserted', [
            'transaction_id'    => $tx->id,
            'pledge_id'         => $pledge->id,
            'subscription_id'   => $subscriptionId,
            'payment_intent_id' => $tx->payment_intent_id,
            'charge_id'         => $tx->charge_id,
            'customer_id'       => $tx->customer_id,
            'payment_method_id' => $tx->payment_method_id,
            'amount_cents'      => $tx->amount_cents,
        ]);
    }

    protected function handleInvoicePaymentFailed(object $invoice): void
    {
        $subscriptionId = $this->extractId($invoice->subscription ?? null)
            ?: $this->extractId(data_get($invoice, 'lines.data.0.subscription'));

        if (! $subscriptionId) {
            return;
        }

        $pledge = Pledge::where('stripe_subscription_id', $subscriptionId)->first();
        if (! $pledge) {
            return;
        }

        $pledge->status = 'past_due';
        $pledge->save();
    }

    // -------------------------------------------------------------------------
    // CHARGES
    // -------------------------------------------------------------------------

    protected function handleChargeSucceeded(object $charge): void
    {
        $paymentIntentId = $this->extractId($charge->payment_intent ?? null);
        if (! $paymentIntentId) {
            Log::info('Charge succeeded missing payment_intent, ignoring', [
                'charge_id' => $charge->id ?? null,
            ]);
            return;
        }

        $tx = Transaction::where('payment_intent_id', $paymentIntentId)->first();

        if (! $tx) {
            // Stripe can send charge.succeeded before invoice.paid.
            // If we can resolve the pledge by customer, create the recurring tx now.
            $created = $this->ensureRecurringTransactionFromCharge($charge);

            if (! $created) {
                Log::info('Charge succeeded but no transaction found', [
                    'payment_intent_id' => $paymentIntentId,
                    'charge_id'         => $charge->id ?? null,
                ]);
            }
            return;
        }

        $card = data_get($charge, 'payment_method_details.card');

        $tx->charge_id   = $tx->charge_id ?? ($charge->id ?? null);
        $tx->receipt_url = $tx->receipt_url ?? ($charge->receipt_url ?? null);
        $tx->payer_email = $tx->payer_email ?? data_get($charge, 'billing_details.email');
        $tx->payer_name  = $tx->payer_name ?? data_get($charge, 'billing_details.name');

        // backfill only if missing
        if (empty($tx->customer_id) && ! empty($charge->customer)) {
            $tx->customer_id = $this->extractId($charge->customer);
        }
        if (empty($tx->payment_method_id) && ! empty($charge->payment_method)) {
            $tx->payment_method_id = $this->extractId($charge->payment_method);
        }

        $meta = is_array($tx->metadata) ? $tx->metadata : [];

        if ($card) {
            $meta = array_merge($meta, array_filter([
                'card_brand'   => $card->brand ?? null,
                'card_last4'   => $card->last4 ?? null,
                'card_country' => $card->country ?? null,
                'card_funding' => $card->funding ?? null,
            ]));
        }

        $tx->metadata = $meta;
        $tx->save();

        Log::info('Transaction enriched from charge.succeeded', [
            'transaction_id'    => $tx->id,
            'payment_intent_id' => $paymentIntentId,
            'charge_id'         => $tx->charge_id,
            'customer_id'       => $tx->customer_id,
            'payment_method_id' => $tx->payment_method_id,
        ]);
    }

    protected function handleChargeRefunded(object $charge): void
    {
        $chargeId = $charge->id ?? null;
        if (! $chargeId) {
            return;
        }

        $tx = Transaction::where('charge_id', $chargeId)->first();
        if (! $tx) {
            return;
        }

        $tx->status = 'refunded';
        $tx->save();

        foreach ((array) data_get($charge, 'refunds.data', []) as $refundObj) {
            $refundId = $refundObj->id ?? null;
            if (! $refundId) {
                continue;
            }

            Refund::updateOrCreate(
                ['stripe_refund_id' => $refundId],
                [
                    'transaction_id' => $tx->id,
                    'charge_id'      => $chargeId,
                    'amount_cents'   => $refundObj->amount ?? 0,
                    'currency'       => $refundObj->currency ?? $tx->currency,
                    'status'         => $refundObj->status ?? 'succeeded',
                    'reason'         => $refundObj->reason ?? null,
                    'metadata'       => (array) ($refundObj->metadata ?? []),
                ]
            );
        }
    }

    // -------------------------------------------------------------------------
    // SUBSCRIPTIONS
    // -------------------------------------------------------------------------

    protected function handleSubscriptionUpdated(object $sub, string $eventType): void
    {
        $subscriptionId = $sub->id ?? null;
        if (! $subscriptionId) {
            return;
        }

        $pledge = Pledge::where('stripe_subscription_id', $subscriptionId)->first();
        if (! $pledge) {
            return;
        }

        Log::info('handleSubscriptionUpdated entry', [
            'event_type'          => $eventType,
            'subscription_id'     => $subscriptionId,
            'status'              => $sub->status ?? null,
            'cancel_at_period_end'=> $sub->cancel_at_period_end ?? null,
            'current_period_start'=> $sub->current_period_start ?? null,
            'current_period_end'  => $sub->current_period_end ?? null,
        ]);

        $updates = [
            'status'               => $sub->status ?? $pledge->status,
            'cancel_at_period_end' => (bool) ($sub->cancel_at_period_end ?? $pledge->cancel_at_period_end),
        ];

        $startTs = $sub->current_period_start ?? null;
        $endTs   = $sub->current_period_end ?? null;

        if ($startTs) {
            $updates['current_period_start'] = Carbon::createFromTimestamp($startTs);
        }

        if ($endTs) {
            $endCarbon = Carbon::createFromTimestamp($endTs);
            $updates['current_period_end'] = $endCarbon;
            $updates['next_pledge_at']     = $endCarbon;
        }

        Log::info('handleSubscriptionUpdated: updating pledge', [
            'pledge_id'           => $pledge->id,
            'subscription_id'     => $subscriptionId,
            'status'              => $updates['status'],
            'cancel_at_period_end'=> $updates['cancel_at_period_end'],
            'current_period_start'=> optional($updates['current_period_start'] ?? null)?->toDateTimeString(),
            'current_period_end'  => optional($updates['current_period_end'] ?? null)?->toDateTimeString(),
        ]);

        $pledge->fill($updates)->save();
    }

    // -------------------------------------------------------------------------
    // HELPERS
    // -------------------------------------------------------------------------

    /**
     * Stripe webhooks often deliver either a string ID or an expanded object.
     */
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

    /**
     * If a charge arrives before invoice.paid, create the recurring transaction.
     */
    protected function ensureRecurringTransactionFromCharge(object $charge): bool
    {
        $customerId = $this->extractId($charge->customer ?? null);
        $piId       = $this->extractId($charge->payment_intent ?? null);

        if (! $customerId || ! $piId) {
            return false;
        }

        // If we already have a tx for this PI, don't duplicate
        if (Transaction::where('payment_intent_id', $piId)->exists()) {
            return false;
        }

        $pledge = Pledge::where('stripe_customer_id', $customerId)
            ->latest('id')
            ->first();

        if (! $pledge) {
            return false;
        }

        // ✅ NEW: If widget already created a recurring tx, enrich it instead
        if ($existing = $this->findExistingRecurringTxForPledge($pledge)) {
            $existing->fill([
                'payment_intent_id' => $piId,
                'charge_id'         => $charge->id ?? null,
                'customer_id'       => $customerId,
                'payment_method_id' => $this->extractId($charge->payment_method ?? null),
                'amount_cents'      => $charge->amount ?? $existing->amount_cents ?? $pledge->amount_cents,
                'currency'          => $charge->currency ?? $existing->currency ?? 'usd',
                'status'            => 'succeeded',
                'source'            => 'stripe_webhook',
                'receipt_url'       => $charge->receipt_url ?? $existing->receipt_url,
                'payer_email'       => data_get($charge, 'billing_details.email') ?? $existing->payer_email ?? $pledge->donor_email,
                'payer_name'        => data_get($charge, 'billing_details.name') ?? $existing->payer_name ?? $pledge->donor_name,
            ]);

            $existing->metadata = array_merge($existing->metadata ?? [], array_filter([
                'stripe_invoice_id'      => $this->extractId($charge->invoice ?? null),
                'stripe_subscription_id' => $pledge->stripe_subscription_id,
            ]));

            $existing->paid_at ??= now();
            $existing->save();

            Log::info('Enriched existing recurring tx from early charge', [
                'transaction_id'    => $existing->id,
                'pledge_id'         => $pledge->id,
                'payment_intent_id' => $piId,
            ]);

            return true;
        }

        // otherwise create a new tx (current behavior)
        Transaction::create([
            'user_id'           => $pledge->user_id,
            'pledge_id'         => $pledge->id,
            'payment_intent_id' => $piId,
            'subscription_id'   => $pledge->stripe_subscription_id,
            'charge_id'         => $charge->id ?? null,
            'customer_id'       => $pledge->stripe_customer_id ?? $customerId,
            'payment_method_id' => $this->extractId($charge->payment_method ?? null),
            'amount_cents'      => $charge->amount ?? $pledge->amount_cents,
            'currency'          => $charge->currency ?? 'usd',
            'type'              => 'subscription_recurring',
            'status'            => 'succeeded',
            'payer_email'       => data_get($charge, 'billing_details.email') ?? $pledge->donor_email,
            'payer_name'        => data_get($charge, 'billing_details.name') ?? $pledge->donor_name,
            'receipt_url'       => $charge->receipt_url ?? null,
            'source'            => 'stripe_webhook',
            'metadata'          => array_filter([
                'stripe_invoice_id'      => $this->extractId($charge->invoice ?? null),
                'stripe_subscription_id' => $pledge->stripe_subscription_id,
            ]),
            'paid_at' => now(),
        ]);

        Log::info('Created transaction from early charge', [
            'pledge_id'         => $pledge->id,
            'payment_intent_id' => $piId,
            'charge_id'         => $charge->id ?? null,
        ]);

        return true;
    }

    /**
     * If PI succeeds before invoice.paid, create recurring tx when possible.
     */
    protected function ensureRecurringTransactionFromPaymentIntent(object $pi): bool
    {
        $piId       = $pi->id ?? null;
        $customerId = $this->extractId($pi->customer ?? null);

        if (! $piId || ! $customerId) {
            return false;
        }

        if (empty($pi->invoice)) {
            return false;
        }

        if (Transaction::where('payment_intent_id', $piId)->exists()) {
            return false;
        }

        $pledge = Pledge::where('stripe_customer_id', $customerId)
            ->latest('id')
            ->first();

        if (! $pledge) {
            return false;
        }

        if ($existing = $this->findExistingRecurringTxForPledge($pledge)) {
            $existing->fill([
                'payment_intent_id' => $piId,
                'charge_id'         => $this->extractId($pi->latest_charge ?? null),
                'customer_id'       => $customerId,
                'payment_method_id' => $this->extractId($pi->payment_method ?? null),
                'amount_cents'      => $pi->amount_received ?? $pi->amount ?? $existing->amount_cents ?? $pledge->amount_cents,
                'currency'          => $pi->currency ?? $existing->currency ?? 'usd',
                'status'            => 'succeeded',
                'source'            => 'stripe_webhook',
            ]);

            $existing->metadata = array_merge($existing->metadata ?? [], array_filter([
                'stripe_invoice_id'      => $this->extractId($pi->invoice ?? null),
                'stripe_subscription_id' => $pledge->stripe_subscription_id,
            ]));

            $existing->paid_at ??= now();
            $existing->save();

            Log::info('Enriched existing recurring tx from early PI', [
                'transaction_id'    => $existing->id,
                'pledge_id'         => $pledge->id,
                'payment_intent_id' => $piId,
            ]);

            return true;
        }

        // fallback: create new like before...
        Transaction::create([
            'user_id'           => $pledge->user_id,
            'pledge_id'         => $pledge->id,
            'payment_intent_id' => $piId,
            'subscription_id'   => $pledge->stripe_subscription_id,
            'charge_id'         => $this->extractId($pi->latest_charge ?? null),
            'customer_id'       => $pledge->stripe_customer_id ?? $customerId,
            'payment_method_id' => $this->extractId($pi->payment_method ?? null),
            'amount_cents'      => $pi->amount_received ?? $pi->amount ?? $pledge->amount_cents,
            'currency'          => $pi->currency ?? 'usd',
            'type'              => 'subscription_recurring',
            'status'            => 'succeeded',
            'payer_email'       => $pledge->donor_email,
            'payer_name'        => $pledge->donor_name,
            'receipt_url'       => null,
            'source'            => 'stripe_webhook',
            'metadata'          => array_filter([
                'stripe_invoice_id'      => $this->extractId($pi->invoice ?? null),
                'stripe_subscription_id' => $pledge->stripe_subscription_id,
            ]),
            'paid_at' => now(),
        ]);

        return true;
    }

    protected function findExistingRecurringTxForPledge(Pledge $pledge): ?Transaction
    {
        return Transaction::query()
            ->where('pledge_id', $pledge->id)
            ->where('type', 'subscription_recurring')
            // widget-created tx often has no PI/charge yet
            ->whereNull('payment_intent_id')
            ->whereNull('charge_id')
            ->where(function ($q) use ($pledge) {
                $q->whereNull('subscription_id')
                ->orWhere('subscription_id', $pledge->stripe_subscription_id);
            })
            // keep it time-bounded so we don't link to something ancient
            ->where('created_at', '>=', now()->subHours(2))
            ->latest('id')
            ->first();
    }
}

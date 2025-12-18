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

        $this->dbg('webhook: hit', [
            'path' => $request->path(),
            'ip'   => $request->ip(),
            'has_sig_header' => $sigHeader !== '',
            'secret_prefix'  => $secret ? substr($secret, 0, 8) . '…' : null,
        ], 'alert');

        if (! $secret && ! app()->environment('local')) {
            abort(500, 'Stripe webhook secret not configured.');
        }

        try {
            $event = $secret
                ? Webhook::constructEvent($payload, $sigHeader, $secret)
                : json_decode($payload);

            if (! is_object($event)) {
                $this->dbg('webhook: payload decoded but not object', [], 'warning');
                return response('Invalid payload', 400);
            }
        } catch (UnexpectedValueException|SignatureVerificationException $e) {
            // In local dev, optionally allow unsigned payloads so I can test without Stripe CLI sig headers.
            if (app()->environment('local') && config('services.stripe.debug_state')) {
                $this->dbg('webhook: signature invalid (local) - falling back to unsigned decode', [
                    'error' => $e->getMessage(),
                ], 'warning');

                $event = json_decode($payload);
                if (! is_object($event)) {
                    return response('Invalid payload', 400);
                }
            } else {
                Log::warning('Stripe webhook invalid', ['error' => $e->getMessage()]);
                return response('Invalid payload', 400);
            }
        }

        $this->dbg('webhook: received', [
            'id'   => $event->id ?? null,
            'type' => $event->type ?? null,
        ]);

        $this->maybeWriteFixture($payload, $event);

        try {
            $this->handleEvent($event);
        } catch (\Throwable $e) {
            Log::error('Stripe webhook handler crashed', [
                'event_id'   => $event->id ?? null,
                'event_type' => $event->type ?? null,
                'error'      => $e->getMessage(),
            ]);

            throw $e;
        }

        return response()->json(['ok' => true]);
    }

    public function handleEvent(object $event): void
    {
        $type = $event->type ?? null;
        if (! $type) {
            return;
        }

        $object = data_get($event, 'data.object');
        if (! is_object($object)) {
            return;
        }

        $this->dbg('webhook: dispatching', [
            'type' => $type,
            'object_id' => $object->id ?? null,
        ]);

        switch ($type) {
            case 'payment_intent.succeeded':
                $this->handlePaymentIntentSucceeded($object);
                break;

            case 'payment_intent.payment_failed':
                $this->handlePaymentIntentFailed($object);
                break;

            // Basil uses invoice_payment.* for the PI reference needed.
            case 'invoice_payment.paid':
                $this->handleInvoicePaymentPaid($object);
                break;

            case 'invoice.paid':
            case 'invoice.payment_succeeded':
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
                $this->dbg('webhook: ignored', ['type' => $type]);
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
            // Only enrich existing subscription placeholder tx (never create new subscription tx here)
            $this->ensureSubscriptionTransactionFromPaymentIntent($pi);

            $this->dbg('PI succeeded but no transaction found', [
                'payment_intent_id' => $piId,
                'invoice' => $this->extractId($pi->invoice ?? null),
                'customer' => $this->extractId($pi->customer ?? null),
            ], 'info');

            return;
        }

        $tx->status  = 'succeeded';
        $tx->paid_at = $tx->paid_at ?? now();
        $tx->source  = $tx->source ?? 'stripe_webhook';

        $latestCharge = $pi->latest_charge ?? null;
        if ($latestCharge && empty($tx->charge_id)) {
            $tx->charge_id = $this->extractId($latestCharge);
        }

        if (empty($tx->payment_method_id) && ! empty($pi->payment_method)) {
            $tx->payment_method_id = $this->extractId($pi->payment_method);
        }

        if (empty($tx->customer_id) && ! empty($pi->customer)) {
            $tx->customer_id = $this->extractId($pi->customer);
        }

        $tx->save();

        $this->dbg('PI succeeded: tx updated', $this->txSnap($tx));
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

        $this->dbg('PI failed: tx updated', [
            'tx_id' => $tx->id,
            'payment_intent_id' => $piId,
        ], 'warning');
    }

    // -------------------------------------------------------------------------
    // INVOICE PAYMENTS (Basil)
    // -------------------------------------------------------------------------

    /**
     * `invoice_payment.paid` carries the PI in:
     *   data.object.payment.payment_intent = "pi_..."
     *
     */
    protected function handleInvoicePaymentPaid(object $inpay): void
    {
        $invoicePaymentId = $inpay->id ?? null; // inpay_...
        $invoiceId        = $this->extractId($inpay->invoice ?? null); // in_...
        $paymentIntentId  = $this->extractId(data_get($inpay, 'payment.payment_intent'))
            ?: $this->extractId(data_get($inpay, 'payment_intent')); // defensive

        $paidAtTs = data_get($inpay, 'status_transitions.paid_at');
        $paidAt   = is_numeric($paidAtTs) ? Carbon::createFromTimestamp((int) $paidAtTs) : null;

        $this->dbg('invoice_payment.paid: start', [
            'invoice_payment_id' => $invoicePaymentId,
            'invoice_id' => $invoiceId,
            'payment_intent_id' => $paymentIntentId,
            'paid_at_ts' => $paidAtTs,
        ], 'info');

        if (! $invoiceId) {
            $this->dbg('invoice_payment.paid: missing invoice id', [
                'invoice_payment_id' => $invoicePaymentId,
            ], 'warning');
            return;
        }

        // Prefer the placeholder tx you already create (it stores stripe_invoice_id in metadata).
        $tx = $this->findTransactionByStripeInvoiceId($invoiceId);

        $pledge = null;

        if ($tx && ! empty($tx->pledge_id)) {
            $pledge = Pledge::find($tx->pledge_id);
        }

        if (! $pledge) {
            $pledge = Pledge::query()
                ->where('latest_invoice_id', $invoiceId)
                ->latest('id')
                ->first();
        }

        if (! $pledge && $tx && ! empty($tx->customer_id)) {
            $pledge = Pledge::query()
                ->where('stripe_customer_id', $tx->customer_id)
                ->latest('id')
                ->first();
        }

        if (! $pledge) {
            $this->dbg('invoice_payment.paid: pledge not found', [
                'invoice_id' => $invoiceId,
                'payment_intent_id' => $paymentIntentId,
                'tx_id' => $tx?->id,
            ], 'warning');
            return;
        }

        // ---- Update pledge (do NOT write nulls over values) ----
        $this->dbg('invoice_payment.paid: pledge before', $this->pledgeSnap($pledge), 'info');

        $pledgeUpdates = ['status' => 'active'];

        if ($invoiceId) {
            $pledgeUpdates['latest_invoice_id'] = $invoiceId;
        }

        if ($paymentIntentId) {
            $pledgeUpdates['latest_payment_intent_id'] = $paymentIntentId;
        }

        if ($paidAt) {
            $pledgeUpdates['last_pledge_at'] = $paidAt;
        }

        $pledge->fill($pledgeUpdates)->save();

        $this->dbg('invoice_payment.paid: pledge after', $this->pledgeSnap($pledge), 'info');

        // ---- Enrich the placeholder tx ----
        if ($tx) {
            $tx->payment_intent_id = $tx->payment_intent_id ?: $paymentIntentId;
            $tx->status            = $tx->status ?: 'succeeded';
            $tx->paid_at           = $tx->paid_at ?: ($paidAt ?: now());
            $tx->source            = $tx->source ?: 'stripe_webhook';

            $tx->metadata = $this->mergeMetadata($tx->metadata, array_filter([
                'stripe_invoice_id'         => $invoiceId,
                'stripe_invoice_payment_id' => $invoicePaymentId,
            ]));

            $tx->save();

            $this->dbg('invoice_payment.paid: tx updated', $this->txSnap($tx), 'info');
        } else {
            $this->dbg('invoice_payment.paid: no tx found to enrich (pledge updated anyway)', [
                'pledge_id' => $pledge->id,
                'invoice_id' => $invoiceId,
                'payment_intent_id' => $paymentIntentId,
            ], 'info');
        }
    }

    /**
     * Try to find the tx created during `DonationsController@complete`
     * by its stored metadata stripe_invoice_id.
     */
    protected function findTransactionByStripeInvoiceId(string $invoiceId): ?Transaction
    {
        // 1) JSON path (works when metadata is JSON column / cast)
        try {
            $tx = Transaction::query()
                ->where('metadata->stripe_invoice_id', $invoiceId)
                ->latest('id')
                ->first();

            if ($tx) {
                return $tx;
            }
        } catch (\Throwable $e) {
            // ignore and fallback to LIKE
        }

        // 2) TEXT fallback (works even if metadata is stored as stringified JSON)
        return Transaction::query()
            ->where('metadata', 'like', '%' . $invoiceId . '%')
            ->latest('id')
            ->first();
    }

    // -------------------------------------------------------------------------
    // INVOICES (subscriptions)
    // -------------------------------------------------------------------------

    protected function handleInvoicePaid(object $invoice, string $eventType = 'invoice.paid'): void
    {
        $invoiceId  = $invoice->id ?? null;
        $customerId = $this->extractId($invoice->customer ?? null);

        $subscriptionId = $this->resolveSubscriptionIdFromInvoice($invoice);

        $paymentIntentId =
            $this->extractId($invoice->payment_intent ?? null)
            ?: $this->extractId(data_get($invoice, 'payment_intent.id'))
            ?: $this->extractId(data_get($invoice, 'charges.data.0.payment_intent'));

        Log::debug('[STRIPE-DBG] invoice raw', ['invoice' => $invoice]);

        $this->dbg('handleInvoicePaid: computed ids', [
            'invoice_id' => $invoiceId,
            'subscription_id' => $subscriptionId,
            'customer_id' => $customerId,
            'payment_intent_id' => $paymentIntentId,
            'has_charges_data' => (bool) data_get($invoice, 'charges.data.0.id'),
            'billing_reason' => data_get($invoice, 'billing_reason'),
        ], 'info');

        $chargeId =
            $this->extractId($invoice->charge ?? null)
            ?: $this->extractId(data_get($invoice, 'charges.data.0.id'))
            ?: $this->extractId(data_get($invoice, 'payment_intent.latest_charge'))
            ?: $this->extractId(data_get($invoice, 'charges.data.0.charge'));

        $amountPaid = $invoice->amount_paid ?? $invoice->amount_due ?? null;
        $currency   = $invoice->currency ?? 'usd';

        $hostedInvoiceUrl = $invoice->hosted_invoice_url ?? null;
        $payerEmail       = $invoice->customer_email ?? null;

        $payerName = data_get($invoice, 'customer_name')
            ?: data_get($invoice, 'customer_shipping.name')
            ?: null;

        $billingReason = data_get($invoice, 'billing_reason');
        $txType = $billingReason === 'subscription_create'
            ? 'subscription_initial'
            : 'subscription_recurring';

        $this->dbg('handleInvoicePaid: start', [
            'eventType' => $eventType,
            'invoice_id' => $invoiceId,
            'subscription_id' => $subscriptionId,
            'customer_id' => $customerId,
            'payment_intent_id' => $paymentIntentId,
            'charge_id' => $chargeId,
            'billing_reason' => $billingReason,
        ]);

        // ---------------------------------------------------------------------
        // Find pledge
        // ---------------------------------------------------------------------

        $pledge = null;

        if ($subscriptionId) {
            $pledge = Pledge::where('stripe_subscription_id', $subscriptionId)->first();
        }

        if (! $pledge && $customerId) {
            $pledge = Pledge::where('stripe_customer_id', $customerId)->latest('id')->first();
            if ($pledge && ! $subscriptionId) {
                $subscriptionId = $pledge->stripe_subscription_id;
            }
        }

        if (! $pledge) {
            $txFallback = null;

            if ($paymentIntentId) {
                $txFallback = Transaction::query()
                    ->where('payment_intent_id', $paymentIntentId)
                    ->first();
            }

            if (! $txFallback && $chargeId) {
                $txFallback = Transaction::query()
                    ->where('charge_id', $chargeId)
                    ->first();
            }

            if ($txFallback) {
                if (! empty($txFallback->pledge_id)) {
                    $pledge = Pledge::find($txFallback->pledge_id);
                }

                if (! $pledge && ! empty($txFallback->customer_id)) {
                    $pledge = Pledge::where('stripe_customer_id', $txFallback->customer_id)->latest('id')->first();
                }

                if ($pledge) {
                    $customerId     = $customerId     ?: ($txFallback->customer_id     ?: $pledge->stripe_customer_id);
                    $subscriptionId = $subscriptionId ?: ($txFallback->subscription_id ?: $pledge->stripe_subscription_id);
                }
            }
        }

        if (! $pledge) {
            Log::warning('handleInvoicePaid: pledge not found', [
                'invoice_id'        => $invoiceId,
                'subscription_id'   => $subscriptionId,
                'customer_id'       => $customerId,
                'payment_intent_id' => $paymentIntentId,
                'charge_id'         => $chargeId,
            ]);
            return;
        }

        // ---------------------------------------------------------------------
        // Pledge updates (avoid stomping nulls)
        // ---------------------------------------------------------------------

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
            $start = Carbon::createFromTimestamp((int) $periodStartTs);
            $end   = Carbon::createFromTimestamp((int) $periodEndTs);

            $paidAtTs = data_get($invoice, 'status_transitions.paid_at')
                ?: data_get($invoice, 'paid_at');

            $paidAt = $paidAtTs ? Carbon::createFromTimestamp((int) $paidAtTs) : now();

            $pledgeUpdates['current_period_start'] = $start;
            $pledgeUpdates['current_period_end']   = $end;
            $pledgeUpdates['last_pledge_at']        = $paidAt;
            $pledgeUpdates['next_pledge_at']        = $end;
        }

        $this->dbg('handleInvoicePaid: pledge before', $this->pledgeSnap($pledge));
        $pledge->fill($pledgeUpdates)->save();
        $this->dbg('handleInvoicePaid: pledge after', $this->pledgeSnap($pledge));

        $invoicePiPm      = $this->extractId(data_get($invoice, 'payment_intent.payment_method'));
        $defaultInvoicePm = $this->extractId($invoice->default_payment_method ?? null);
        $paymentMethodId  = $invoicePiPm ?: $defaultInvoicePm;

        // ---------------------------------------------------------------------
        // Transaction upsert (never reuse a completed tx for a different invoice)
        // ---------------------------------------------------------------------

        $existingTx = null;
        $matchedByPaymentIntent = false;

        if ($paymentIntentId) {
            $existingTx = Transaction::where('payment_intent_id', $paymentIntentId)->first();
            $matchedByPaymentIntent = (bool) $existingTx;
        }

        if (! $existingTx && $chargeId) {
            $existingTx = Transaction::where('charge_id', $chargeId)->first();
        }

        if (! $existingTx) {
            $existingTx = $this->findExistingSubscriptionTxForPledge(
                pledge: $pledge,
                subscriptionId: $subscriptionId,
                preferredType: $txType,
                invoiceId: $invoiceId
            );
        }

        if (! $matchedByPaymentIntent && $existingTx && $invoiceId) {
            $meta = $existingTx->metadata;

            if (is_string($meta) && $meta !== '') {
                $meta = json_decode($meta, true) ?: [];
            } elseif (! is_array($meta)) {
                $meta = [];
            }

            $existingInvoiceId = data_get($meta, 'stripe_invoice_id');

            if (
                $existingInvoiceId &&
                $existingInvoiceId !== $invoiceId &&
                ! empty($existingTx->payment_intent_id) &&
                ! empty($existingTx->charge_id)
            ) {
                $this->dbg('handleInvoicePaid: refusing to reuse completed tx for different invoice', [
                    'tx_id' => $existingTx->id,
                    'existing_invoice_id' => $existingInvoiceId,
                    'incoming_invoice_id' => $invoiceId,
                ], 'warning');

                $existingTx = null;
            }
        }

        $baseMetadata = array_filter([
            'stripe_invoice_id'      => $invoiceId,
            'stripe_subscription_id' => $subscriptionId,
            'billing_reason'         => $billingReason,
        ]);

        if ($existingTx) {
            $existingTx->attempt_id = $existingTx->attempt_id ?: $pledge->attempt_id;

            $existingTx->amount_cents = $amountPaid ?? $existingTx->amount_cents ?? $pledge->amount_cents;
            $existingTx->currency     = $currency ?? $existingTx->currency;
            $existingTx->status       = 'succeeded';
            $existingTx->source       = 'stripe_webhook';
            $existingTx->receipt_url  = $hostedInvoiceUrl ?: $existingTx->receipt_url;
            $existingTx->paid_at      = $existingTx->paid_at ?? now();

            $existingTx->subscription_id   = $existingTx->subscription_id   ?: $subscriptionId;
            $existingTx->payment_intent_id = $existingTx->payment_intent_id ?: $paymentIntentId;
            $existingTx->charge_id         = $existingTx->charge_id         ?? $chargeId;
            $existingTx->customer_id       = $existingTx->customer_id       ?? $customerId;
            $existingTx->payment_method_id = $existingTx->payment_method_id ?? $paymentMethodId;

            $existingTx->payer_email = $existingTx->payer_email ?? ($payerEmail ?: $pledge->donor_email);
            $existingTx->payer_name  = $existingTx->payer_name  ?? ($payerName ?: $pledge->donor_name);

            $existingTx->metadata = $this->mergeMetadata($existingTx->metadata, $baseMetadata);

            if ($billingReason === 'subscription_create') {
                $existingTx->type = 'subscription_initial';
            } elseif (empty($existingTx->type)) {
                $existingTx->type = $txType;
            }

            $existingTx->save();

            $this->dbg('handleInvoicePaid: tx updated', $this->txSnap($existingTx));
        } else {
            $created = Transaction::create([
                'user_id'           => $pledge->user_id,
                'pledge_id'         => $pledge->id,
                'attempt_id'        => $pledge->attempt_id,
                'subscription_id'   => $subscriptionId,
                'payment_intent_id' => $paymentIntentId,
                'charge_id'         => $chargeId,
                'customer_id'       => $customerId,
                'payment_method_id' => $paymentMethodId,
                'amount_cents'      => $amountPaid ?? $pledge->amount_cents,
                'currency'          => $currency,
                'type'              => $txType,
                'status'            => 'succeeded',
                'source'            => 'stripe_webhook',
                'receipt_url'       => $hostedInvoiceUrl,
                'payer_email'       => $payerEmail ?: $pledge->donor_email,
                'payer_name'        => $payerName ?: $pledge->donor_name,
                'metadata'          => $baseMetadata,
                'paid_at'           => now(),
            ]);

            $this->dbg('handleInvoicePaid: tx created', $this->txSnap($created));
        }
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

        $this->dbg('invoice.payment_failed: pledge past_due', $this->pledgeSnap($pledge), 'warning');
    }

    // -------------------------------------------------------------------------
    // CHARGES
    // -------------------------------------------------------------------------

    protected function handleChargeSucceeded(object $charge): void
    {
        $piId = $this->extractId($charge->payment_intent ?? null);
        if (! $piId) {
            $this->dbg('charge.succeeded missing payment_intent, ignoring', [
                'charge_id' => $charge->id ?? null,
            ], 'info');
            return;
        }

        $customerId = $this->extractId($charge->customer ?? null);
        $invoiceId  = $this->extractId($charge->invoice ?? null);

        $frequency = data_get($charge, 'metadata.frequency');
        $isExplicitOneTime = $frequency === 'one_time';

        $subscriptionPledge = null;
        if ($customerId) {
            $subscriptionPledge = Pledge::query()
                ->where('stripe_customer_id', $customerId)
                ->whereNotNull('stripe_subscription_id')
                ->latest('id')
                ->first();
        }

        $hasRecentOneTimePlaceholder = false;
        if ($customerId) {
            $hasRecentOneTimePlaceholder = Transaction::query()
                ->whereNull('payment_intent_id')
                ->where('type', 'one_time')
                ->where('status', 'pending')
                ->where('customer_id', $customerId)
                ->where('created_at', '>=', now()->subHours(6))
                ->exists();
        }

        // Subscription charge path (invoice present or customer has a subscription pledge)
        if (! $isExplicitOneTime && ! $hasRecentOneTimePlaceholder && ($invoiceId || $subscriptionPledge)) {
            $this->upsertSubscriptionTransactionFromCharge($charge);
            return;
        }

        // One-time charge path
        $tx = Transaction::where('payment_intent_id', $piId)->first();

        if (! $tx) {
            $tx = Transaction::query()
                ->whereNull('payment_intent_id')
                ->where('type', 'one_time')
                ->where('status', 'pending')
                ->when($customerId, fn ($q) => $q->where('customer_id', $customerId))
                ->where('created_at', '>=', now()->subHours(6))
                ->latest('id')
                ->first();
        }

        // As a last resort, try to attach to a recent non-subscription pledge by customer_id
        $fallbackPledge = null;
        if (! $tx && $customerId) {
            $fallbackPledge = Pledge::query()
                ->where('stripe_customer_id', $customerId)
                ->whereNull('stripe_subscription_id')
                ->latest('id')
                ->first();
        }

        if (! $tx) {
            $tx = new Transaction();
            $tx->type     = 'one_time';
            $tx->status   = 'pending';
            $tx->source   = 'stripe_webhook';
            $tx->metadata = ['frequency' => 'one_time'];

            if ($fallbackPledge) {
                $tx->pledge_id  = $fallbackPledge->id;
                $tx->user_id    = $fallbackPledge->user_id;
                $tx->attempt_id = $fallbackPledge->attempt_id;
            }
        }

        $card = data_get($charge, 'payment_method_details.card');

        $tx->payment_intent_id = $tx->payment_intent_id ?: $piId;
        $tx->charge_id         = $tx->charge_id ?? ($charge->id ?? null);
        $tx->customer_id       = $tx->customer_id ?? $customerId;
        $tx->payment_method_id = $tx->payment_method_id ?? $this->extractId($charge->payment_method ?? null);
        $tx->amount_cents      = $charge->amount ?? $tx->amount_cents;
        $tx->currency          = $charge->currency ?? $tx->currency ?? 'usd';
        $tx->receipt_url       = $tx->receipt_url ?? ($charge->receipt_url ?? null);
        $tx->payer_email       = $tx->payer_email ?? data_get($charge, 'billing_details.email');
        $tx->payer_name        = $tx->payer_name ?? data_get($charge, 'billing_details.name');

        $meta = $this->mergeMetadata($tx->metadata, []);

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
        $tx->status   = 'succeeded';
        $tx->paid_at  = $tx->paid_at ?? now();
        $tx->source   = 'stripe_webhook';

        $tx->save();

        $this->dbg('charge.succeeded: one-time tx saved', $this->txSnap($tx));
    }

    /**
     * Subscription charge: create OR enrich exactly one tx row.
     * Prefer placeholder rows; never duplicate.
     */
    protected function upsertSubscriptionTransactionFromCharge(object $charge): void
    {
        $customerId = $this->extractId($charge->customer ?? null);
        $piId       = $this->extractId($charge->payment_intent ?? null);
        $chargeId   = $charge->id ?? null;
        $invoiceId  = $this->extractId($charge->invoice ?? null);

        if (! $customerId || ! $piId) {
            return;
        }

        $pledge = Pledge::query()
            ->where('stripe_customer_id', $customerId)
            ->whereNotNull('stripe_subscription_id')
            ->latest('id')
            ->first();

        if (! $pledge) {
            return;
        }

        // Backfill pledge fields from charge when invoice webhooks don’t carry PI
        $pledgeDirty = false;

        if (empty($pledge->latest_payment_intent_id) && $piId) {
            $pledge->latest_payment_intent_id = $piId;
            $pledgeDirty = true;
        }

        if (empty($pledge->latest_invoice_id) && $invoiceId) {
            $pledge->latest_invoice_id = $invoiceId;
            $pledgeDirty = true;
        }

        if ($pledgeDirty) {
            $this->dbg('charge.succeeded: backfilling pledge PI/invoice', [
                'pledge_id' => $pledge->id,
                'latest_payment_intent_id' => $pledge->latest_payment_intent_id,
                'latest_invoice_id' => $pledge->latest_invoice_id,
            ], 'info');

            $pledge->save();
        }

        $tx = Transaction::where('payment_intent_id', $piId)->first()
            ?: ($chargeId ? Transaction::where('charge_id', $chargeId)->first() : null);

        if (! $tx) {
            $tx = Transaction::query()
                ->where('pledge_id', $pledge->id)
                ->whereIn('type', ['subscription_initial', 'subscription_recurring'])
                ->whereIn('status', ['pending', 'succeeded'])
                ->whereNull('payment_intent_id')
                ->where('created_at', '>=', now()->subHours(24))
                ->latest('id')
                ->first();
        }

        if (! $tx) {
            $tx = new Transaction();
            $tx->pledge_id  = $pledge->id;
            $tx->user_id    = $pledge->user_id;
            $tx->attempt_id = $pledge->attempt_id;
            $tx->type       = 'subscription_recurring';
            $tx->status     = 'pending';
            $tx->metadata   = [];
        }

        $card = data_get($charge, 'payment_method_details.card');

        $tx->user_id           = $tx->user_id ?? $pledge->user_id;
        $tx->pledge_id         = $tx->pledge_id ?? $pledge->id;
        $tx->attempt_id        = $tx->attempt_id ?: $pledge->attempt_id;
        $tx->subscription_id   = $tx->subscription_id ?: $pledge->stripe_subscription_id;

        $tx->payment_intent_id = $tx->payment_intent_id ?: $piId;
        $tx->charge_id         = $tx->charge_id ?? $chargeId;
        $tx->customer_id       = $tx->customer_id ?? $customerId;
        $tx->payment_method_id = $tx->payment_method_id ?? $this->extractId($charge->payment_method ?? null);

        $tx->amount_cents      = $charge->amount ?? $tx->amount_cents ?? $pledge->amount_cents;
        $tx->currency          = $charge->currency ?? $tx->currency ?? 'usd';
        $tx->receipt_url       = $charge->receipt_url ?? $tx->receipt_url;
        $tx->payer_email       = data_get($charge, 'billing_details.email') ?? $tx->payer_email ?? $pledge->donor_email;
        $tx->payer_name        = data_get($charge, 'billing_details.name')  ?? $tx->payer_name  ?? $pledge->donor_name;

        if (empty($tx->type)) {
            $tx->type = 'subscription_recurring';
        }

        $meta = $this->mergeMetadata($tx->metadata, array_filter([
            'stripe_invoice_id'      => $invoiceId,
            'stripe_subscription_id' => $pledge->stripe_subscription_id,
        ]));

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
        $tx->status   = 'succeeded';
        $tx->paid_at  = $tx->paid_at ?? now();
        $tx->source   = 'stripe_webhook';

        $tx->save();

        $this->dbg('charge.succeeded: subscription tx upserted', $this->txSnap($tx));
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

        $refunds = data_get($charge, 'refunds.data', []);
        if (! is_array($refunds)) {
            $refunds = [];
        }

        foreach ($refunds as $refundObj) {
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

        $this->dbg('charge.refunded: tx refunded + refunds upserted', [
            'tx_id' => $tx->id,
            'charge_id' => $chargeId,
            'refund_count' => count($refunds),
        ], 'info');
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

        $updates = [
            'status'               => $sub->status ?? $pledge->status,
            'cancel_at_period_end' => (bool) ($sub->cancel_at_period_end ?? $pledge->cancel_at_period_end),
        ];

        // Stripe subscriptions have current_period_* at the top level.
        $startTs = data_get($sub, 'current_period_start');
        $endTs   = data_get($sub, 'current_period_end');

        if (is_numeric($startTs)) {
            $updates['current_period_start'] = Carbon::createFromTimestamp((int) $startTs);
        }

        if (is_numeric($endTs)) {
            $end = Carbon::createFromTimestamp((int) $endTs);
            $updates['current_period_end'] = $end;
            $updates['next_pledge_at']     = $end;
        }

        $this->dbg('subscription.*: pledge before', $this->pledgeSnap($pledge));
        $pledge->fill($updates)->save();
        $this->dbg('subscription.*: pledge after', $this->pledgeSnap($pledge));
    }

    // -------------------------------------------------------------------------
    // HELPERS
    // -------------------------------------------------------------------------

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

    protected function findExistingSubscriptionTxForPledge(
        Pledge $pledge,
        ?string $subscriptionId,
        ?string $preferredType = null,
        ?string $invoiceId = null
    ): ?Transaction {
        $base = Transaction::query()
            ->where('pledge_id', $pledge->id)
            ->whereIn('type', ['subscription_initial', 'subscription_recurring'])
            ->where(function ($q) use ($subscriptionId) {
                $q->whereNull('subscription_id');
                if ($subscriptionId) {
                    $q->orWhere('subscription_id', $subscriptionId);
                }
            })
            ->where('created_at', '>=', now()->subHours(24))
            ->where(function ($q) use ($invoiceId) {
                $q->whereNull('payment_intent_id')
                    ->orWhereNull('charge_id')
                    ->orWhereNull('metadata->stripe_invoice_id');

                if ($invoiceId) {
                    $q->orWhere('metadata->stripe_invoice_id', $invoiceId);
                }
            });

        if ($invoiceId) {
            $match = (clone $base)
                ->where('metadata->stripe_invoice_id', $invoiceId)
                ->latest('id')
                ->first();

            if ($match) {
                return $match;
            }
        }

        $q = (clone $base)->orderByRaw(
            'CASE
                WHEN payment_intent_id IS NULL THEN 0
                WHEN charge_id IS NULL THEN 1
                ELSE 2
            END'
        );

        if ($preferredType) {
            $q->orderByRaw('CASE WHEN type = ? THEN 0 ELSE 1 END', [$preferredType]);
        }

        return $q->latest('id')->first();
    }

    protected function ensureSubscriptionTransactionFromPaymentIntent(object $pi): bool
    {
        if (empty($pi->invoice)) {
            return false;
        }

        $piId       = $pi->id ?? null;
        $customerId = $this->extractId($pi->customer ?? null);

        if (! $piId || ! $customerId) {
            return false;
        }

        $pledge = Pledge::where('stripe_customer_id', $customerId)->latest('id')->first();
        if (! $pledge) {
            return false;
        }

        $subscriptionId = $pledge->stripe_subscription_id;

        $existing = $this->findExistingSubscriptionTxForPledge($pledge, $subscriptionId);

        if (! $existing) {
            return false;
        }

        $existing->fill([
            'attempt_id'        => $existing->attempt_id ?: $pledge->attempt_id,
            'payment_intent_id' => $existing->payment_intent_id ?: $piId,
            'charge_id'         => $existing->charge_id ?? $this->extractId($pi->latest_charge ?? null),
            'customer_id'       => $existing->customer_id ?? $customerId,
            'payment_method_id' => $existing->payment_method_id ?? $this->extractId($pi->payment_method ?? null),
            'amount_cents'      => $pi->amount_received ?? $pi->amount ?? $existing->amount_cents ?? $pledge->amount_cents,
            'currency'          => $pi->currency ?? $existing->currency ?? 'usd',
            'status'            => 'succeeded',
            'source'            => 'stripe_webhook',
        ]);

        if (empty($existing->subscription_id) && $subscriptionId) {
            $existing->subscription_id = $subscriptionId;
        }

        $existing->metadata = $this->mergeMetadata($existing->metadata, array_filter([
            'stripe_invoice_id'      => $this->extractId($pi->invoice ?? null),
            'stripe_subscription_id' => $subscriptionId,
        ]));

        $existing->paid_at ??= now();
        $existing->save();

        $this->dbg('ensureSubscriptionTransactionFromPaymentIntent: enriched', $this->txSnap($existing));

        return true;
    }

    protected function resolveSubscriptionIdFromInvoice(object $invoice): ?string
    {
        $paths = [
            'subscription',
            'lines.data.0.subscription',
            'lines.data.0.subscription_details.subscription',
            'lines.data.0.parent.subscription_item_details.subscription',
            'lines.data.0.parent.subscription_details.subscription',
            'parent.subscription_details.subscription',
            'subscription_details.subscription',
        ];

        foreach ($paths as $path) {
            $id = $this->extractId(data_get($invoice, $path));
            if ($id) {
                return $id;
            }
        }

        return null;
    }

    protected function dbg(string $message, array $context = [], string $level = 'error'): void
    {
        if (! config('services.stripe.debug_state')) {
            return;
        }

        Log::log($level, '[STRIPE-DBG] ' . $message, $context);
    }

    protected function pledgeSnap(Pledge $pledge): array
    {
        return [
            'pledge_id' => $pledge->id,
            'attempt_id' => $pledge->attempt_id,
            'status' => $pledge->status,
            'stripe_customer_id' => $pledge->stripe_customer_id,
            'stripe_subscription_id' => $pledge->stripe_subscription_id,
            'stripe_price_id' => $pledge->stripe_price_id,
            'setup_intent_id' => $pledge->setup_intent_id,
            'latest_invoice_id' => $pledge->latest_invoice_id,
            'latest_payment_intent_id' => $pledge->latest_payment_intent_id,
            'current_period_start' => optional($pledge->current_period_start)->toDateTimeString(),
            'current_period_end' => optional($pledge->current_period_end)->toDateTimeString(),
            'last_pledge_at' => optional($pledge->last_pledge_at)->toDateTimeString(),
            'next_pledge_at' => optional($pledge->next_pledge_at)->toDateTimeString(),
            'updated_at' => optional($pledge->updated_at)->toDateTimeString(),
        ];
    }

    protected function txSnap(Transaction $tx): array
    {
        return [
            'tx_id' => $tx->id,
            'pledge_id' => $tx->pledge_id,
            'attempt_id' => $tx->attempt_id,
            'type' => $tx->type,
            'status' => $tx->status,
            'payment_intent_id' => $tx->payment_intent_id,
            'subscription_id' => $tx->subscription_id,
            'charge_id' => $tx->charge_id,
            'customer_id' => $tx->customer_id,
            'payment_method_id' => $tx->payment_method_id,
            'amount_cents' => $tx->amount_cents,
            'currency' => $tx->currency,
            'paid_at' => optional($tx->paid_at)->toDateTimeString(),
            'updated_at' => optional($tx->updated_at)->toDateTimeString(),
        ];
    }

    protected function maybeWriteFixture(string $payload, object $event): void
    {
        if (! config('services.stripe.log_webhook_payload', false)) {
            return;
        }

        $dir = storage_path('logs/stripe-fixtures');

        if (! is_dir($dir)) {
            if (! @mkdir($dir, 0755, true) && ! is_dir($dir)) {
                $this->dbg('webhook: fixture dir create FAILED', [
                    'dir' => $dir,
                    'error' => error_get_last(),
                ], 'error');
                return;
            }
        }

        $type = $event->type ?? 'unknown';
        $id   = $event->id ?? uniqid('evt_', true);

        $safeType = preg_replace('/[^a-zA-Z0-9_.-]+/', '_', (string) $type);
        $safeId   = preg_replace('/[^a-zA-Z0-9_.-]+/', '_', (string) $id);

        $path = "{$dir}/{$safeType}.{$safeId}.json";
        $bytes = @file_put_contents($path, $payload);

        if ($bytes === false) {
            $this->dbg('webhook: fixture write FAILED', [
                'file' => $path,
                'error' => error_get_last(),
                'dir_exists' => is_dir($dir),
                'dir_writable' => is_writable($dir),
            ], 'error');
        } else {
            $this->dbg('webhook: wrote fixture', [
                'file' => $path,
                'bytes' => $bytes,
            ]);
        }
    }
}

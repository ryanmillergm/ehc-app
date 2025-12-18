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

        if (! $secret && ! app()->environment('local')) {
            abort(500, 'Stripe webhook secret not configured.');
        }

        try {
            $event = $secret
                ? Webhook::constructEvent($payload, $sigHeader, $secret)
                : json_decode($payload);

            if (! is_object($event)) {
                return response('Invalid payload', 400);
            }
        } catch (UnexpectedValueException|SignatureVerificationException $e) {
            Log::warning('Stripe webhook invalid', ['error' => $e->getMessage()]);
            return response('Invalid payload', 400);
        }

        Log::info('Stripe webhook received', [
            'id'   => $event->id ?? null,
            'type' => $event->type ?? null,
        ]);

        if (config('services.stripe.log_webhook_payload', false)) {
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
            // Only enrich existing subscription placeholder tx (never create new subscription tx here)
            $this->ensureSubscriptionTransactionFromPaymentIntent($pi);

            Log::info('PI succeeded but no transaction found', [
                'payment_intent_id' => $piId,
            ]);

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
    // INVOICES (subscriptions)
    // -------------------------------------------------------------------------

    protected function handleInvoicePaid(object $invoice, string $eventType = 'invoice.paid'): void
    {
        $invoiceId  = $invoice->id ?? null;
        $customerId = $this->extractId($invoice->customer ?? null);

        $subscriptionId  = $this->resolveSubscriptionIdFromInvoice($invoice);
        $paymentIntentId = $this->extractId($invoice->payment_intent ?? null);

        $chargeId = $this->extractId($invoice->charge ?? null)
            ?: $this->extractId(data_get($invoice, 'charges.data.0.id'))
            ?: $this->extractId(data_get($invoice, 'payment_intent.latest_charge'));

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

        $pledge = null;

        // 1) Preferred: resolve by subscription id
        if ($subscriptionId) {
            $pledge = Pledge::where('stripe_subscription_id', $subscriptionId)->first();
        }

        // 2) Next: resolve by customer id
        if (! $pledge && $customerId) {
            $pledge = Pledge::where('stripe_customer_id', $customerId)->latest('id')->first();

            if ($pledge && ! $subscriptionId) {
                $subscriptionId = $pledge->stripe_subscription_id;
            }
        }

        // 3) Fallback: invoice missing customer/subscription => resolve via existing tx
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
                // Prefer direct pledge_id linkage
                if (! empty($txFallback->pledge_id)) {
                    $pledge = Pledge::find($txFallback->pledge_id);
                }

                // Secondary: resolve via tx customer_id
                if (! $pledge && ! empty($txFallback->customer_id)) {
                    $pledge = Pledge::where('stripe_customer_id', $txFallback->customer_id)->latest('id')->first();
                }

                if ($pledge) {
                    $customerId      = $customerId      ?: ($txFallback->customer_id      ?: $pledge->stripe_customer_id);
                    $subscriptionId  = $subscriptionId  ?: ($txFallback->subscription_id  ?: $pledge->stripe_subscription_id);
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
            $start = Carbon::createFromTimestamp($periodStartTs);
            $end   = Carbon::createFromTimestamp($periodEndTs);

            $paidAtTs = data_get($invoice, 'status_transitions.paid_at')
                ?: data_get($invoice, 'paid_at');

            $paidAt = $paidAtTs ? Carbon::createFromTimestamp($paidAtTs) : now();

            $pledgeUpdates['current_period_start'] = $start;
            $pledgeUpdates['current_period_end']   = $end;
            $pledgeUpdates['last_pledge_at']        = $paidAt;
            $pledgeUpdates['next_pledge_at']        = $end;
        }

        $pledge->fill($pledgeUpdates)->save();

        $invoicePiPm      = $this->extractId(data_get($invoice, 'payment_intent.payment_method'));
        $defaultInvoicePm = $this->extractId($invoice->default_payment_method ?? null);
        $paymentMethodId  = $invoicePiPm ?: $defaultInvoicePm;

        // --------------------------------------------------------
        // Transaction upsert
        // --------------------------------------------------------

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

        if ($matchedByPaymentIntent && $invoiceId && $existingTx) {
            $meta = $existingTx->metadata;

            if (is_string($meta) && $meta !== '') {
                $meta = json_decode($meta, true) ?: [];
            } elseif (! is_array($meta)) {
                $meta = [];
            }

            $existingInvoiceId = data_get($meta, 'stripe_invoice_id');

            if ($existingInvoiceId && $existingInvoiceId !== $invoiceId) {
                Log::info('Invoice id changed for same payment_intent_id; trusting webhook', [
                    'payment_intent_id' => $paymentIntentId,
                    'old_invoice_id'    => $existingInvoiceId,
                    'new_invoice_id'    => $invoiceId,
                ]);
            }
        }

        // Only apply the “do not reuse” guard if we did NOT match by payment_intent_id.
        // If we matched by PI, PI is the source-of-truth identity and we should update that row.
        if (! $matchedByPaymentIntent && $existingTx && $invoiceId) {
            $meta = $existingTx->metadata;

            if (is_string($meta) && $meta !== '') {
                $meta = json_decode($meta, true) ?: [];
            } elseif (! is_array($meta)) {
                $meta = [];
            }

            $existingInvoiceId = data_get($meta, 'stripe_invoice_id');

            // If this tx is already “complete” and tied to a different invoice, don’t reuse it.
            if (
                $existingInvoiceId &&
                $existingInvoiceId !== $invoiceId &&
                ! empty($existingTx->payment_intent_id) &&
                ! empty($existingTx->charge_id)
            ) {
                $existingTx = null;
            }
        }

        $baseMetadata = array_filter([
            'stripe_invoice_id'      => $invoiceId,
            'stripe_subscription_id' => $subscriptionId,
            'billing_reason'         => $billingReason,
        ]);

        if ($existingTx) {
            $existingTx->amount_cents = $amountPaid ?? $existingTx->amount_cents ?? $pledge->amount_cents;
            $existingTx->currency     = $currency ?? $existingTx->currency;
            $existingTx->status       = 'succeeded';
            $existingTx->source       = 'stripe_webhook';
            $existingTx->receipt_url  = $hostedInvoiceUrl ?: $existingTx->receipt_url;
            $existingTx->paid_at      = $existingTx->paid_at ?? now();

            if (empty($existingTx->subscription_id) && ! empty($subscriptionId)) {
                $existingTx->subscription_id = $subscriptionId;
            }
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

            $existingTx->payer_email = $existingTx->payer_email ?? ($payerEmail ?: $pledge->donor_email);
            $existingTx->payer_name  = $existingTx->payer_name ?? ($payerName ?: $pledge->donor_name);

            $existingTx->metadata = $this->mergeMetadata($existingTx->metadata, $baseMetadata);

            if ($billingReason === 'subscription_create') {
                $existingTx->type = 'subscription_initial';
            } elseif (empty($existingTx->type)) {
                $existingTx->type = $txType;
            }

            $existingTx->save();
        } else {
            Transaction::create([
                'user_id'           => $pledge->user_id,
                'pledge_id'         => $pledge->id,
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
    }

    // -------------------------------------------------------------------------
    // CHARGES
    // -------------------------------------------------------------------------

    protected function handleChargeSucceeded(object $charge): void
    {
        $piId = $this->extractId($charge->payment_intent ?? null);
        if (! $piId) {
            Log::info('Charge succeeded missing payment_intent, ignoring', [
                'charge_id' => $charge->id ?? null,
            ]);
            return;
        }

        $customerId = $this->extractId($charge->customer ?? null);
        $invoiceId  = $this->extractId($charge->invoice ?? null);

        $frequency = data_get($charge, 'metadata.frequency');
        $isExplicitOneTime = $frequency === 'one_time';

        // Is there a subscription pledge for this customer?
        $subscriptionPledge = null;
        if ($customerId) {
            $subscriptionPledge = Pledge::query()
                ->where('stripe_customer_id', $customerId)
                ->whereNotNull('stripe_subscription_id')
                ->latest('id')
                ->first();
        }

        // If the widget created a one-time placeholder recently, don't steal it into subscription logic.
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

        // Subscription charge if:
        // - invoice id exists (best signal), OR
        // - customer has a subscription pledge (and we don't have a strong one-time signal)
        if (! $isExplicitOneTime && ! $hasRecentOneTimePlaceholder && ($invoiceId || $subscriptionPledge)) {
            $this->upsertSubscriptionTransactionFromCharge($charge);
            return;
        }

        // -------------------------
        // One-time donation charge
        // -------------------------

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

            if (! $tx) {
                $tx = new Transaction();
                $tx->type     = 'one_time';
                $tx->status   = 'pending';
                $tx->source   = 'stripe_webhook';
                $tx->metadata = ['frequency' => 'one_time'];
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

        // 1) strongest matches
        $tx = Transaction::where('payment_intent_id', $piId)->first()
            ?: ($chargeId ? Transaction::where('charge_id', $chargeId)->first() : null);

        // 2) placeholder row for this pledge (created by widget/service)
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

        // 3) if still nothing, we are allowed to create (but only one)
        if (! $tx) {
            $tx = new Transaction();
            $tx->pledge_id = $pledge->id;
            $tx->user_id   = $pledge->user_id;
            $tx->type      = 'subscription_recurring';
            $tx->status    = 'pending';
            $tx->metadata  = [];
        }

        $card = data_get($charge, 'payment_method_details.card');

        $tx->user_id           = $tx->user_id ?? $pledge->user_id;
        $tx->pledge_id         = $tx->pledge_id ?? $pledge->id;
        $tx->subscription_id   = $tx->subscription_id ?: $pledge->stripe_subscription_id;

        $tx->payment_intent_id = $tx->payment_intent_id ?: $piId;
        $tx->charge_id         = $tx->charge_id ?? $chargeId;
        $tx->customer_id       = $tx->customer_id ?? $customerId;
        $tx->payment_method_id = $tx->payment_method_id ?? $this->extractId($charge->payment_method ?? null);

        $tx->amount_cents      = $charge->amount ?? $tx->amount_cents ?? $pledge->amount_cents;
        $tx->currency          = $charge->currency ?? $tx->currency ?? 'usd';
        $tx->receipt_url       = $charge->receipt_url ?? $tx->receipt_url;
        $tx->payer_email       = data_get($charge, 'billing_details.email') ?? $tx->payer_email ?? $pledge->donor_email;
        $tx->payer_name        = data_get($charge, 'billing_details.name') ?? $tx->payer_name ?? $pledge->donor_name;

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

        $startTs = $sub->current_period_start ?? null;
        $endTs   = $sub->current_period_end ?? null;

        if ($startTs) {
            $updates['current_period_start'] = Carbon::createFromTimestamp($startTs);
        }

        if ($endTs) {
            $end = Carbon::createFromTimestamp($endTs);
            $updates['current_period_end'] = $end;
            $updates['next_pledge_at']     = $end;
        }

        $pledge->fill($updates)->save();
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
                // allow invoice webhook to "attach" invoice metadata
                // - OR any row that does NOT yet have stripe_invoice_id
                $q->whereNull('payment_intent_id')
                ->orWhereNull('charge_id')
                ->orWhereNull('metadata->stripe_invoice_id');

                // If invoice id is known, always allow exact matches too
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

    /**
     * Early subscription charge: ONLY enrich an existing tx; never create.
     */
    protected function ensureSubscriptionTransactionFromCharge(object $charge): bool
    {
        $customerId = $this->extractId($charge->customer ?? null);
        $piId       = $this->extractId($charge->payment_intent ?? null);

        if (! $customerId || ! $piId) {
            return false;
        }

        $pledge = Pledge::query()
            ->where('stripe_customer_id', $customerId)
            ->whereNotNull('stripe_subscription_id')
            ->latest('id')
            ->first();

        if (! $pledge) {
            return false;
        }

        $subscriptionId = $pledge->stripe_subscription_id;
        $invoiceId      = $this->extractId($charge->invoice ?? null);

        $tx = Transaction::where('payment_intent_id', $piId)->first();

        if (! $tx) {
            $tx = $this->findExistingSubscriptionTxForPledge(
                pledge: $pledge,
                subscriptionId: $subscriptionId,
                preferredType: null,
                invoiceId: $invoiceId
            );
        }

        if (! $tx) {
            return false;
        }

        if ($tx->pledge_id && (int) $tx->pledge_id !== (int) $pledge->id) {
            return false;
        }

        $card = data_get($charge, 'payment_method_details.card');

        $tx->fill([
            'user_id'           => $tx->user_id ?? $pledge->user_id,
            'pledge_id'         => $tx->pledge_id ?? $pledge->id,
            'subscription_id'   => $tx->subscription_id ?: $subscriptionId,
            'payment_intent_id' => $tx->payment_intent_id ?: $piId,
            'charge_id'         => $tx->charge_id ?? ($charge->id ?? null),
            'customer_id'       => $tx->customer_id ?? $customerId,
            'payment_method_id' => $tx->payment_method_id ?? $this->extractId($charge->payment_method ?? null),
            'amount_cents'      => $charge->amount ?? $tx->amount_cents ?? $pledge->amount_cents,
            'currency'          => $charge->currency ?? $tx->currency ?? 'usd',
            'status'            => 'succeeded',
            'source'            => 'stripe_webhook',
            'receipt_url'       => $charge->receipt_url ?? $tx->receipt_url,
            'payer_email'       => data_get($charge, 'billing_details.email') ?? $tx->payer_email ?? $pledge->donor_email,
            'payer_name'        => data_get($charge, 'billing_details.name') ?? $tx->payer_name ?? $pledge->donor_name,
        ]);

        if (empty($tx->type)) {
            $tx->type = 'subscription_recurring';
        }

        $meta = $this->mergeMetadata($tx->metadata, array_filter([
            'stripe_invoice_id'      => $invoiceId,
            'stripe_subscription_id' => $subscriptionId,
        ]));

        if ($card) {
            $meta = array_merge($meta, array_filter([
                'card_brand'   => $card->brand ?? null,
                'card_last4'   => $card->last4 ?? null,
                'card_country' => $card->country ?? null,
                'card_funding' => $card->funding ?? null,
            ]));
        }

        $tx->metadata = $meta;
        $tx->paid_at  = $tx->paid_at ?? now();
        $tx->save();

        return true;
    }

    /**
     * Early subscription PI: ONLY enrich an existing tx; never create.
     */
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
}

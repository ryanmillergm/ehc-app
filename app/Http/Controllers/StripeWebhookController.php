<?php

namespace App\Http\Controllers;

use Throwable;
use App\Models\Pledge;
use App\Models\Refund;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Stripe\StripeClient;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;
use UnexpectedValueException;
use Illuminate\Database\UniqueConstraintViolationException;
use App\Support\Stripe\TransactionResolver;

class StripeWebhookController extends Controller
{
    public function __construct(
        protected ?StripeClient $stripe = null,
    ) {
    }

    protected function stripeClient(): ?StripeClient
    {
        if ($this->stripe instanceof StripeClient) {
            return $this->stripe;
        }

        $secret = trim((string) config('services.stripe.secret'));
        if ($secret === '') {
            return null;
        }

        $this->stripe = new StripeClient($secret);
        return $this->stripe;
    }

    /**
     * Stripe webhook endpoint.
     */
    public function __invoke(Request $request)
    {
        $payload   = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature', '');
        $secret    = config('services.stripe.webhook_secret');

        $this->dbg('webhook: hit', [
            'path'           => $request->path(),
            'ip'             => $request->ip(),
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
        } catch (Throwable $e) {
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
            'type'      => $type,
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


            case 'refund.created':
            case 'refund.updated':
            case 'refund.failed':
                $this->handleRefundCreatedOrUpdated($object);
                break;

            case 'charge.refund.updated':
                $this->handleChargeRefundUpdated($object);
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
            // For subscription payments Stripe will send invoice.* events.
            // Do not create or “claim” anything here to avoid duplicates.
            $this->dbg('PI succeeded but no transaction found (waiting for invoice event)', [
                'payment_intent_id' => $piId,
                'invoice'           => $this->extractId($pi->invoice ?? null),
                'customer'          => $this->extractId($pi->customer ?? null),
            ], 'info');

            return;
        }

        $tx->status  = 'succeeded';
        $tx->paid_at = $tx->paid_at ?? now();
        $tx->source  = 'stripe_webhook';

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
            'tx_id'             => $tx->id,
            'payment_intent_id' => $piId,
        ], 'warning');
    }

    // -------------------------------------------------------------------------
    // INVOICE PAYMENTS (Basil)
    // -------------------------------------------------------------------------

    /**
     * `invoice_payment.paid` carries the PI in:
     *   data.object.payment.payment_intent = "pi_..."
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
            'invoice_id'         => $invoiceId,
            'payment_intent_id'  => $paymentIntentId,
            'paid_at_ts'         => $paidAtTs,
        ], 'info');

        if (! $invoiceId) {
            $this->dbg('invoice_payment.paid: missing invoice id', [
                'invoice_payment_id' => $invoicePaymentId,
            ], 'warning');
            return;
        }

        // Prefer the canonical column first (back-compat fallback inside)
        $tx = app(TransactionResolver::class)->resolveForInvoice($pledge, $invoiceId, $paymentIntentId);

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
                'invoice_id'        => $invoiceId,
                'payment_intent_id' => $paymentIntentId,
                'tx_id'             => $tx?->id,
            ], 'warning');
            return;
        }

        // ---- Update pledge (do NOT write nulls over values) ----
        $this->dbg('invoice_payment.paid: pledge before', $this->pledgeSnap($pledge), 'info');

        $pledgeUpdates = ['status' => 'active'];

        $pledgeUpdates['latest_invoice_id'] = $invoiceId;

        if ($paymentIntentId) {
            $pledgeUpdates['latest_payment_intent_id'] = $paymentIntentId;
        }

        if ($paidAt) {
            $pledgeUpdates['last_pledge_at'] = $paidAt;
        }

        $pledge->fill($pledgeUpdates)->save();

        $this->dbg('invoice_payment.paid: pledge after', $this->pledgeSnap($pledge), 'info');

        // ---- Enrich the tx (no creation here) ----
        if ($tx) {
            if ($paymentIntentId) {
                // PI ownership: if another tx already owns this PI, enrich THAT tx instead.
                $owner = Transaction::where('payment_intent_id', $paymentIntentId)->first();
                if ($owner && $owner->id !== $tx->id) {
                    $this->dbg('invoice_payment.paid: PI already claimed; using owner', [
                        'pi'       => $paymentIntentId,
                        'owner_tx' => $owner->id,
                        'tx'       => $tx->id,
                    ], 'warning');
                    $tx = $owner;
                }
            }

            $tx->stripe_invoice_id  = $tx->stripe_invoice_id ?: $invoiceId; // ✅ NEW column
            $tx->payment_intent_id  = $tx->payment_intent_id ?: $paymentIntentId;
            $tx->status             = 'succeeded';
            $tx->paid_at            = $tx->paid_at ?: ($paidAt ?: now());
            $tx->source             = $tx->source ?: 'stripe_webhook';

            $tx->metadata = $this->mergeMetadata($tx->metadata, array_filter([
                'stripe_invoice_id'         => $invoiceId, // keep metadata too
                'stripe_invoice_payment_id' => $invoicePaymentId,
            ]));

            $tx->save();

            $this->dbg('invoice_payment.paid: tx updated', $this->txSnap($tx), 'info');
        } else {
            $this->dbg('invoice_payment.paid: no tx found to enrich (pledge updated anyway)', [
                'pledge_id'         => $pledge->id,
                'invoice_id'        => $invoiceId,
                'payment_intent_id' => $paymentIntentId,
            ], 'info');
        }
    }

    /**
     * Find tx by invoice id (prefers the column; falls back to older metadata storage).
     */
    protected function findTransactionByStripeInvoiceId(string $invoiceId): ?Transaction
    {
        $tx = Transaction::query()
            ->where('stripe_invoice_id', $invoiceId)
            ->latest('id')
            ->first();

        if ($tx) {
            return $tx;
        }

        // Back-compat: older rows that only stored it in metadata
        try {
            $tx = Transaction::query()
                ->where('metadata->stripe_invoice_id', $invoiceId)
                ->latest('id')
                ->first();

            if ($tx) {
                return $tx;
            }
        } catch (Throwable $e) {
            // ignore and fallback to LIKE
        }

        return Transaction::query()
            ->where('metadata', 'like', '%' . $invoiceId . '%')
            ->latest('id')
            ->first();
    }

    // -------------------------------------------------------------------------
    // INVOICES (subscriptions) - CANONICAL
    // -------------------------------------------------------------------------
    protected function claimPaymentIntentId(Transaction $tx, ?string $paymentIntentId): void
    {
        if (! $paymentIntentId) {
            return;
        }

        $owner = Transaction::query()
            ->where('payment_intent_id', $paymentIntentId)
            ->lockForUpdate()
            ->first();

        // Unclaimed or already ours: safe to set.
        if (! $owner || (int) $owner->id === (int) $tx->id) {
            $tx->payment_intent_id = $paymentIntentId;
            return;
        }

        // Someone else already owns it. Do NOT set (would violate unique index).
        $this->dbg('handleInvoicePaid: payment_intent_id already owned; skipping claim on this tx', [
            'payment_intent_id' => $paymentIntentId,
            'owner_tx_id'       => $owner->id,
            'current_tx_id'     => $tx->id,
            'invoice_id'        => $tx->stripe_invoice_id,
        ], 'warning');
    }

    protected function claimChargeId(Transaction $tx, ?string $chargeId): void
    {
        if (! $chargeId) {
            return;
        }

        // Only do this if your DB enforces unique charge_id.
        $owner = Transaction::query()
            ->where('charge_id', $chargeId)
            ->lockForUpdate()
            ->first();

        if (! $owner || (int) $owner->id === (int) $tx->id) {
            $tx->charge_id = $chargeId;
            return;
        }

        $this->dbg('handleInvoicePaid: charge_id already owned; skipping claim on this tx', [
            'charge_id'     => $chargeId,
            'owner_tx_id'   => $owner->id,
            'current_tx_id' => $tx->id,
        ], 'warning');
    }



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
            'invoice_id'        => $invoiceId,
            'subscription_id'   => $subscriptionId,
            'customer_id'       => $customerId,
            'payment_intent_id' => $paymentIntentId,
            'has_charges_data'  => (bool) data_get($invoice, 'charges.data.0.id'),
            'billing_reason'    => data_get($invoice, 'billing_reason'),
        ], 'info');

        $chargeId =
            $this->extractId($invoice->charge ?? null)
            ?: $this->extractId(data_get($invoice, 'charges.data.0.id'))
            ?: $this->extractId(data_get($invoice, 'payment_intent.latest_charge'))
            ?: $this->extractId(data_get($invoice, 'charges.data.0.charge'));

        // Fallback: if Stripe didn't include a charge id on the invoice payload, look it up via the PaymentIntent.
        // This happens sometimes unless the event payload expanded `latest_charge`.
        if (! $chargeId && $paymentIntentId && ! app()->runningUnitTests() && (bool) config('services.stripe.webhook_api_fallback', true)) {
            $client = $this->stripeClient();

            if ($client) {
                try {
                    $pi = $client->paymentIntents->retrieve($paymentIntentId, [
                        'expand' => ['latest_charge'],
                    ]);

                    $chargeId = $chargeId
                        ?: $this->extractId($pi->latest_charge ?? null)
                        ?: (is_object($pi->latest_charge ?? null) ? ($pi->latest_charge->id ?? null) : null);
                } catch (\Throwable $e) {
                    $this->dbg('invoice.paid: payment_intent lookup failed', [
                        'payment_intent_id' => $paymentIntentId,
                        'error' => $e->getMessage(),
                    ]);
                }
            } else {
                $this->dbg('invoice.paid: cannot lookup payment_intent (missing Stripe secret)', [
                    'payment_intent_id' => $paymentIntentId,
                ]);
            }
        }

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
            'eventType'         => $eventType,
            'invoice_id'        => $invoiceId,
            'subscription_id'   => $subscriptionId,
            'customer_id'       => $customerId,
            'payment_intent_id' => $paymentIntentId,
            'charge_id'         => $chargeId,
            'billing_reason'    => $billingReason,
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
        // Transaction upsert
        // ---------------------------------------------------------------------

        $existingTx = null;
        $matchedByPaymentIntent = false;

        // Canonical identity for invoice events: invoice id.
        // If invoiceId exists, we ONLY update the tx that already belongs to this invoice.
        if ($invoiceId) {
            $existingTx = Transaction::query()
                ->where('pledge_id', $pledge->id)
                ->where('stripe_invoice_id', $invoiceId)
                ->first();

            if (! $existingTx) {
                $existingTx = Transaction::query()
                    ->where('pledge_id', $pledge->id)
                    ->where('metadata->stripe_invoice_id', $invoiceId)
                    ->first();
            }
if ($existingTx) {
                $this->dbg('handleInvoicePaid: matched tx by invoice id', [
                    'tx_id'      => $existingTx->id,
                    'invoice_id' => $invoiceId,
                ], 'info');
            }
        }

        $hasStrongTxKeys = (bool) ($paymentIntentId || $chargeId);

        // If PI + charge are missing, do NOT try to match some other tx for the pledge/subscription.
        // We’ll create a new tx for this invoice id (idempotent on invoice id).
        if (! $existingTx && ! $hasStrongTxKeys) {
            // We still try to enrich an existing *pending* placeholder (widget-created) tx
            // for this pledge/subscription, but we refuse to reuse completed txs.
            $existingTx = Transaction::query()
                ->where('pledge_id', $pledge->id)
                ->when($subscriptionId, fn ($q) => $q->where('subscription_id', $subscriptionId))
                ->whereIn('type', ['subscription_initial', 'subscription_recurring'])
                ->where(function ($q) use ($invoiceId) {
                    // must be unclaimed or already this invoice
                    $q->whereNull('stripe_invoice_id')
                    ->orWhere('stripe_invoice_id', $invoiceId);
                })
                ->where(function ($q) {
                    // pending-ish only
                    $q->whereNull('paid_at')
                    ->orWhereIn('status', ['pending', 'incomplete', 'requires_payment_method']);
                })
                ->latest('id')
                ->first();

            if ($existingTx) {
                $this->dbg('handleInvoicePaid: matched pending tx without PI/charge', [
                    'tx_id'      => $existingTx->id,
                    'invoice_id' => $invoiceId,
                ], 'info');
            } else {
                $this->dbg('handleInvoicePaid: no PI/charge and no pending placeholder; will create tx keyed by invoice id', [
                    'invoice_id'      => $invoiceId,
                    'pledge_id'       => $pledge->id,
                    'subscription_id' => $subscriptionId,
                    'customer_id'     => $customerId,
                ], 'info');
            }
        }

        // Only try the broader matching helpers if:
        // - we did NOT already match by invoice id, AND
        // - we DO have at least one strong key (PI or charge)
        if (! $existingTx && $hasStrongTxKeys) {
            $existingTx = $this->findExistingSubscriptionTxForPledge(
                pledge: $pledge,
                subscriptionId: $subscriptionId,
                preferredType: $txType,
                invoiceId: $invoiceId
            );
        }

        if (! $existingTx && $paymentIntentId) {
            $existingTx = Transaction::where('payment_intent_id', $paymentIntentId)->first();
            $matchedByPaymentIntent = (bool) $existingTx;
        }

        if (! $existingTx && $chargeId) {
            $existingTx = Transaction::where('charge_id', $chargeId)->first();
        }

        // If PI already belongs to another tx, use the owner (don’t steal).
        if ($paymentIntentId) {
            $owner = Transaction::where('payment_intent_id', $paymentIntentId)->first();
            if ($owner) {
                if (! $existingTx || $owner->id !== $existingTx->id) {

                    // If we already have the canonical invoice-owned transaction, do NOT jump to the PI owner.
                    // This prevents violating the unique (pledge_id, stripe_invoice_id) constraint when some's created
                    // a stray row that happened to claim the payment_intent_id.
                    if ($invoiceId && $existingTx && $existingTx->stripe_invoice_id && (string) $existingTx->stripe_invoice_id === (string) $invoiceId) {
                        $this->dbg('handleInvoicePaid: PI already claimed elsewhere; keeping canonical invoice tx', [
                            'pi'               => $paymentIntentId,
                            'owner_tx'         => $owner->id,
                            'canonical_tx'     => $existingTx->id,
                            'invoice_id'       => $invoiceId,
                        ], 'warning');
                    } else {
                        $this->dbg('handleInvoicePaid: PI already claimed; using owner tx', [
                            'pi'         => $paymentIntentId,
                            'owner_tx'   => $owner->id,
                            'current_tx' => $existingTx?->id,
                            'invoice_id' => $invoiceId,
                        ], 'warning');

                        $existingTx = $owner;
                        $matchedByPaymentIntent = true;
                    }
                }
            }
        }

        // Guard: refuse to reuse a completed tx for a different invoice (unless matched by PI)
        if (! $matchedByPaymentIntent && $existingTx && $invoiceId) {
            $existingInvoiceId = $existingTx->stripe_invoice_id
                ?: data_get($existingTx->metadata, 'stripe_invoice_id');

            if (
                $existingInvoiceId &&
                $existingInvoiceId !== $invoiceId &&
                ! empty($existingTx->payment_intent_id) &&
                ! empty($existingTx->charge_id)
            ) {
                $this->dbg('handleInvoicePaid: refusing to reuse completed tx for different invoice', [
                    'tx_id'               => $existingTx->id,
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
            'stage'                  => 'invoice_paid',
        ]);

        // FINAL SAFETY: payment_intent_id is unique.
        // If a tx already exists for this PI, we MUST update it (invoice id may differ).
        if (! $existingTx && $paymentIntentId) {
            $existingTx = Transaction::query()
                ->where('payment_intent_id', $paymentIntentId)
                ->first();

            if ($existingTx) {
                $matchedByPaymentIntent = true;

                $this->dbg('handleInvoicePaid: found existing tx by PI at last-second guard', [
                    'tx_id'      => $existingTx->id,
                    'pi'         => $paymentIntentId,
                    'invoice_id' => $invoiceId,
                ], 'warning');
            }
        }

        if ($existingTx) {
            $existingTx->attempt_id = $existingTx->attempt_id ?: $pledge->attempt_id;

            $existingTx->amount_cents = $amountPaid ?? $existingTx->amount_cents ?? $pledge->amount_cents;
            $existingTx->currency     = $currency ?? $existingTx->currency;
            $existingTx->status       = 'succeeded';
            $existingTx->setStage('invoice_paid');

            $existingTx->source       = 'stripe_webhook';
            $existingTx->receipt_url  = $hostedInvoiceUrl ?: $existingTx->receipt_url;
            $existingTx->paid_at      = $existingTx->paid_at ?? now();



            // If a different transaction already owns this (pledge_id, stripe_invoice_id) pair, switch to it as canonical.
            // This avoids violating the unique (pledge_id, stripe_invoice_id) constraint when we matched the "wrong" row first
            // (e.g. a row that only had invoice id in metadata or matched by payment_intent_id).
            if ($invoiceId) {
                $canonicalByInvoice = Transaction::query()
                    ->where('pledge_id', $pledge->id)
                    ->where('stripe_invoice_id', $invoiceId)
                    ->lockForUpdate()
                    ->first();

                if ($canonicalByInvoice && (int) $canonicalByInvoice->id !== (int) $existingTx->id) {
                    // Merge any strong Stripe keys we already discovered onto the canonical row without stealing unique ids.
                    $canonicalByInvoice->subscription_id = $canonicalByInvoice->subscription_id ?: ($existingTx->subscription_id ?: $subscriptionId);
                    $canonicalByInvoice->attempt_id      = $canonicalByInvoice->attempt_id      ?: $existingTx->attempt_id;

                    $this->claimPaymentIntentId($canonicalByInvoice, $existingTx->payment_intent_id ?? null);
                    $this->claimChargeId($canonicalByInvoice, $existingTx->charge_id ?? null);

                    $canonicalByInvoice->payment_method_id = $canonicalByInvoice->payment_method_id ?: $existingTx->payment_method_id;
                    $canonicalByInvoice->customer_id       = $canonicalByInvoice->customer_id       ?: $existingTx->customer_id;
                    $canonicalByInvoice->payer_email       = $canonicalByInvoice->payer_email       ?: $existingTx->payer_email;
                    $canonicalByInvoice->payer_name        = $canonicalByInvoice->payer_name        ?: $existingTx->payer_name;

                    // Preserve any metadata already captured on either row.
                    $canonicalByInvoice->metadata = $this->mergeMetadata($canonicalByInvoice->metadata, (array) ($existingTx->metadata ?? []));

                    $existingTx = $canonicalByInvoice;

                    $this->dbg('handleInvoicePaid: switched to canonical tx by invoice', [
                        'pledge_id'       => $pledge->id,
                        'invoice_id'      => $invoiceId,
                        'canonical_tx_id' => $canonicalByInvoice->id,
                    ], 'info');
                }
            }

            $existingTx->subscription_id   = $existingTx->subscription_id   ?: $subscriptionId;

            // allow webhook invoice to win when we matched by PI (out-of-order / mismatch)
                        if ($invoiceId) {
                if (! $existingTx->stripe_invoice_id) {
                    $existingTx->stripe_invoice_id = $invoiceId;
                } elseif ((string) $existingTx->stripe_invoice_id !== (string) $invoiceId) {
                    // Never overwrite an existing invoice id on an already-linked tx.
                    // If we got here, either the event is out-of-order or we matched the wrong tx.
                    // The canonical-by-invoice switch above should handle the common case; this is a final guard.
                    $this->dbg('handleInvoicePaid: tx already has different stripe_invoice_id; not overwriting', [
                        'tx_id'              => $existingTx->id,
                        'existing_invoice_id'=> $existingTx->stripe_invoice_id,
                        'incoming_invoice_id'=> $invoiceId,
                        'payment_intent_id'  => $paymentIntentId,
                    ], 'warning');
                }
            }



            // Guard: payment_intent_id is unique. If another row already owns this PI, we either
            // (a) move it to the canonical invoice row (when it's clearly a duplicate metadata-only row), or
            // (b) refuse to assign it here to avoid blowing up on the unique index.
            if ($paymentIntentId) {
                $piOwner = Transaction::query()
                    ->where('payment_intent_id', $paymentIntentId)
                    ->lockForUpdate()
                    ->first();

                if ($piOwner && (int) $piOwner->id !== (int) $existingTx->id) {
                    $samePledge = (int) $piOwner->pledge_id === (int) $existingTx->pledge_id;

                    $ownerLooksLikeDuplicate = $samePledge
                        && $invoiceId
                        && empty($piOwner->stripe_invoice_id)
                        && data_get($piOwner->metadata, 'stripe_invoice_id') === $invoiceId;

                    if ($ownerLooksLikeDuplicate) {
                        // Un-claim PI from the duplicate row so the canonical invoice row can own it.
                        $piOwner->payment_intent_id = null;
                        $piOwner->save();
                    } else {
                        // Someone else owns this PI (possibly a real one-time tx). Don't steal it.
                        $paymentIntentId = null;
                    }
                }
            }

            if (! $existingTx->payment_intent_id) {
                $this->claimPaymentIntentId($existingTx, $paymentIntentId);
            }
            if (! $existingTx->charge_id) {
                $this->claimChargeId($existingTx, $chargeId);
            }
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
            
            $pledge->setStage('active', save: true);

            $this->dbg('handleInvoicePaid: tx updated', $this->txSnap($existingTx));
        } else {
            try {
                $created = Transaction::create([
                    'user_id'           => $pledge->user_id,
                    'pledge_id'         => $pledge->id,
                    'attempt_id'        => $pledge->attempt_id,
                    'subscription_id'   => $subscriptionId,
                    'stripe_invoice_id' => $invoiceId,
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
            } catch (UniqueConstraintViolationException $e) {
                // Concurrency / retry safety: another request inserted the tx first.
                // Recover by finding the canonical row by unique PI and updating it.
                $this->dbg('handleInvoicePaid: unique violation on create; recovering', [
                    'pi'         => $paymentIntentId,
                    'invoice_id' => $invoiceId,
                    'error'      => $e->getMessage(),
                ], 'warning');

                $existingTx = $paymentIntentId
                    ? Transaction::query()->where('payment_intent_id', $paymentIntentId)->first()
                    : null;

                if (! $existingTx) {
                    // If we can't recover, rethrow so you see the real issue in logs/tests.
                    throw $e;
                }

                $matchedByPaymentIntent = true;

                // Apply the same enrichment/update logic as above (minimal duplication)
                $existingTx->attempt_id = $existingTx->attempt_id ?: $pledge->attempt_id;

                $existingTx->amount_cents = $amountPaid ?? $existingTx->amount_cents ?? $pledge->amount_cents;
                $existingTx->currency     = $currency ?? $existingTx->currency;
                $existingTx->status       = 'succeeded';
                $existingTx->source       = 'stripe_webhook';
                $existingTx->receipt_url  = $hostedInvoiceUrl ?: $existingTx->receipt_url;
                $existingTx->paid_at      = $existingTx->paid_at ?? now();

                $existingTx->subscription_id   = $existingTx->subscription_id   ?: $subscriptionId;
                $existingTx->stripe_invoice_id = $invoiceId; // webhook wins in recovery path too
                if (! $existingTx->payment_intent_id) {
                $this->claimPaymentIntentId($existingTx, $paymentIntentId);
            }
                if (! $existingTx->charge_id) {
                $this->claimChargeId($existingTx, $chargeId);
            }
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

                $this->dbg('handleInvoicePaid: recovered tx updated', $this->txSnap($existingTx));
            }
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
    // CHARGES (unchanged from your pasted file)
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

        // Canonical rule: if charge is tied to an invoice, subscription-land is handled by invoice.* events.
        if ($invoiceId) {
            $this->dbg('charge.succeeded: has invoice id, ignoring (invoice handler is canonical)', [
                'charge_id'  => $charge->id ?? null,
                'invoice_id' => $invoiceId,
                'pi'         => $piId,
                'customer'   => $customerId,
            ], 'info');
            return;
        }

        // Only treat as one-time if it is explicitly labeled one_time in metadata.
        // One-time path: if there's NO invoice, we treat it as a one-time donation.
        // Subscription charges should carry an invoice and are handled by invoice.* handlers.
        $frequency = data_get($charge, 'metadata.frequency'); // may be missing

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

        // If Stripe ever sends a charge without invoice but it's actually subscription-ish,
        // don't create a tx unless we can associate it cleanly.
        if (! $tx && ! $frequency && ! $customerId) {
            $this->dbg('charge.succeeded: no invoice but missing customer + no metadata; ignoring', [
                'charge_id' => $charge->id ?? null,
                'pi'        => $piId,
            ], 'warning');
            return;
        }

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

        // Controller helper (Transaction may not have mergeMetadata()).
        $meta = $this->mergeMetadata($tx->metadata, [
            'frequency' => data_get($tx->metadata, 'frequency', 'one_time'),
        ]);

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

        $refunds = data_get($charge, 'refunds.data', []);
        if (! is_array($refunds)) {
            $refunds = [];
        }

        foreach ($refunds as $refundObj) {
            if (! is_object($refundObj)) {
                continue;
            }

            $refundId = $refundObj->id ?? null;
            if (! $refundId) {
                continue;
            }

            Refund::updateOrCreate(
                ['stripe_refund_id' => $refundId],
                [
                    'transaction_id' => $tx->id,
                    'charge_id'      => $chargeId,
                    'amount_cents'   => (int) ($refundObj->amount ?? 0),
                    'currency'       => (string) ($refundObj->currency ?? $tx->currency ?? 'usd'),
                    'status'         => (string) ($refundObj->status ?? 'succeeded'),
                    'reason'         => $refundObj->reason ?? null,
                    'metadata'       => (array) ($refundObj->metadata ?? []),
                ]
            );
        }

        // Determine full vs partial refund.
        // Prefer Stripe's amount_refunded when present; otherwise sum succeeded refunds in DB.
        $amountRefunded = data_get($charge, 'amount_refunded');
        if ($amountRefunded === null) {
            $amountRefunded = (int) Refund::query()
                ->where('charge_id', $chargeId)
                ->where('status', 'succeeded')
                ->sum('amount_cents');
        } else {
            $amountRefunded = (int) $amountRefunded;
        }

        if ($amountRefunded >= (int) $tx->amount_cents) {
            $tx->status = 'refunded';
        } elseif ($amountRefunded > 0) {
            $tx->status = 'partially_refunded';
        }

        $tx->save();

        $this->dbg('charge.refunded: refunds upserted + tx status updated', [
            'tx_id'          => $tx->id,
            'charge_id'      => $chargeId,
            'refund_count'   => count($refunds),
            'amountRefunded' => $amountRefunded,
            'tx_amount'      => (int) $tx->amount_cents,
            'tx_status'      => $tx->status,
        ], 'info');
    }



    protected function handleRefundCreatedOrUpdated(object $refund): void
    {
        $refundId = $refund->id ?? null;
        $chargeId = $this->extractId($refund->charge ?? null);

        if (! $refundId || ! $chargeId) {
            return;
        }

        $tx = Transaction::where('charge_id', $chargeId)->first();
        if (! $tx) {
            return;
        }

        Refund::updateOrCreate(
            ['stripe_refund_id' => $refundId],
            [
                'transaction_id' => $tx->id,
                'charge_id'      => $chargeId,
                'amount_cents'   => (int) ($refund->amount ?? 0),
                'currency'       => (string) ($refund->currency ?? $tx->currency ?? 'usd'),
                'status'         => (string) ($refund->status ?? 'succeeded'),
                'reason'         => $refund->reason ?? null,
                'metadata'       => (array) ($refund->metadata ?? []),
            ]
        );

        // Compute total succeeded refunds so partial refunds are represented correctly.
        $totalSucceededRefunded = (int) Refund::query()
            ->where('charge_id', $chargeId)
            ->where('status', 'succeeded')
            ->sum('amount_cents');

        if ($totalSucceededRefunded >= (int) $tx->amount_cents) {
            $tx->status = 'refunded';
        } elseif ($totalSucceededRefunded > 0) {
            $tx->status = 'partially_refunded';
        }

        $tx->save();

        $this->dbg('refund.*: upserted refund + updated tx status', [
            'tx_id'                 => $tx->id,
            'charge_id'             => $chargeId,
            'refund_id'             => $refundId,
            'refund_status'         => (string) ($refund->status ?? 'succeeded'),
            'total_succeeded_cents' => $totalSucceededRefunded,
            'tx_amount'             => (int) $tx->amount_cents,
            'tx_status'             => $tx->status,
        ], 'info');
    }

    protected function handleChargeRefundUpdated(object $refund): void
    {
        // Stripe sends a Refund object for charge.refund.updated.
        $this->handleRefundCreatedOrUpdated($refund);
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
                // Column-first incomplete marker
                $q->whereNull('payment_intent_id')
                    ->orWhereNull('charge_id')
                    ->orWhereNull('stripe_invoice_id');

                if ($invoiceId) {
                    $q->orWhere('stripe_invoice_id', $invoiceId);

                    // Back-compat for older rows:
                    $q->orWhere('metadata->stripe_invoice_id', $invoiceId);
                }
            });

        if ($invoiceId) {
            $match = (clone $base)
                ->where(function ($q) use ($invoiceId) {
                    $q->where('stripe_invoice_id', $invoiceId)
                      ->orWhere('metadata->stripe_invoice_id', $invoiceId);
                })
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
            'pledge_id'                 => $pledge->id,
            'attempt_id'                => $pledge->attempt_id,
            'status'                    => $pledge->status,
            'stripe_customer_id'        => $pledge->stripe_customer_id,
            'stripe_subscription_id'    => $pledge->stripe_subscription_id,
            'stripe_price_id'           => $pledge->stripe_price_id,
            'setup_intent_id'           => $pledge->setup_intent_id,
            'latest_invoice_id'         => $pledge->latest_invoice_id,
            'latest_payment_intent_id'  => $pledge->latest_payment_intent_id,
            'current_period_start'      => optional($pledge->current_period_start)->toDateTimeString(),
            'current_period_end'        => optional($pledge->current_period_end)->toDateTimeString(),
            'last_pledge_at'            => optional($pledge->last_pledge_at)->toDateTimeString(),
            'next_pledge_at'            => optional($pledge->next_pledge_at)->toDateTimeString(),
            'updated_at'                => optional($pledge->updated_at)->toDateTimeString(),
        ];
    }

    protected function txSnap(Transaction $tx): array
    {
        return [
            'tx_id'            => $tx->id,
            'pledge_id'        => $tx->pledge_id,
            'attempt_id'       => $tx->attempt_id,
            'type'             => $tx->type,
            'status'           => $tx->status,
            'payment_intent_id'=> $tx->payment_intent_id,
            'subscription_id'  => $tx->subscription_id,
            'stripe_invoice_id'=> $tx->stripe_invoice_id, // ✅
            'charge_id'        => $tx->charge_id,
            'customer_id'      => $tx->customer_id,
            'payment_method_id'=> $tx->payment_method_id,
            'amount_cents'     => $tx->amount_cents,
            'currency'         => $tx->currency,
            'paid_at'          => optional($tx->paid_at)->toDateTimeString(),
            'updated_at'       => optional($tx->updated_at)->toDateTimeString(),
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
                    'dir'   => $dir,
                    'error' => error_get_last(),
                ], 'error');
                return;
            }
        }

        $type = $event->type ?? 'unknown';
        $id   = $event->id ?? uniqid('evt_', true);

        $safeType = preg_replace('/[^a-zA-Z0-9_.-]+/', '_', (string) $type);
        $safeId   = preg_replace('/[^a-zA-Z0-9_.-]+/', '_', (string) $id);

        $path  = "{$dir}/{$safeType}.{$safeId}.json";
        $bytes = @file_put_contents($path, $payload);

        if ($bytes === false) {
            $this->dbg('webhook: fixture write FAILED', [
                'file'         => $path,
                'error'        => error_get_last(),
                'dir_exists'   => is_dir($dir),
                'dir_writable' => is_writable($dir),
            ], 'error');
        } else {
            $this->dbg('webhook: wrote fixture', [
                'file'  => $path,
                'bytes' => $bytes,
            ]);
        }
    }
}
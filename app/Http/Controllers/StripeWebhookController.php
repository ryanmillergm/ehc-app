<?php

namespace App\Http\Controllers;

use Throwable;
use App\Models\Pledge;
use App\Models\Refund;
use App\Models\Transaction;
use App\Support\Stripe\InteractsWithStripeMetadata;
use App\Support\Stripe\TransactionInvoiceLinker;
use App\Support\Stripe\TransactionResolver;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Stripe\StripeClient;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;
use UnexpectedValueException;
use Illuminate\Database\QueryException;

class StripeWebhookController extends Controller
{
    use InteractsWithStripeMetadata;

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
    public function __invoke(Request $request): JsonResponse
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
            // Misconfiguration: we still return 200 to avoid Stripe retry storms,
            // but we log loudly so it gets fixed.
            Log::error('Stripe webhook secret not configured.');
            return response()->json(['ok' => true, 'error' => true]);
        }

        try {
            $event = $secret
                ? Webhook::constructEvent($payload, $sigHeader, $secret)
                : json_decode($payload);

            if (! is_object($event)) {
                $this->dbg('webhook: payload decoded but not object', [], 'warning');
                return response()->json(['ok' => false], 400);
            }
        } catch (UnexpectedValueException|SignatureVerificationException $e) {
            // In local dev, optionally allow unsigned payloads so you can test without Stripe CLI sig headers.
            if (app()->environment('local') && config('services.stripe.debug_state')) {
                $this->dbg('webhook: signature invalid (local) - falling back to unsigned decode', [
                    'error' => $e->getMessage(),
                ], 'warning');

                $event = json_decode($payload);
                if (! is_object($event)) {
                    return response()->json(['ok' => false], 400);
                }
            } else {
                Log::warning('Stripe webhook invalid', ['error' => $e->getMessage()]);
                return response()->json(['ok' => false], 400);
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
            // IMPORTANT: never 500 a Stripe webhook for internal bookkeeping issues.
            // A 500 causes Stripe retries, which can amplify races / duplicates.
            Log::error('Stripe webhook handler crashed (swallowed)', [
                'event_id'   => $event->id ?? null,
                'event_type' => $event->type ?? null,
                'error'      => $e->getMessage(),
            ]);
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

        // Ensure metadata is never lost.
        $tx->metadata = $this->mergeMetadata($tx->metadata, [
            'stage' => 'payment_intent_succeeded',
        ]);

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
        $tx->metadata = $this->mergeMetadata($tx->metadata, [
            'stage' => 'payment_intent_failed',
        ]);

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
        // Pattern: resolve → update → (optional create) → never throw.

        $invoicePaymentId = $this->extractId($inpay->id ?? null); // inpay_...
        $invoiceId        = $this->extractId($inpay->invoice ?? null); // in_...
        $paymentIntentId  = $this->extractId(data_get($inpay, 'payment.payment_intent'))
            ?: $this->extractId(data_get($inpay, 'payment_intent')); // defensive

        $paidAtTs = data_get($inpay, 'status_transitions.paid_at');
        $paidAt   = is_numeric($paidAtTs) ? Carbon::createFromTimestamp((int) $paidAtTs) : null;

        if (! $invoiceId) {
            $this->dbg('invoice_payment.paid: missing invoice id', [
                'invoice_payment_id' => $invoicePaymentId,
            ], 'warning');
            return;
        }

        // ---------------------------------------------------------------------
        // Resolve pledge + best tx hint.
        //
        // IMPORTANT: invoice_payment.paid happens *because* the invoice exists.
        // In your system, the invoice id is first stashed into tx.metadata during
        // subscription creation. So the most reliable anchor is:
        //   Transaction where metadata->stripe_invoice_id == $invoiceId
        // ---------------------------------------------------------------------

        $txHint = Transaction::query()
            ->whereJsonContains('metadata->stripe_invoice_id', $invoiceId)
            ->orderByDesc('id')
            ->first();

        $pledge = null;

        if ($txHint?->pledge_id) {
            $pledge = Pledge::find($txHint->pledge_id);
        }

        // Fallbacks (best-effort, never throw)
        if (! $pledge && $paymentIntentId) {
            $byPi = Transaction::query()->where('payment_intent_id', $paymentIntentId)->first();
            if ($byPi?->pledge_id) {
                $txHint ??= $byPi;
                $pledge = Pledge::find($byPi->pledge_id);
            }
        }

        if (! $pledge && $txHint?->customer_id) {
            $pledge = Pledge::query()
                ->where('stripe_customer_id', $txHint->customer_id)
                ->latest('id')
                ->first();
        }

        // Last-ditch: if your pledge sometimes already has latest_invoice_id set, keep it.
        if (! $pledge) {
            $pledge = Pledge::query()
                ->where('latest_invoice_id', $invoiceId)
                ->latest('id')
                ->first();
        }

        if (! $pledge) {
            $this->dbg('invoice_payment.paid: pledge not found', [
                'invoice_id'        => $invoiceId,
                'payment_intent_id' => $paymentIntentId,
                'tx_id'             => $txHint?->id,
            ], 'warning');
            return;
        }

        /** @var TransactionResolver $resolver */
        $resolver = app(TransactionResolver::class);

        /** @var TransactionInvoiceLinker $invoiceLinker */
        $invoiceLinker = app(TransactionInvoiceLinker::class);

        try {
            DB::transaction(function () use (
                $pledge,
                $invoiceId,
                $paymentIntentId,
                $paidAt,
                $invoicePaymentId,
                $resolver,
                $invoiceLinker,
                $txHint
            ) {
                // Prefer resolver; if it returns null, fall back to the txHint we found via metadata.
                $tx = $resolver->resolveForInvoice($pledge, $invoiceId, $paymentIntentId) ?: $txHint;

                // If resolver returned something tied to a *different* invoice, don't mutate it into this invoice.
                if (
                    $tx
                    && $invoiceId
                    && ! empty($tx->stripe_invoice_id)
                    && (string) $tx->stripe_invoice_id !== (string) $invoiceId
                ) {
                    $tx = null;
                }

                if (! $tx) {
                    // If we already have a row for this pledge+invoice, use it (prevents duplicates).
                    $existing = Transaction::query()
                        ->where('pledge_id', $pledge->id)
                        ->where('stripe_invoice_id', $invoiceId)
                        ->lockForUpdate()
                        ->first();

                    if ($existing) {
                        $tx = $existing;
                    } else {
                        // Optional: if there is NO PI and NO txHint, we still can safely create because invoice_id is unique per pledge.
                        // But we should still protect against null invoice_id.
                        if (! $invoiceId) {
                            $this->dbg('invoice_payment.paid: cannot create tx without invoice id', [
                                'pledge_id' => $pledge->id,
                                'pi'        => $paymentIntentId,
                            ], 'warning');
                            return;
                        }

                        $tx = Transaction::create([
                            'user_id'           => $pledge->user_id,
                            'pledge_id'         => $pledge->id,
                            'attempt_id'        => $pledge->attempt_id,
                            'subscription_id'   => $pledge->stripe_subscription_id,
                            'stripe_invoice_id' => $invoiceId,
                            'status'            => 'pending',
                            'source'            => 'stripe_webhook',
                            'type'              => 'subscription_recurring',
                            'currency'          => $pledge->currency ?: 'usd',
                            'amount_cents'      => $pledge->amount_cents,
                            'metadata'          => [
                                'stripe_invoice_id' => $invoiceId,
                                'stage'             => 'invoice_payment_paid',
                            ],
                        ]);
                    }
                }

                // Now canonicalize (this is still good)
                $tx = $invoiceLinker->adoptOwnerIfInvoiceClaimed($tx, $pledge->id, $invoiceId);

                // Update tx (avoid stomping nulls).
                if (! $tx->stripe_invoice_id) {
                    $tx->stripe_invoice_id = $invoiceId;
                }

                if (! $tx->payment_intent_id && $paymentIntentId) {
                    $invoiceLinker->claimPaymentIntentId($tx, $paymentIntentId);
                }

                // Mark succeeded (this event means "paid").
                $tx->status = 'succeeded';

                if (! $tx->paid_at) {
                    $tx->paid_at = $paidAt ?: now();
                }

                $tx->source = 'stripe_webhook';

                $tx->metadata = $this->mergeMetadata($tx->metadata, array_filter([
                    'stripe_invoice_id'         => $invoiceId,
                    'stripe_invoice_payment_id' => $invoicePaymentId,
                    'stage'                     => 'invoice_payment_paid',
                ]));

                $tx->save();

                // ---------------------------
                // Pledge updates (non-clobber)
                // ---------------------------

                $pledge->status = 'active';

                // latest_invoice_id: set if empty OR same (never stomp a different invoice id)
                if (empty($pledge->latest_invoice_id)) {
                    $pledge->latest_invoice_id = $invoiceId;
                } elseif ($pledge->latest_invoice_id !== $invoiceId) {
                    $this->dbg('invoice_payment.paid: pledge latest_invoice_id differs; not overwriting', [
                        'pledge_id' => $pledge->id,
                        'existing'  => $pledge->latest_invoice_id,
                        'incoming'  => $invoiceId,
                    ], 'warning');
                }

                // latest_payment_intent_id: set if empty OR same (never stomp a different PI)
                if ($paymentIntentId) {
                    if (empty($pledge->latest_payment_intent_id)) {
                        $pledge->latest_payment_intent_id = $paymentIntentId;
                    } elseif ($pledge->latest_payment_intent_id !== $paymentIntentId) {
                        $this->dbg('invoice_payment.paid: pledge latest_payment_intent_id differs; not overwriting', [
                            'pledge_id' => $pledge->id,
                            'existing'  => $pledge->latest_payment_intent_id,
                            'incoming'  => $paymentIntentId,
                        ], 'warning');
                    }
                }

                // last_pledge_at: only move forward in time
                if ($paidAt) {
                    if (empty($pledge->last_pledge_at) || $paidAt->greaterThan($pledge->last_pledge_at)) {
                        $pledge->last_pledge_at = $paidAt;
                    }
                }

                $pledge->save();
            }, 3);
        } catch (QueryException $e) {
            if (! $this->isUniqueViolation($e)) {
                $this->dbg('invoice_payment.paid: QueryException (non-unique) swallowed', [
                    'invoice_id' => $invoiceId,
                    'error'      => $e->getMessage(),
                ], 'error');
                return;
            }

            // Recover from races: re-resolve canonical and try again (swallow all errors).
            $this->dbg('invoice_payment.paid: unique violation; recovering', [
                'pledge_id'  => $pledge->id,
                'invoice_id' => $invoiceId,
                'pi'         => $paymentIntentId,
                'error'      => $e->getMessage(),
            ], 'warning');

            try {
                DB::transaction(function () use ($pledge, $invoiceId, $paymentIntentId, $paidAt, $invoicePaymentId, $resolver, $invoiceLinker) {
                    $tx = $resolver->resolveForInvoice($pledge, $invoiceId, $paymentIntentId);
                    if (! $tx) {
                        return;
                    }

                    $tx = $invoiceLinker->adoptOwnerIfInvoiceClaimed($tx, $pledge->id, $invoiceId);

                    if (! $tx->payment_intent_id && $paymentIntentId) {
                        $invoiceLinker->claimPaymentIntentId($tx, $paymentIntentId);
                    }

                    $tx->status  = 'succeeded';
                    $tx->paid_at = $tx->paid_at ?: ($paidAt ?: now());
                    $tx->source  = 'stripe_webhook';

                    $tx->metadata = $this->mergeMetadata($tx->metadata, array_filter([
                        'stripe_invoice_id'         => $invoiceId,
                        'stripe_invoice_payment_id' => $invoicePaymentId,
                        'stage'                     => 'invoice_payment_paid',
                    ]));

                    $tx->save();

                    // Also try to ensure pledge latest fields are set (non-clobber)
                    if (empty($pledge->latest_invoice_id)) {
                        $pledge->latest_invoice_id = $invoiceId;
                    }
                    if ($paymentIntentId && empty($pledge->latest_payment_intent_id)) {
                        $pledge->latest_payment_intent_id = $paymentIntentId;
                    }
                    if ($paidAt && (empty($pledge->last_pledge_at) || $paidAt->greaterThan($pledge->last_pledge_at))) {
                        $pledge->last_pledge_at = $paidAt;
                    }
                    if ($pledge->status !== 'active') {
                        $pledge->status = 'active';
                    }

                    $pledge->save();
                }, 3);
            } catch (Throwable $e2) {
                $this->dbg('invoice_payment.paid: recovery failed (swallowed)', [
                    'invoice_id' => $invoiceId,
                    'error'      => $e2->getMessage(),
                ], 'warning');
            }
        } catch (Throwable $e) {
            $this->dbg('invoice_payment.paid: exception (swallowed)', [
                'pledge_id'  => $pledge->id,
                'invoice_id' => $invoiceId,
                'pi'         => $paymentIntentId,
                'error'      => $e->getMessage(),
            ], 'error');
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
        } catch (Throwable) {
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

    protected function handleInvoicePaid(object $invoice, string $eventType = 'invoice.paid'): void
    {
        // This event means money moved. Our job is to record it *idempotently*.
        // Pattern: resolve → update → (optional create) → recover on unique → never throw.

        $invoiceId  = $invoice->id ?? null;
        $customerId = $this->extractId($invoice->customer ?? null);

        $subscriptionId = $this->resolveSubscriptionIdFromInvoice($invoice);

        $paymentIntentId =
            $this->extractId($invoice->payment_intent ?? null)
            ?: $this->extractId(data_get($invoice, 'payment_intent.id'))
            ?: $this->extractId(data_get($invoice, 'charges.data.0.payment_intent'));

        $chargeId =
            $this->extractId($invoice->charge ?? null)
            ?: $this->extractId(data_get($invoice, 'charges.data.0.id'))
            ?: $this->extractId(data_get($invoice, 'payment_intent.latest_charge'))
            ?: $this->extractId(data_get($invoice, 'charges.data.0.charge'));

        // Optional API fallback for charge id.
        if (
            ! $chargeId
            && $paymentIntentId
            && ! app()->runningUnitTests()
            && (bool) config('services.stripe.webhook_api_fallback', true)
        ) {
            $client = $this->stripeClient();

            if ($client) {
                try {
                    $pi = $client->paymentIntents->retrieve($paymentIntentId, [
                        'expand' => ['latest_charge'],
                    ]);

                    $chargeId = $chargeId
                        ?: $this->extractId($pi->latest_charge ?? null)
                        ?: (is_object($pi->latest_charge ?? null) ? ($pi->latest_charge->id ?? null) : null);
                } catch (Throwable $e) {
                    $this->dbg('invoice.paid: payment_intent lookup failed', [
                        'payment_intent_id' => $paymentIntentId,
                        'error'             => $e->getMessage(),
                    ], 'warning');
                }
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

        // ---------------------------------------------------------------------
        // Find pledge (best-effort)
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

        // Paid-at (if present in invoice)
        $paidAtTs = data_get($invoice, 'status_transitions.paid_at') ?: data_get($invoice, 'paid_at');
        $paidAt   = is_numeric($paidAtTs) ? Carbon::createFromTimestamp((int) $paidAtTs) : null;

        // Update pledge “softly” (don’t stomp nulls)
        try {
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

                $pledgeUpdates['current_period_start'] = $start;
                $pledgeUpdates['current_period_end']   = $end;
                $pledgeUpdates['last_pledge_at']        = $paidAt ?: now();
                $pledgeUpdates['next_pledge_at']        = $end;
            }

            $pledge->fill($pledgeUpdates)->save();
        } catch (Throwable $e) {
            $this->dbg('handleInvoicePaid: pledge update failed (swallowed)', [
                'pledge_id' => $pledge->id,
                'error'     => $e->getMessage(),
            ], 'warning');
        }

        $invoicePiPm      = $this->extractId(data_get($invoice, 'payment_intent.payment_method'));
        $defaultInvoicePm = $this->extractId($invoice->default_payment_method ?? null);
        $paymentMethodId  = $invoicePiPm ?: $defaultInvoicePm;

        /** @var TransactionResolver $resolver */
        $resolver = app(TransactionResolver::class);

        /** @var TransactionInvoiceLinker $invoiceLinker */
        $invoiceLinker = app(TransactionInvoiceLinker::class);

        $baseMetadata = array_filter([
            'stripe_invoice_id'      => $invoiceId,
            'stripe_subscription_id' => $subscriptionId,
            'billing_reason'         => $billingReason,
            'stage'                  => 'invoice_paid',
            'event_type'             => $eventType,
        ]);

        try {
            DB::transaction(function () use (
                $pledge,
                $invoiceId,
                $paymentIntentId,
                $chargeId,
                $amountPaid,
                $currency,
                $hostedInvoiceUrl,
                $customerId,
                $paymentMethodId,
                $payerEmail,
                $payerName,
                $subscriptionId,
                $billingReason,
                $txType,
                $paidAt,
                $resolver,
                $invoiceLinker,
                $baseMetadata
            ) {
                $tx = $resolver->resolveForInvoice($pledge, $invoiceId, $paymentIntentId);
                if (
                    $tx
                    && $invoiceId
                    && ! empty($tx->stripe_invoice_id)
                    && (string) $tx->stripe_invoice_id !== (string) $invoiceId
                ) {
                    $tx = null;
                }

                if (! $tx) {
                    // invoice.paid without an invoice id is useless to us.
                    if (! $invoiceId) {
                        $this->dbg('invoice.paid: missing invoice id; cannot resolve or create tx', [
                            'pledge_id'        => $pledge->id,
                            'payment_intent_id'=> $paymentIntentId,
                            'charge_id'        => $chargeId,
                        ], 'warning');
                        return;
                    }

                    // Prevent duplicates: if a row already exists for this pledge+invoice, reuse it.
                    $existing = Transaction::query()
                        ->where('pledge_id', $pledge->id)
                        ->where('stripe_invoice_id', $invoiceId)
                        ->lockForUpdate()
                        ->first();

                    $tx = $existing ?: Transaction::create([
                        'user_id'           => $pledge->user_id,
                        'pledge_id'         => $pledge->id,
                        'attempt_id'        => $pledge->attempt_id,
                        'subscription_id'   => $subscriptionId,
                        'stripe_invoice_id' => $invoiceId,
                        'status'            => 'pending',
                        'source'            => 'stripe_webhook',
                        'type'              => $txType,
                        'currency'          => $currency,
                        'amount_cents'      => $amountPaid ?? $pledge->amount_cents,
                    ]);
                }

                $tx = $invoiceLinker->adoptOwnerIfInvoiceClaimed($tx, $pledge->id, $invoiceId);

                $tx->attempt_id      = $tx->attempt_id ?: $pledge->attempt_id;
                $tx->subscription_id = $tx->subscription_id ?: $subscriptionId;
                $tx->stripe_invoice_id = $tx->stripe_invoice_id ?: $invoiceId;

                if (! $tx->payment_intent_id && $paymentIntentId) {
                    $invoiceLinker->claimPaymentIntentId($tx, $paymentIntentId);
                }
                if (! $tx->charge_id && $chargeId) {
                    $invoiceLinker->claimChargeId($tx, $chargeId);
                }

                $tx->customer_id       = $tx->customer_id       ?? $customerId;
                $tx->payment_method_id = $tx->payment_method_id ?? $paymentMethodId;

                $tx->payer_email = $tx->payer_email ?? ($payerEmail ?: $pledge->donor_email);
                $tx->payer_name  = $tx->payer_name  ?? ($payerName  ?: $pledge->donor_name);
                $tx->receipt_url = $tx->receipt_url ?? $hostedInvoiceUrl;

                $tx->amount_cents = $amountPaid ?? $tx->amount_cents ?? $pledge->amount_cents;
                $tx->currency     = $currency ?? $tx->currency ?? 'usd';

                $tx->paid_at = $tx->paid_at ?? ($paidAt ?: now());
                $tx->status  = 'succeeded';
                $tx->source  = 'stripe_webhook';

                $tx->type = ($billingReason === 'subscription_create')
                    ? 'subscription_initial'
                    : ($tx->type ?: $txType);

                $tx->metadata = $this->mergeMetadata($tx->metadata, $baseMetadata);
                $tx->setStage('invoice_paid');

                $tx->save();

                $pledge->setStage('active', save: true);
            });
        } catch (QueryException $e) {
            if (! $this->isUniqueViolation($e)) {
                $this->dbg('handleInvoicePaid: QueryException (non-unique) swallowed', [
                    'invoice_id' => $invoiceId,
                    'error'      => $e->getMessage(),
                ], 'error');
                return;
            }

            $this->dbg('handleInvoicePaid: unique violation; recovering', [
                'pledge_id'  => $pledge->id,
                'invoice_id' => $invoiceId,
                'pi'         => $paymentIntentId,
                'error'      => $e->getMessage(),
            ], 'warning');

            try {
                DB::transaction(function () use (
                    $pledge,
                    $invoiceId,
                    $paymentIntentId,
                    $chargeId,
                    $amountPaid,
                    $currency,
                    $hostedInvoiceUrl,
                    $paidAt,
                    $resolver,
                    $invoiceLinker,
                    $baseMetadata
                ) {
                    $tx = $resolver->resolveForInvoice($pledge, $invoiceId, $paymentIntentId);

                    // Same safety rule during recovery: don't "recover" into a different invoice tx.
                    if (
                        $tx
                        && $invoiceId
                        && ! empty($tx->stripe_invoice_id)
                        && (string) $tx->stripe_invoice_id !== (string) $invoiceId
                    ) {
                        return;
                    }

                    if (! $tx) {
                        return;
                    }

                    $tx = $invoiceLinker->adoptOwnerIfInvoiceClaimed($tx, $pledge->id, $invoiceId);

                    if (! $tx->payment_intent_id && $paymentIntentId) {
                        $invoiceLinker->claimPaymentIntentId($tx, $paymentIntentId);
                    }
                    if (! $tx->charge_id && $chargeId) {
                        $invoiceLinker->claimChargeId($tx, $chargeId);
                    }

                    $tx->amount_cents = $amountPaid ?? $tx->amount_cents ?? $pledge->amount_cents;
                    $tx->currency     = $currency ?? $tx->currency ?? 'usd';
                    $tx->receipt_url  = $tx->receipt_url ?? $hostedInvoiceUrl;

                    $tx->paid_at = $tx->paid_at ?? ($paidAt ?: now());
                    $tx->status  = 'succeeded';
                    $tx->source  = 'stripe_webhook';

                    $tx->metadata = $this->mergeMetadata($tx->metadata, $baseMetadata);
                    $tx->setStage('invoice_paid');

                    $tx->save();

                    $pledge->setStage('active', save: true);
                });
            } catch (Throwable $e2) {
                $this->dbg('handleInvoicePaid: recovery failed (swallowed)', [
                    'invoice_id' => $invoiceId,
                    'error'      => $e2->getMessage(),
                ], 'warning');
            }
        } catch (Throwable $e) {
            $this->dbg('handleInvoicePaid: exception (swallowed)', [
                'pledge_id'  => $pledge->id,
                'invoice_id' => $invoiceId,
                'pi'         => $paymentIntentId,
                'error'      => $e->getMessage(),
            ], 'error');
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

        // One-time path: if there's NO invoice, we treat it as a one-time donation.
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

        $tx->payment_intent_id = $tx->payment_intent_id ?: $piId;
        $tx->charge_id         = $tx->charge_id ?? ($charge->id ?? null);
        $tx->customer_id       = $tx->customer_id ?? $customerId;
        $tx->payment_method_id = $tx->payment_method_id ?? $this->extractId($charge->payment_method ?? null);
        $tx->amount_cents      = $charge->amount ?? $tx->amount_cents;
        $tx->currency          = $charge->currency ?? $tx->currency ?? 'usd';
        $tx->receipt_url       = $tx->receipt_url ?? ($charge->receipt_url ?? null);
        $tx->payer_email       = $tx->payer_email ?? data_get($charge, 'billing_details.email');
        $tx->payer_name        = $tx->payer_name ?? data_get($charge, 'billing_details.name');

        $tx->metadata = $this->mergeMetadata($tx->metadata, array_filter([
            'frequency' => data_get($tx->metadata, 'frequency', 'one_time'),
            'stage' => 'charge_succeeded',
        ]));

        $tx->metadata = $this->mergeMetadata($tx->metadata, $this->cardMetaFromChargeObject($charge));

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

        $tx->metadata = $this->mergeMetadata($tx->metadata, [
            'stage' => 'charge_refunded',
        ]);

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

        $tx->metadata = $this->mergeMetadata($tx->metadata, [
            'stage' => 'refund_created_or_updated',
        ]);

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

    protected function isUniqueViolation(QueryException $e): bool
    {
        $sqlState = $e->errorInfo[0] ?? null; // e.g. 23000
        $driverCode = $e->errorInfo[1] ?? null; // e.g. 1062 in MySQL
        return $sqlState === '23000' || (string) $driverCode === '1062';
    }

    /**
     * Stripe PHP SDK Charge objects are real classes; webhook payload charges are generic objects.
     * The InteractsWithStripeMetadata trait expects Charge, so we normalize.
     */
    protected function cardMetaFromChargeObject(object $charge): array
    {
        $card = data_get($charge, 'payment_method_details.card');
        if (! is_object($card)) {
            return [];
        }

        return array_filter([
            'card_brand'     => $card->brand ?? null,
            'card_last4'     => $card->last4 ?? null,
            'card_country'   => $card->country ?? null,
            'card_funding'   => $card->funding ?? null,
            'card_exp_month' => $card->exp_month ?? null,
            'card_exp_year'  => $card->exp_year ?? null,
        ]);
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
            'pledge_id'                => $pledge->id,
            'attempt_id'               => $pledge->attempt_id,
            'status'                   => $pledge->status,
            'stripe_customer_id'       => $pledge->stripe_customer_id,
            'stripe_subscription_id'   => $pledge->stripe_subscription_id,
            'stripe_price_id'          => $pledge->stripe_price_id,
            'setup_intent_id'          => $pledge->setup_intent_id,
            'latest_invoice_id'        => $pledge->latest_invoice_id,
            'latest_payment_intent_id' => $pledge->latest_payment_intent_id,
            'current_period_start'     => optional($pledge->current_period_start)->toDateTimeString(),
            'current_period_end'       => optional($pledge->current_period_end)->toDateTimeString(),
            'last_pledge_at'           => optional($pledge->last_pledge_at)->toDateTimeString(),
            'next_pledge_at'           => optional($pledge->next_pledge_at)->toDateTimeString(),
            'updated_at'               => optional($pledge->updated_at)->toDateTimeString(),
        ];
    }

    protected function txSnap(Transaction $tx): array
    {
        return [
            'tx_id'             => $tx->id,
            'pledge_id'         => $tx->pledge_id,
            'attempt_id'        => $tx->attempt_id,
            'type'              => $tx->type,
            'status'            => $tx->status,
            'payment_intent_id' => $tx->payment_intent_id,
            'subscription_id'   => $tx->subscription_id,
            'stripe_invoice_id' => $tx->stripe_invoice_id,
            'charge_id'         => $tx->charge_id,
            'customer_id'       => $tx->customer_id,
            'payment_method_id' => $tx->payment_method_id,
            'amount_cents'      => $tx->amount_cents,
            'currency'          => $tx->currency,
            'paid_at'           => optional($tx->paid_at)->toDateTimeString(),
            'updated_at'        => optional($tx->updated_at)->toDateTimeString(),
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

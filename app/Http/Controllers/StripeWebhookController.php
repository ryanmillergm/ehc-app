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
        // If the container already has a StripeClient (ex: FakeStripeClient in tests),
        // use it. This avoids hitting the network and avoids needing STRIPE_SECRET in tests.
        if (app()->bound(StripeClient::class)) {
            $client = app(StripeClient::class);
            if ($client instanceof StripeClient) {
                return $client;
            }
        }

        // Existing behavior for production/local.
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
            // In local dev OR unit tests, optionally allow unsigned payloads so tests can POST fixtures without Stripe headers.
            if ((app()->environment('local') || app()->runningUnitTests()) && config('services.stripe.debug_state')) {
                $this->dbg('webhook: signature invalid (local/testing) - falling back to unsigned decode', [
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

            if (app()->runningUnitTests()) {
                throw $e;
            }

            // In production: never 500 a webhook for bookkeeping issues.
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

            // Treat invoice_payment.paid as an alias for invoice paid/succeeded.
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

        $invoiceId = $this->extractId($pi->invoice ?? null)
            ?: $this->extractId(data_get($pi, 'invoice.id'));

        if ($invoiceId) {
            $this->dbg('payment_intent.succeeded: invoice-linked; invoice is single writer', [
                'payment_intent_id' => $piId,
                'invoice_id'        => $invoiceId,
            ], 'info');

            return;
        }

        $tx = Transaction::query()
            ->where('payment_intent_id', $piId)
            ->first();

        if (! $tx) {
            $this->dbg('payment_intent.succeeded: no existing tx to update; ignoring to prevent duplicates', [
                'payment_intent_id' => $piId,
                'invoice_id'        => $invoiceId,
                'customer'          => $this->extractId($pi->customer ?? null),
                'latest_charge'     => $this->extractId($pi->latest_charge ?? null),
            ], 'warning');

            return;
        }

        /** @var \App\Support\Stripe\TransactionInvoiceLinker $linker */
        $linker = app(\App\Support\Stripe\TransactionInvoiceLinker::class);

        // Enrich identifiers safely (unique-safe)
        $latestChargeId = $this->extractId($pi->latest_charge ?? null);
        if (empty($tx->charge_id) && $latestChargeId) {
            $tx = $linker->claimChargeId($tx, $latestChargeId);
        }

        if (empty($tx->payment_method_id) && ! empty($pi->payment_method)) {
            $tx->payment_method_id = $this->extractId($pi->payment_method);
        }

        if (empty($tx->customer_id) && ! empty($pi->customer)) {
            $tx->customer_id = $this->extractId($pi->customer);
        }

        $isSubscriptionTx = ! empty($invoiceId)
            || ! empty($tx->pledge_id)
            || in_array($tx->type, ['subscription_initial', 'subscription_recurring'], true);

        // Only one-time: PI succeeded is allowed to mark paid
        if (! $isSubscriptionTx) {
            if ($tx->status !== 'succeeded') {
                $tx->status = 'succeeded';
            }

            $tx->paid_at = $tx->paid_at ?? now();

            $tx->setStage('paid');

            // Preserve historical metadata, but assert truth
            $tx->metadata = $this->mergeMetadata($tx->metadata, [
                'event'  => 'payment_intent.succeeded',
                'writer' => 'payment_intent_succeeded',
            ]);

            // Do not stomp source; only fill if empty
            $tx->source = $tx->source ?: 'stripe_webhook';
        } else {
            // Subscription world: enrichment only (invoice.* is single writer for paid)
            $this->dbg('payment_intent.succeeded: subscription-related; enriched only (invoice is single writer)', [
                'tx_id'     => $tx->id,
                'tx_type'   => $tx->type,
                'pledge_id' => $tx->pledge_id,
                'pi'        => $piId,
                'invoice_id'=> $invoiceId,
            ], 'info');
        }

        // Ensure metadata is never lost.
        $tx->metadata = $this->mergeMetadata($tx->metadata, array_filter([
            'event'      => 'payment_intent.succeeded',
            'invoice_id' => $invoiceId,
        ]));

        $tx->save();

        $this->dbg('payment_intent.succeeded: updated tx', $this->txSnap($tx), 'info');
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
     * `invoice_payment.paid` (Basil) is NOT a writer.
     * It only exists to bridge to invoice.* where the invoice is the single writer.
     *
     * It carries:
     *   data.object.invoice = "in_..."
     *   data.object.payment.payment_intent = "pi_..."
     */
    protected function handleInvoicePaymentPaid(object $inpay): void
    {
        $invoiceId = $this->extractId($inpay->invoice ?? null);

        if (! $invoiceId) {
            $this->dbg('invoice_payment.paid: missing invoice id; cannot forward to invoice handler', [
                'invoice_payment_id' => $this->extractId($inpay->id ?? null),
                'pi' => $this->extractId(data_get($inpay, 'payment.payment_intent'))
                    ?: $this->extractId(data_get($inpay, 'payment_intent')),
            ], 'warning');

            return;
        }

        // In tests we do NOT hit the network — we bind a FakeStripeClient.
        // If no client is available (misconfigured test), bail gracefully.
        $client = $this->stripeClient();
        if (! $client) {
            $this->dbg('invoice_payment.paid: stripe client unavailable; cannot retrieve invoice', [
                'invoice_id'  => $invoiceId,
                'unit_tests'  => app()->runningUnitTests(),
            ], 'warning');

            return;
        }

        try {
            $invoice = $client->invoices->retrieve($invoiceId, [
                'expand' => [
                    'payment_intent',
                    'payment_intent.latest_charge',
                    'charge',
                    'customer',
                    'subscription',
                    'lines.data.price',
                ],
            ]);

            // Single-writer: forward to invoice handler.
            $this->handleInvoicePaid($invoice, 'invoice_payment.paid');
        } catch (Throwable $e) {
            $this->dbg('invoice_payment.paid: invoice retrieval failed; cannot forward', [
                'invoice_id' => $invoiceId,
                'error'      => $e->getMessage(),
            ], 'warning');
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
        $invoiceId  = $this->extractId($invoice->id ?? null);
        $customerId = $this->extractId($invoice->customer ?? null);

        $subscriptionId = $this->resolveSubscriptionIdFromInvoice($invoice);

        // ---------------------------------------------------------------------
        // Expand thin invoice payloads (prod hits Stripe; tests use FakeStripeClient)
        // ---------------------------------------------------------------------
        $maybePi =
            $this->extractId($invoice->payment_intent ?? null)
            ?: $this->extractId(data_get($invoice, 'payment_intent.id'))
            ?: $this->extractId(data_get($invoice, 'charges.data.0.payment_intent'));

        $maybeCharge =
            $this->extractId($invoice->charge ?? null)
            ?: $this->extractId(data_get($invoice, 'charges.data.0.id'))
            ?: $this->extractId(data_get($invoice, 'payment_intent.latest_charge'))
            ?: $this->extractId(data_get($invoice, 'charges.data.0.charge'));

        if (
            ((! $maybePi) || (! $maybeCharge))
            && $invoiceId
            && (bool) config('services.stripe.webhook_api_fallback', true)
        ) {
            $client = $this->stripeClient();

            if ($client) {
                try {
                    $invoice = $client->invoices->retrieve($invoiceId, [
                        'expand' => [
                            'payment_intent',
                            'payment_intent.latest_charge',
                            'charge',
                            'customer',
                            'subscription',
                            'lines.data.price',
                        ],
                    ]);

                    // Refresh after expansion
                    $customerId = $customerId ?: $this->extractId($invoice->customer ?? null);
                    $subscriptionId = $subscriptionId ?: $this->resolveSubscriptionIdFromInvoice($invoice);
                } catch (Throwable $e) {
                    $this->dbg('invoice.paid: invoice retrieve/expand failed; continuing with thin payload', [
                        'invoice_id' => $invoiceId,
                        'error'      => $e->getMessage(),
                        'event_type' => $eventType,
                    ], 'warning');
                }
            }
        }

        $paymentIntentId =
            $this->extractId($invoice->payment_intent ?? null)
            ?: $this->extractId(data_get($invoice, 'payment_intent.id'))
            ?: $this->extractId(data_get($invoice, 'charges.data.0.payment_intent'));

        $chargeId =
            $this->extractId($invoice->charge ?? null)
            ?: $this->extractId(data_get($invoice, 'charges.data.0.id'))
            ?: $this->extractId(data_get($invoice, 'payment_intent.latest_charge'))
            ?: $this->extractId(data_get($invoice, 'charges.data.0.charge'));

        // Optional API fallback for charge id (safe in tests with FakeStripeClient)
        if (
            ! $chargeId
            && $paymentIntentId
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

        // Stripe’s billing_reason is the best hint for initial vs recurring.
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
                $txFallback = Transaction::query()->where('payment_intent_id', $paymentIntentId)->first();
            }

            if (! $txFallback && $chargeId) {
                $txFallback = Transaction::query()->where('charge_id', $chargeId)->first();
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
                'event_type'        => $eventType,
            ]);
            return;
        }

        // Paid-at (Stripe invoices use unix timestamps)
        $paidAtTs = data_get($invoice, 'status_transitions.paid_at') ?: data_get($invoice, 'paid_at');
        $paidAt   = is_numeric($paidAtTs) ? Carbon::createFromTimestamp((int) $paidAtTs) : null;

        // Update pledge softly (swallow errors)
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
            'stage'                  => 'paid',
            'event_type'             => $eventType,
            'writer'                 => 'invoice_paid',
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
                // -----------------------------------------------------------------
                // 1) Resolver first (canonical tx if it already exists)
                // -----------------------------------------------------------------
                $tx = $resolver->resolveForInvoice($pledge, $invoiceId, $paymentIntentId, $billingReason);

                // Safety: don't mutate a different invoice
                if (
                    $tx
                    && $invoiceId
                    && ! empty($tx->stripe_invoice_id)
                    && (string) $tx->stripe_invoice_id !== (string) $invoiceId
                ) {
                    $tx = null;
                }

                // -----------------------------------------------------------------
                // 2) Adopt the donation_widget anchor (best path)
                //    - initial invoice: prefer subscription_initial anchor
                // -----------------------------------------------------------------
                if (! $tx) {
                    $anchorQuery = Transaction::query()
                        ->where('pledge_id', $pledge->id)
                        ->where('attempt_id', $pledge->attempt_id)
                        ->where('status', 'pending')
                        ->where('created_at', '>=', now()->subHours(12))
                        ->lockForUpdate()
                        ->latest('id')
                        ->where(function ($q) use ($paymentIntentId, $chargeId) {
                            // placeholder with nothing yet
                            $q->where(function ($qq) {
                                $qq->whereNull('payment_intent_id')->whereNull('charge_id');
                            });

                            // OR placeholder that already has PI/charge but no invoice yet
                            if ($paymentIntentId) {
                                $q->orWhere(function ($qq) use ($paymentIntentId) {
                                    $qq->where('payment_intent_id', $paymentIntentId)
                                        ->whereNull('stripe_invoice_id');
                                });
                            }

                            if ($chargeId) {
                                $q->orWhere(function ($qq) use ($chargeId) {
                                    $qq->where('charge_id', $chargeId)
                                        ->whereNull('stripe_invoice_id');
                                });
                            }
                        });

                    if ($billingReason === 'subscription_create') {
                        $anchorQuery->where('type', 'subscription_initial');
                    } else {
                        $anchorQuery->whereIn('type', ['subscription_initial', 'subscription_recurring']);
                    }

                    // Prefer widget-origin anchor if present
                    $tx = (clone $anchorQuery)->where('source', 'donation_widget')->first()
                        ?: $anchorQuery->first();
                }

                // Safety again
                if (
                    $tx
                    && $invoiceId
                    && ! empty($tx->stripe_invoice_id)
                    && (string) $tx->stripe_invoice_id !== (string) $invoiceId
                ) {
                    $tx = null;
                }

                // -----------------------------------------------------------------
                // 3) If still no tx, upsert pledge+invoice row (invoice is the key)
                // -----------------------------------------------------------------
                if (! $tx) {
                    if (! $invoiceId) {
                        $this->dbg('invoice.paid: missing invoice id; cannot resolve or create tx', [
                            'pledge_id'         => $pledge->id,
                            'payment_intent_id' => $paymentIntentId,
                            'charge_id'         => $chargeId,
                        ], 'warning');
                        return;
                    }

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

                // -----------------------------------------------------------------
                // 4) Canonicalize + claim identifiers (invoice is the primary key)
                // -----------------------------------------------------------------
                $tx = $invoiceLinker->adoptOwnerIfInvoiceClaimed($tx, (int) $pledge->id, $invoiceId);

                if ($invoiceId) {
                    $tx = $invoiceLinker->claimInvoiceId($tx, (int) $pledge->id, $invoiceId);
                }

                if ($paymentIntentId) {
                    $tx = $invoiceLinker->claimPaymentIntentId($tx, $paymentIntentId);
                }

                if ($chargeId) {
                    $tx = $invoiceLinker->claimChargeId($tx, $chargeId);
                }

                // -----------------------------------------------------------------
                // 5) Fill columns (non-clobber) and finalize paid state
                // -----------------------------------------------------------------
                $tx->attempt_id      = $tx->attempt_id ?: $pledge->attempt_id;
                $tx->subscription_id = $tx->subscription_id ?: $subscriptionId;

                // Persist SetupIntent onto tx from pledge (stable subscription checkout id)
                if (empty($tx->setup_intent_id) && ! empty($pledge->setup_intent_id)) {
                    $tx->setup_intent_id = $pledge->setup_intent_id;
                }

                $tx->customer_id       = $tx->customer_id       ?? $customerId ?? $pledge->stripe_customer_id;
                $tx->payment_method_id = $tx->payment_method_id ?? $paymentMethodId;

                $tx->payer_email = $tx->payer_email ?? ($payerEmail ?: $pledge->donor_email);
                $tx->payer_name  = $tx->payer_name  ?? ($payerName  ?: $pledge->donor_name);
                $tx->receipt_url = $tx->receipt_url ?? $hostedInvoiceUrl;

                $tx->amount_cents = $amountPaid ?? $tx->amount_cents ?? $pledge->amount_cents;
                $tx->currency     = $currency ?? $tx->currency ?? 'usd';

                // invoice events mark paid
                $tx->paid_at = $tx->paid_at ?? ($paidAt ?: now());
                $tx->status  = 'succeeded';
                $tx->source = $tx->source ?: 'stripe_webhook';

                $tx->type = ($billingReason === 'subscription_create')
                    ? 'subscription_initial'
                    : ($tx->type ?: $txType);

                $tx->metadata = $this->mergeMetadata($tx->metadata, $baseMetadata);

                $tx->setStage('paid');

                $tx->save();

                // Keep pledge stage consistent
                $pledge->setStage('active', save: true);
            }, 3);
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
                    $billingReason,
                    $resolver,
                    $invoiceLinker,
                    $baseMetadata,
                    $subscriptionId,
                    $customerId,
                    $paymentMethodId,
                    $payerEmail,
                    $payerName,
                    $txType
                ) {
                    $tx = $resolver->resolveForInvoice($pledge, $invoiceId, $paymentIntentId, $billingReason ?? null);

                    if (
                        $tx
                        && $invoiceId
                        && ! empty($tx->stripe_invoice_id)
                        && (string) $tx->stripe_invoice_id !== (string) $invoiceId
                    ) {
                        $tx = null;
                    }

                    // Adopt anchor during recovery as well
                    if (! $tx) {
                        $anchorQuery = Transaction::query()
                            ->where('pledge_id', $pledge->id)
                            ->where('attempt_id', $pledge->attempt_id)
                            ->where('status', 'pending')
                            ->where('created_at', '>=', now()->subHours(12))
                            ->lockForUpdate()
                            ->latest('id')
                            ->where(function ($q) use ($paymentIntentId, $chargeId) {
                                $q->where(function ($qq) {
                                    $qq->whereNull('payment_intent_id')->whereNull('charge_id');
                                });

                                if ($paymentIntentId) {
                                    $q->orWhere(function ($qq) use ($paymentIntentId) {
                                        $qq->where('payment_intent_id', $paymentIntentId)
                                            ->whereNull('stripe_invoice_id');
                                    });
                                }

                                if ($chargeId) {
                                    $q->orWhere(function ($qq) use ($chargeId) {
                                        $qq->where('charge_id', $chargeId)
                                            ->whereNull('stripe_invoice_id');
                                    });
                                }
                            });

                        if (($billingReason ?? null) === 'subscription_create') {
                            $anchorQuery->where('type', 'subscription_initial');
                        } else {
                            $anchorQuery->whereIn('type', ['subscription_initial', 'subscription_recurring']);
                        }

                        $tx = (clone $anchorQuery)->where('source', 'donation_widget')->first()
                            ?: $anchorQuery->first();
                    }

                    if (! $tx) {
                        return;
                    }

                    $tx = $invoiceLinker->adoptOwnerIfInvoiceClaimed($tx, (int) $pledge->id, $invoiceId);

                    if ($invoiceId) {
                        $tx = $invoiceLinker->claimInvoiceId($tx, (int) $pledge->id, $invoiceId);
                    }

                    if ($paymentIntentId) {
                        $tx = $invoiceLinker->claimPaymentIntentId($tx, $paymentIntentId);
                    }

                    if ($chargeId) {
                        $tx = $invoiceLinker->claimChargeId($tx, $chargeId);
                    }

                    if (empty($tx->setup_intent_id) && ! empty($pledge->setup_intent_id)) {
                        $tx->setup_intent_id = $pledge->setup_intent_id;
                    }

                    $tx->attempt_id      = $tx->attempt_id ?: $pledge->attempt_id;
                    $tx->subscription_id = $tx->subscription_id ?: $subscriptionId;

                    $tx->customer_id       = $tx->customer_id       ?? $customerId ?? $pledge->stripe_customer_id;
                    $tx->payment_method_id = $tx->payment_method_id ?? $paymentMethodId;

                    $tx->payer_email = $tx->payer_email ?? ($payerEmail ?: $pledge->donor_email);
                    $tx->payer_name  = $tx->payer_name  ?? ($payerName  ?: $pledge->donor_name);
                    $tx->receipt_url = $tx->receipt_url ?? $hostedInvoiceUrl;

                    $tx->amount_cents = $amountPaid ?? $tx->amount_cents ?? $pledge->amount_cents;
                    $tx->currency     = $currency ?? $tx->currency ?? 'usd';

                    $tx->paid_at = $tx->paid_at ?? ($paidAt ?: now());
                    $tx->status  = 'succeeded';
                    $tx->source = $tx->source ?: 'stripe_webhook';

                    $tx->type = (($billingReason ?? null) === 'subscription_create')
                        ? 'subscription_initial'
                        : ($tx->type ?: $txType);

                    $tx->metadata = $this->mergeMetadata($tx->metadata, $baseMetadata);

                    $tx->setStage('paid');

                    $tx->save();

                    $pledge->setStage('active', save: true);
                }, 3);
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
        $chargeId   = $this->extractId($charge->id ?? null);
        $piId       = $this->extractId($charge->payment_intent ?? null);
        $customerId = $this->extractId($charge->customer ?? null);

        $invoiceId = $this->extractId($charge->invoice ?? null)
            ?: $this->extractId(data_get($charge, 'invoice.id'));

        if (! $piId && ! $chargeId) {
            $this->dbg('charge.succeeded missing identifiers, ignoring', [
                'charge_id' => $chargeId,
                'pi'        => $piId,
            ], 'info');
            return;
        }

        // Find an existing tx to enrich (NEVER create)
        $tx = null;

        if ($piId) {
            $tx = Transaction::query()->where('payment_intent_id', $piId)->first();
        }

        if (! $tx && $chargeId) {
            $tx = Transaction::query()->where('charge_id', $chargeId)->first();
        }

        // Optional but helpful: if invoice handler already set stripe_invoice_id, find by invoice too
        if (! $tx && $invoiceId) {
            $tx = Transaction::query()->where('stripe_invoice_id', $invoiceId)->first();
        }

        if (! $tx) {
            $this->dbg('charge.succeeded: no existing transaction to update; ignoring to prevent duplicates', [
                'charge_id'         => $chargeId,
                'payment_intent_id' => $piId,
                'invoice_id'        => $invoiceId,
                'customer_id'       => $customerId,
                'description'       => $charge->description ?? null,
            ], 'warning');
            return;
        }

        /** @var \App\Support\Stripe\TransactionInvoiceLinker $linker */
        $linker = app(\App\Support\Stripe\TransactionInvoiceLinker::class);

        // Claim identifiers safely (unique-safe)
        if ($piId) {
            $tx = $linker->claimPaymentIntentId($tx, $piId);
        }
        if ($chargeId) {
            $tx = $linker->claimChargeId($tx, $chargeId);
        }

        // Enrich fields, non-stomp
        $tx->customer_id       = $tx->customer_id       ?? $customerId;
        $tx->payment_method_id = $tx->payment_method_id ?? $this->extractId($charge->payment_method ?? null);

        if (! $tx->amount_cents && isset($charge->amount)) {
            $tx->amount_cents = (int) $charge->amount;
        }
        if (! $tx->currency && isset($charge->currency)) {
            $tx->currency = (string) $charge->currency;
        }

        $tx->receipt_url = $tx->receipt_url ?? ($charge->receipt_url ?? null);
        $tx->payer_email = $tx->payer_email ?? data_get($charge, 'billing_details.email');
        $tx->payer_name  = $tx->payer_name  ?? data_get($charge, 'billing_details.name');

        // IMPORTANT: charge.* never marks paid. Even for one-time, PI is your writer.
        $tx->metadata = $this->mergeMetadata($tx->metadata, array_filter([
            'event'      => 'charge.succeeded',
            'invoice_id' => $invoiceId,
        ]));

        $tx->metadata = $this->mergeMetadata($tx->metadata, $this->cardMetaFromChargeObject($charge));

        $tx->save();

        $this->dbg('charge.succeeded: enriched existing tx (no paid writes)', $this->txSnap($tx), 'info');
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

<?php

namespace App\Support\Stripe;

use App\Models\Pledge;
use App\Models\Transaction;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TransactionResolver
{
    /**
     * Resolve the *canonical* transaction to update for an invoice-related event.
     *
     * SUBSCRIPTION RULE:
     *   If invoiceId exists, invoice ownership must win over payment_intent_id.
     *
     * Priority (invoice flows):
     *  1) (pledge_id, stripe_invoice_id) owner
     *  2) pledge placeholder (same pledge + attempt, invoice NULL) adopt anchor (out-of-order safe)
     *  3) payment_intent_id match - fallback only
     *  4) attempt_id fallback last resort
     *
     * Notes:
     * - This function NEVER creates.
     * - Uses lockForUpdate, so it must run inside a transaction (we ensure that).
     */
    public function resolveForInvoice(
        Pledge $pledge,
        ?string $invoiceId,
        ?string $paymentIntentId,
        ?string $billingReason = null, // e.g. "subscription_create"
    ): ?Transaction {
        $fn = function () use ($pledge, $invoiceId, $paymentIntentId, $billingReason) {

            // -----------------------------------------------------------------
            // 1) Invoice wins (canonical) when available.
            // -----------------------------------------------------------------
            if ($invoiceId) {
                $byInvoice = Transaction::query()
                    ->where('pledge_id', $pledge->id)
                    ->where('stripe_invoice_id', $invoiceId)
                    ->lockForUpdate()
                    ->first();

                if ($byInvoice) {
                    return $byInvoice;
                }
            }

            // -----------------------------------------------------------------
            // 2) Adopt placeholder anchor for this pledge attempt.
            //    Prefer the widget-created subscription_initial on subscription_create.
            // -----------------------------------------------------------------
            $attemptId = $pledge->attempt_id;

            if ($attemptId) {
                $anchorBase = Transaction::query()
                    ->where('pledge_id', $pledge->id)
                    ->where('attempt_id', $attemptId)
                    ->whereNull('stripe_invoice_id')
                    ->whereIn('status', ['pending']) // adjust if you have more in-progress statuses
                    ->lockForUpdate()
                    ->orderByDesc('id');

                // Prefer a true placeholder (no PI yet) because it's the cleanest adoption.
                $anchorBase->where(function ($q) {
                    $q->whereNull('payment_intent_id')
                      ->orWhere('payment_intent_id', '');
                });

                // When it's the initial invoice, strongly prefer subscription_initial.
                if ($billingReason === 'subscription_create') {
                    $anchorPreferred = (clone $anchorBase)
                        ->where('type', 'subscription_initial')
                        ->where('source', 'donation_widget')
                        ->first();

                    if ($anchorPreferred) {
                        return $anchorPreferred;
                    }

                    $anchorFallback = (clone $anchorBase)
                        ->where('type', 'subscription_initial')
                        ->first();

                    if ($anchorFallback) {
                        return $anchorFallback;
                    }

                    // If we didn't find a strict initial anchor, fall through to more general anchors below.
                }

                // General (recurring or unknown): prefer widget source if available.
                $anchorPreferred = (clone $anchorBase)
                    ->where('source', 'donation_widget')
                    ->first();

                if ($anchorPreferred) {
                    return $anchorPreferred;
                }

                $anchorFallback = (clone $anchorBase)->first();
                if ($anchorFallback) {
                    return $anchorFallback;
                }
            }

            // -----------------------------------------------------------------
            // 3) PaymentIntent fallback match (ONLY after invoice + placeholder).
            // -----------------------------------------------------------------
            if ($paymentIntentId) {
                $byPi = Transaction::query()
                    ->where('payment_intent_id', $paymentIntentId)
                    ->lockForUpdate()
                    ->first();

                if ($byPi) {
                    // Safety: if this tx is already tied to a different invoice, don't return it.
                    if ($invoiceId && ! empty($byPi->stripe_invoice_id) && (string) $byPi->stripe_invoice_id !== (string) $invoiceId) {
                        return null;
                    }

                    // Safety: if it belongs to a different pledge, don't return it.
                    if (! empty($byPi->pledge_id) && (int) $byPi->pledge_id !== (int) $pledge->id) {
                        return null;
                    }

                    return $byPi;
                }
            }

            // -----------------------------------------------------------------
            // 4) Last resort: attempt_id row (can be noisy, so keep it last).
            // -----------------------------------------------------------------
            if ($attemptId) {
                return Transaction::query()
                    ->where('attempt_id', $attemptId)
                    ->lockForUpdate()
                    ->orderByDesc('id')
                    ->first();
            }

            return null;
        };

        // Avoid nested transactions — if we’re already inside one, just run.
        if (DB::transactionLevel() > 0) {
            return $fn();
        }

        return DB::transaction($fn, 3);
    }

    /**
     * Resolve or create a transaction for the invoice, handling duplicates gracefully.
     *
     * IMPORTANT:
     * - Create only when we truly found nothing sensible to update.
     * - For invoice flows, invoice must remain the canonical identity.
     */
    public function resolveOrCreateForInvoice(
        Pledge $pledge,
        ?string $invoiceId,
        ?string $paymentIntentId,
        array $updates = [],
        string $defaultType = 'subscription_recurring',
        ?string $billingReason = null,
    ): Transaction {
        $fn = function () use ($pledge, $invoiceId, $paymentIntentId, $updates, $defaultType, $billingReason) {

            $tx = $this->resolveForInvoice($pledge, $invoiceId, $paymentIntentId, $billingReason);

            if (! $tx) {
                if (! $invoiceId) {
                    // Without an invoice id, we should not create in "invoice world".
                    // Caller can decide what to do, but failing fast is better than duplicates.
                    throw new \RuntimeException('resolveOrCreateForInvoice called without invoiceId; refusing to create.');
                }

                try {
                    $tx = Transaction::create(array_merge([
                        'user_id'           => $updates['user_id'] ?? $pledge->user_id,
                        'pledge_id'         => $pledge->id,
                        'attempt_id'        => $pledge->attempt_id,
                        'subscription_id'   => $updates['subscription_id'] ?? $pledge->stripe_subscription_id,
                        'stripe_invoice_id' => $invoiceId,
                        // NOTE: do NOT force PI here unless you are 100% sure it's correct; allow linker to claim later.
                        'payment_intent_id' => $updates['payment_intent_id'] ?? null,
                        'type'              => $defaultType,
                        'status'            => 'pending',
                        'amount_cents'      => $updates['amount_cents'] ?? $pledge->amount_cents ?? 0,
                        'currency'          => $updates['currency'] ?? $pledge->currency ?? 'usd',
                        'source'            => $updates['source'] ?? 'stripe_webhook',
                    ], $updates));
                } catch (UniqueConstraintViolationException | QueryException $e) {
                    Log::warning('Transaction create failed (likely duplicate) → adopting existing', [
                        'pledge_id'   => $pledge->id,
                        'invoice_id'  => $invoiceId,
                        'pi_id'       => $paymentIntentId,
                        'error'       => $e->getMessage(),
                    ]);

                    // Fetch the canonical owner deterministically: invoice first.
                    $tx = Transaction::query()
                        ->where('pledge_id', $pledge->id)
                        ->where('stripe_invoice_id', $invoiceId)
                        ->lockForUpdate()
                        ->first();

                    // Fallback to PI only if invoice lookup failed.
                    if (! $tx && $paymentIntentId) {
                        $tx = Transaction::query()
                            ->where('payment_intent_id', $paymentIntentId)
                            ->lockForUpdate()
                            ->first();
                    }

                    if (! $tx) {
                        throw $e;
                    }
                }
            }

            // Non-destructive updates (“no-op if already set”)
            foreach ($updates as $key => $value) {
                if ($value === null) {
                    continue;
                }
                if (! isset($tx->$key) || $tx->$key === null || $tx->$key === '') {
                    $tx->$key = $value;
                }
            }

            // Invoice must be present if provided (non-stomp)
            if ($invoiceId && empty($tx->stripe_invoice_id)) {
                $tx->stripe_invoice_id = $invoiceId;
            }

            // PI is still non-stomp: only set if empty
            if ($paymentIntentId && empty($tx->payment_intent_id)) {
                $tx->payment_intent_id = $paymentIntentId;
            }

            $tx->save();

            return $tx;
        };

        if (DB::transactionLevel() > 0) {
            return $fn();
        }

        return DB::transaction($fn, 3);
    }
}

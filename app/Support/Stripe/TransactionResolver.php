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
     * Priority:
     *  1) payment_intent_id (globally unique)
     *  2) (pledge_id, stripe_invoice_id) owner
     *  3) pledge placeholder (same pledge, stripe_invoice_id IS NULL) — this is what fixes out-of-order delivery
     *  4) attempt_id fallback (last resort)
     */
    public function resolveForInvoice(Pledge $pledge, ?string $invoiceId, ?string $paymentIntentId): ?Transaction
    {
        $fn = function () use ($pledge, $invoiceId, $paymentIntentId) {
            // 1) PI wins if present.
            if ($paymentIntentId) {
                $byPi = Transaction::query()
                    ->where('payment_intent_id', $paymentIntentId)
                    ->lockForUpdate()
                    ->first();

                if ($byPi) {
                    return $byPi;
                }
            }

            // 2) Canonical invoice row for this pledge.
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

            // 3) Placeholder for this pledge: no invoice yet.
            // Prefer a true placeholder (no PI either), but allow matching PI if present.
            $placeholder = Transaction::query()
                ->where('pledge_id', $pledge->id)
                ->whereNull('stripe_invoice_id')
                ->when($paymentIntentId, function ($q) use ($paymentIntentId) {
                    $q->where(function ($qq) use ($paymentIntentId) {
                        $qq->whereNull('payment_intent_id')
                           ->orWhere('payment_intent_id', $paymentIntentId);
                    });
                }, function ($q) {
                    $q->whereNull('payment_intent_id');
                })
                ->whereIn('status', ['pending']) // adjust if you have other “in-progress” statuses
                ->lockForUpdate()
                ->orderByDesc('id')
                ->first();

            if ($placeholder) {
                return $placeholder;
            }

            // 4) Last resort: current attempt row.
            if ($pledge->attempt_id) {
                return Transaction::query()
                    ->where('attempt_id', $pledge->attempt_id)
                    ->lockForUpdate()
                    ->orderByDesc('id')
                    ->first();
            }

            return null;
        };

        // Avoid nested transactions — if we’re already inside one, just run with the current locks.
        if (DB::transactionLevel() > 0) {
            return $fn();
        }

        return DB::transaction($fn, 3);
    }

    /**
     * Resolve or create a transaction for the invoice, handling duplicates gracefully.
     *
     * IMPORTANT:
     * - Do NOT call resolveForInvoice() inside another transaction that itself starts a transaction.
     * - Create only when we truly found nothing sensible to update.
     */
    public function resolveOrCreateForInvoice(
        Pledge $pledge,
        ?string $invoiceId,
        ?string $paymentIntentId,
        array $updates = [],
        string $defaultType = 'subscription_recurring'
    ): Transaction {
        $fn = function () use ($pledge, $invoiceId, $paymentIntentId, $updates, $defaultType) {
            $tx = $this->resolveForInvoice($pledge, $invoiceId, $paymentIntentId);

            if (! $tx) {
                try {
                    $tx = Transaction::create(array_merge([
                        'user_id'           => $updates['user_id'] ?? $pledge->user_id,
                        'pledge_id'         => $pledge->id,
                        'attempt_id'        => $pledge->attempt_id,
                        'subscription_id'   => $updates['subscription_id'] ?? $pledge->stripe_subscription_id,
                        'stripe_invoice_id' => $invoiceId,
                        'payment_intent_id' => $paymentIntentId,
                        'type'              => $defaultType,
                        'status'            => 'pending',
                        'amount_cents'      => $updates['amount_cents'] ?? $pledge->amount_cents ?? 0,
                        'currency'          => $updates['currency'] ?? $pledge->currency ?? 'usd',
                    ], $updates));
                } catch (UniqueConstraintViolationException | QueryException $e) {
                    Log::warning('Transaction create failed (likely duplicate) → adopting existing', [
                        'pledge_id'   => $pledge->id,
                        'invoice_id'  => $invoiceId,
                        'pi_id'       => $paymentIntentId,
                        'error'       => $e->getMessage(),
                    ]);

                    // Fetch the *real* owner deterministically.
                    $tx = Transaction::query()
                        ->when($paymentIntentId, fn ($q) => $q->orWhere('payment_intent_id', $paymentIntentId))
                        ->when($invoiceId, fn ($q) => $q->orWhere(function ($qq) use ($pledge, $invoiceId) {
                            $qq->where('pledge_id', $pledge->id)->where('stripe_invoice_id', $invoiceId);
                        }))
                        ->lockForUpdate()
                        ->orderByDesc('id')
                        ->firstOrFail();
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

            // If caller passed invoiceId/paymentIntentId, do not overwrite non-null values.
            if ($invoiceId && empty($tx->stripe_invoice_id)) {
                $tx->stripe_invoice_id = $invoiceId;
            }
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

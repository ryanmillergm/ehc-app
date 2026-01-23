<?php

namespace App\Support\Stripe;

use App\Models\Pledge;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;

class TransactionResolver
{
    /**
     * Resolve the *canonical* transaction to update for an invoice-related event.
     *
     * Rules (in priority order):
     *  1) If a transaction already owns the PaymentIntent, use it (PI is globally unique).
     *  2) If a transaction already owns (pledge_id, stripe_invoice_id), use it.
     *  3) If there is an "attempt" transaction for this pledge, use the most recent one.
     *
     * Returns null if nothing sensible exists yet (caller may create).
     */
    public function resolveForInvoice(Pledge $pledge, ?string $invoiceId, ?string $paymentIntentId): ?Transaction
    {
        return DB::transaction(function () use ($pledge, $invoiceId, $paymentIntentId) {
            // 1) PI wins if present (and safe).
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

            // 3) Fall back to the current attempt (most recent), if any.
            if ($pledge->attempt_id) {
                return Transaction::query()
                    ->where('attempt_id', $pledge->attempt_id)
                    ->lockForUpdate()
                    ->orderByDesc('id')
                    ->first();
            }

            return null;
        }, 3);
    }
}

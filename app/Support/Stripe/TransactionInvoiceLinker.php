<?php

namespace App\Support\Stripe;

use App\Models\Transaction;
use Illuminate\Support\Facades\DB;

class TransactionInvoiceLinker
{
    /**
     * Run $fn inside a transaction if we aren't already in one.
     * Needed because lockForUpdate requires an active transaction.
     */
    protected function inTransaction(callable $fn): mixed
    {
        if (DB::transactionLevel() > 0) {
            return $fn();
        }

        return DB::transaction(fn () => $fn(), 3);
    }

    /**
     * Ensure exactly one Transaction owns (pledge_id, stripe_invoice_id).
     * If another tx already owns it, return that one (the owner).
     */
    public function adoptOwnerIfInvoiceClaimed(Transaction $tx, int $pledgeId, ?string $invoiceId): Transaction
    {
        if (! $invoiceId) {
            return $tx;
        }

        return $this->inTransaction(function () use ($tx, $pledgeId, $invoiceId) {
            $owner = Transaction::query()
                ->where('pledge_id', $pledgeId)
                ->where('stripe_invoice_id', $invoiceId)
                ->lockForUpdate()
                ->first();

            if (! $owner || (int) $owner->id === (int) $tx->id) {
                return $tx;
            }

            return $owner;
        });
    }

    /**
     * Unique-safe claim for payment_intent_id (pi_...).
     * No-op if already set.
     */
    public function claimPaymentIntentId(Transaction $tx, ?string $paymentIntentId): Transaction
    {
        if (! $paymentIntentId) {
            return $tx;
        }

        // B) no-op if already set (don’t stomp, don’t lock)
        if (! empty($tx->payment_intent_id)) {
            return $tx;
        }

        return $this->inTransaction(function () use ($tx, $paymentIntentId) {
            $owner = Transaction::query()
                ->where('payment_intent_id', $paymentIntentId)
                ->lockForUpdate()
                ->first();

            if (! $owner || (int) $owner->id === (int) $tx->id) {
                $tx->payment_intent_id = $paymentIntentId;
            }

            return $tx;
        });
    }

    /**
     * Unique-safe claim for charge_id (ch_...).
     * No-op if already set.
     */
    public function claimChargeId(Transaction $tx, ?string $chargeId): Transaction
    {
        if (! $chargeId) {
            return $tx;
        }

        // B) no-op if already set
        if (! empty($tx->charge_id)) {
            return $tx;
        }

        return $this->inTransaction(function () use ($tx, $chargeId) {
            $owner = Transaction::query()
                ->where('charge_id', $chargeId)
                ->lockForUpdate()
                ->first();

            if (! $owner || (int) $owner->id === (int) $tx->id) {
                $tx->charge_id = $chargeId;
            }

            return $tx;
        });
    }

    public function claimInvoiceId(Transaction $tx, int $pledgeId, ?string $invoiceId): Transaction
    {
        if (! $invoiceId) {
            return $tx;
        }

        // If another tx already owns this invoice for the pledge, return it and DO NOT mutate $tx.
        $owner = Transaction::query()
            ->where('pledge_id', $pledgeId)
            ->where('stripe_invoice_id', $invoiceId)
            ->lockForUpdate()
            ->first();

        if ($owner && $owner->id !== $tx->id) {
            return $owner;
        }

        // Otherwise, safe to claim on this row if empty.
        if (empty($tx->stripe_invoice_id)) {
            $tx->stripe_invoice_id = $invoiceId;
            $tx->save();
        }

        return $tx;
    }
}

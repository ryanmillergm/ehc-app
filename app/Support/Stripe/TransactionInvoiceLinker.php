<?php

namespace App\Support\Stripe;

use App\Models\Transaction;
use Illuminate\Support\Facades\DB;

class TransactionInvoiceLinker
{
    /**
     * Ensure exactly one Transaction owns (pledge_id, stripe_invoice_id).
     * If another tx already owns it, return that one (the owner).
     */
    public function adoptOwnerIfInvoiceClaimed(Transaction $tx, int $pledgeId, ?string $invoiceId): Transaction
    {
        if (! $invoiceId) {
            return $tx;
        }

        return DB::transaction(function () use ($tx, $pledgeId, $invoiceId) {
            $owner = Transaction::query()
                ->where('pledge_id', $pledgeId)
                ->where('stripe_invoice_id', $invoiceId)
                ->lockForUpdate()
                ->first();

            if (! $owner || (int) $owner->id === (int) $tx->id) {
                return $tx;
            }

            // Someone else already claimed this invoice for this pledge.
            // Use the owner; do NOT try to assign invoice_id to $tx.
            return $owner;
        }, 3);
    }
}

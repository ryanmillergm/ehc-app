<?php

namespace Tests\Unit\Support\Stripe;

use App\Models\Pledge;
use App\Models\Transaction;
use App\Support\Stripe\TransactionInvoiceLinker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TransactionInvoiceLinkerTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_adopts_existing_owner_transaction_for_same_pledge_and_invoice(): void
    {
        $pledge = Pledge::factory()->create();

        $owner = Transaction::factory()->create([
            'pledge_id'        => $pledge->id,
            'stripe_invoice_id'=> 'in_123',
            'status'           => 'succeeded',
        ]);

        $pending = Transaction::factory()->create([
            'pledge_id'         => $pledge->id,
            'stripe_invoice_id' => null,
            'status'            => 'pending',
        ]);

        $linker = app(TransactionInvoiceLinker::class);

        $adopted = $linker->adoptOwnerIfInvoiceClaimed($pending, $pledge->id, 'in_123');

        $this->assertSame($owner->id, $adopted->id);

        // Ensure pending tx still doesn't claim the invoice
        $pending->refresh();
        $this->assertNull($pending->stripe_invoice_id);

        // Owner remains the owner
        $owner->refresh();
        $this->assertSame('in_123', $owner->stripe_invoice_id);
    }
}

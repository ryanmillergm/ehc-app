<?php

namespace Tests\Feature;

use App\Http\Controllers\StripeWebhookController;
use App\Models\Refund;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StripeWebhookRefundsTest extends TestCase
{
    use RefreshDatabase;

    public function test_charge_refunded_marks_transaction_refunded_and_upserts_refund_rows_idempotently(): void
    {
        $tx = Transaction::factory()->create([
            'charge_id'    => 'ch_123',
            'amount_cents' => 2000,
            'currency'     => 'usd',
            'status'       => 'succeeded',
        ]);

        $event = (object) [
            'type' => 'charge.refunded',
            'data' => (object) [
                'object' => (object) [
                    'id'       => 'ch_123',
                    'refunds'  => (object) [
                        'data' => [
                            (object) [
                                'id'       => 're_123',
                                'amount'   => 2000,
                                'currency' => 'usd',
                                'status'   => 'succeeded',
                                'reason'   => null,
                                'metadata' => (object) [],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        (new StripeWebhookController())->handleEvent($event);

        $tx->refresh();
        $this->assertSame('refunded', $tx->status);

        $this->assertDatabaseHas('refunds', [
            'transaction_id'   => $tx->id,
            'stripe_refund_id' => 're_123',
            'charge_id'        => 'ch_123',
            'amount_cents'     => 2000,
            'currency'         => 'usd',
            'status'           => 'succeeded',
        ]);

        // idempotency: running it again should not duplicate
        (new StripeWebhookController())->handleEvent($event);

        $this->assertSame(1, Refund::where('stripe_refund_id', 're_123')->count());
    }
}

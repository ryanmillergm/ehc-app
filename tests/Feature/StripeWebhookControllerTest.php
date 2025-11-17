<?php

namespace Tests\Feature;

use App\Http\Controllers\StripeWebhookController;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StripeWebhookControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_payment_intent_succeeded_updates_transaction(): void
    {
        $tx = Transaction::factory()->create([
            'payment_intent_id' => 'pi_123',
            'status'            => 'pending',
        ]);

        $event = (object) [
            'type' => 'payment_intent.succeeded',
            'data' => (object) [
                'object' => (object) [
                    'id'      => 'pi_123',
                    'charges' => (object) [
                        'data' => [
                            (object) [
                                'id'          => 'ch_123',
                                'receipt_url' => 'https://example.test/receipt',
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $controller = new StripeWebhookController();
        $controller->handleEvent($event);

        $this->assertDatabaseHas('transactions', [
            'id'         => $tx->id,
            'status'     => 'succeeded',
            'charge_id'  => 'ch_123',
        ]);
    }
}

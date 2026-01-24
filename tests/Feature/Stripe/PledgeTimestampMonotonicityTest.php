<?php

namespace Tests\Feature\Stripe;

use App\Http\Controllers\StripeWebhookController;
use App\Models\Pledge;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
use Tests\TestCase;

class PledgeTimestampMonotonicityTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function invoice_payment_paid_does_not_move_last_pledge_at_backwards(): void
    {
        // Given a pledge that already has a newer last_pledge_at
        $existing = Carbon::parse('2026-01-23 10:00:00');

        $pledge = Pledge::factory()->create([
            'status' => 'active',
            'latest_invoice_id' => 'in_123',
            'last_pledge_at' => $existing,
        ]);

        // And an out-of-order invoice_payment.paid webhook with an older paid_at
        $olderPaidAt = Carbon::parse('2026-01-22 09:00:00')->timestamp;

        $inpay = (object) [
            'id' => 'inpay_123',
            'invoice' => 'in_123',
            'status_transitions' => (object) ['paid_at' => $olderPaidAt],
            'payment' => (object) [
                // Can be null; handler should still work
                'payment_intent' => null,
            ],
        ];

        // When we handle the webhook
        $controller = app(StripeWebhookController::class);

        $method = new ReflectionMethod($controller, 'handleInvoicePaymentPaid');
        $method->setAccessible(true);
        $method->invoke($controller, $inpay);

        // Then last_pledge_at should not move backwards
        $pledge->refresh();

        $this->assertTrue(
            $pledge->last_pledge_at->equalTo($existing),
            'Expected last_pledge_at to remain at the newer timestamp.'
        );
    }
}

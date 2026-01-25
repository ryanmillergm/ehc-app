<?php

namespace Tests\Feature\Stripe;

use App\Http\Controllers\StripeWebhookController;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class StripeWebhookIdempotentTransactionUpdateTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function charge_succeeded_is_idempotent_and_enriches_existing_transaction_without_duplicates(): void
    {
        $tx = Transaction::factory()->create([
            'payment_intent_id' => 'pi_test_123',
            'charge_id'         => null,
            'status'            => 'pending',
            'source'            => 'donation_widget',
            'amount_cents'      => 2500,
            'currency'          => 'usd',
            'payer_email'       => null,
            'payer_name'        => null,
            'receipt_url'       => null,
            'paid_at'           => null,
            'metadata'          => [
                'donation' => 'widget',
            ],
        ]);

        $charge = (object) [
            'id'             => 'ch_test_123',
            'payment_intent' => 'pi_test_123',
            'amount'         => 2500,
            'currency'       => 'usd',
            'receipt_url'    => 'https://example.test/receipt/ch_test_123',
            'billing_details' => (object) [
                'name'  => 'Test Donor',
                'email' => 'donor@example.test',
            ],
            'payment_method_details' => (object) [
                'card' => (object) [
                    'brand'     => 'visa',
                    'last4'     => '4242',
                    'exp_month' => 12,
                    'exp_year'  => 2030,
                ],
            ],
        ];

        $controller = new class extends StripeWebhookController {
            public function callHandleChargeSucceeded(object $charge): void
            {
                $this->handleChargeSucceeded($charge);
            }
        };

        // Run twice to simulate duplicate webhook deliveries
        $controller->callHandleChargeSucceeded($charge);
        $controller->callHandleChargeSucceeded($charge);

        // Still exactly one transaction row
        $this->assertSame(1, Transaction::query()->count());

        $tx->refresh();

        // It should be the same transaction, now enriched
        $this->assertSame('pi_test_123', $tx->payment_intent_id);
        $this->assertSame('ch_test_123', $tx->charge_id);
        $this->assertSame('https://example.test/receipt/ch_test_123', $tx->receipt_url);

        // charge.succeeded is enrichment — not authoritative finalization
        $this->assertSame('pending', $tx->status);
        $this->assertNull($tx->paid_at);

        // Preserve original "source" (donation_widget) unless your app explicitly wants to overwrite it
        $this->assertSame('donation_widget', $tx->source);

        // Payer details enriched if present
        $this->assertSame('donor@example.test', $tx->payer_email);
        $this->assertSame('Test Donor', $tx->payer_name);

        // Card metadata merged in
        $this->assertSame('visa', $tx->metadata['card_brand'] ?? null);
        $this->assertSame('4242', $tx->metadata['card_last4'] ?? null);
        $this->assertSame(12, $tx->metadata['card_exp_month'] ?? null);
        $this->assertSame(2030, $tx->metadata['card_exp_year'] ?? null);

        // Existing metadata preserved (non-stomp)
        $this->assertSame('widget', $tx->metadata['donation'] ?? null);
    }
}

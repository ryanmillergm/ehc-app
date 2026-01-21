<?php

namespace Tests\Concerns;

use App\Models\Pledge;
use App\Models\Transaction;
use Carbon\Carbon;
use PHPUnit\Framework\Assert;

trait AssertsStripeWebhookState
{
    protected function assertPledgeActiveAndUpdated(Pledge $pledge, array $expected = []): void
    {
        $pledge->refresh();

        foreach ($expected as $key => $value) {
            $this->assertEquals(
                $value,
                data_get($pledge, $key),
                "Pledge field [{$key}] did not match expected value."
            );
        }
    }

    /**
     * Normalize metadata into an array.
     */
    protected function meta($metadata): array
    {
        if (is_array($metadata)) {
            return $metadata;
        }

        if (is_string($metadata) && $metadata !== '') {
            $decoded = json_decode($metadata, true);
            return is_array($decoded) ? $decoded : [];
        }

        if (is_object($metadata)) {
            return (array) $metadata;
        }

        return [];
    }

    /**
     * Assert "no surprises" completeness for a subscription transaction.
     */
    protected function assertSubscriptionTxComplete(Transaction $tx, array $expects = []): void
    {
        Assert::assertSame('succeeded', $tx->status, 'Subscription tx must end succeeded.');
        Assert::assertNotNull($tx->paid_at, 'Subscription tx must have paid_at.');

        // Must have canonical fields filled (unless a test intentionally allows null)
        Assert::assertNotEmpty($tx->pledge_id, 'Subscription tx must have pledge_id.');
        Assert::assertNotEmpty($tx->subscription_id, 'Subscription tx must have subscription_id.');
        Assert::assertNotEmpty($tx->customer_id, 'Subscription tx must have customer_id.');
        Assert::assertNotEmpty($tx->receipt_url, 'Subscription tx must have receipt_url.');

        if (!empty($expects['payment_method_id'] ?? null)) {
            Assert::assertSame($expects['payment_method_id'], $tx->payment_method_id);
        } else {
            Assert::assertNotEmpty($tx->payment_method_id, 'Subscription tx must have payment_method_id.');
        }

        if (!empty($expects['charge_id'] ?? null)) {
            Assert::assertSame($expects['charge_id'], $tx->charge_id);
        }

        if (!empty($expects['customer_id'] ?? null)) {
            Assert::assertSame($expects['customer_id'], $tx->customer_id);
        }

        if (!empty($expects['subscription_id'] ?? null)) {
            Assert::assertSame($expects['subscription_id'], $tx->subscription_id);
        }

        // type should be subscription_* not one_time
        Assert::assertContains(
            $tx->type,
            ['subscription_initial', 'subscription_recurring'],
            'Subscription tx type must be subscription_initial or subscription_recurring.'
        );
    }

    /**
     * Assert pledge was activated and key Stripe fields synced.
     */
    protected function assertPledgeActiveAndSynced(Pledge $pledge, array $expects = []): void
    {
        $pledge->refresh();

        Assert::assertSame('active', $pledge->status, 'Pledge must be active after invoice.paid.');

        if (isset($expects['latest_invoice_id'])) {
            Assert::assertSame($expects['latest_invoice_id'], $pledge->latest_invoice_id);
        } else {
            Assert::assertNotEmpty($pledge->latest_invoice_id, 'Pledge must have latest_invoice_id.');
        }

        if (isset($expects['latest_payment_intent_id'])) {
            Assert::assertSame($expects['latest_payment_intent_id'], $pledge->latest_payment_intent_id);
        } else {
            Assert::assertNotEmpty($pledge->latest_payment_intent_id, 'Pledge must have latest_payment_intent_id.');
        }

        Assert::assertNotNull($pledge->current_period_start, 'Pledge must have current_period_start.');
        Assert::assertNotNull($pledge->current_period_end, 'Pledge must have current_period_end.');
        Assert::assertNotNull($pledge->last_pledge_at, 'Pledge must have last_pledge_at.');
        Assert::assertNotNull($pledge->next_pledge_at, 'Pledge must have next_pledge_at.');

        // sanity: start/end should be Carbon instances (cast) or parseable
        Assert::assertTrue(
            $pledge->current_period_start instanceof Carbon,
            'current_period_start should be a Carbon instance.'
        );
        Assert::assertTrue(
            $pledge->current_period_end instanceof Carbon,
            'current_period_end should be a Carbon instance.'
        );
    }

    protected function assertTxUniquenessByPi(string $pi): void
    {
        $this->assertSame(1, \App\Models\Transaction::where('payment_intent_id', $pi)->count());
    }

    protected function assertTxUniquenessByInvoice(string $invoiceId): void
    {
        $this->assertSame(1, \App\Models\Transaction::where('stripe_invoice_id', $invoiceId)->count());
    }

    protected function mockStripeForComplete(array $overrides = [])
    {
        $stripe = $this->mock(\App\Services\StripeService::class);

        $stripe->shouldReceive('retrievePaymentIntent')
            ->andReturn($overrides['paymentIntent'] ?? (object) [
                'id' => 'pi_123',
                'status' => 'succeeded',
                'latest_charge' => 'ch_123',
            ]);

        $stripe->shouldReceive('finalizeTransactionFromPaymentIntent')
            ->andReturnUsing(fn ($tx, $pi) => $tx);

        return $stripe;
    }
}

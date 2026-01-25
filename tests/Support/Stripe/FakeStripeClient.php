<?php

declare(strict_types=1);

namespace Tests\Support\Stripe;

use Stripe\StripeClient;

/**
 * Minimal fake StripeClient for webhook tests.
 *
 * Supports:
 *  - $client->invoices->retrieve($id, [...])
 *  - $client->paymentIntents->retrieve($id, [...])
 *  - $client->charges->retrieve($id, [...])
 *
 * No network. Returns canned objects you provide.
 *
 * NOTE: We intentionally do NOT rely on Stripe's internal service classes.
 * We just provide properties with a compatible `retrieve()` signature.
 */
class FakeStripeClient extends StripeClient
{
    public FakeStripeInvoicesService $invoices;
    public FakeStripePaymentIntentsService $paymentIntents;
    public FakeStripeChargesService $charges;

    /**
     * @param array{
     *   invoices?: array<string, object>,
     *   payment_intents?: array<string, object>,
     *   charges?: array<string, object>,
     * } $store
     */
    public function __construct(array $store = [])
    {
        // StripeClient wants an API key, but we never use it.
        parent::__construct('sk_test_fake');

        $this->invoices = new FakeStripeInvoicesService($store['invoices'] ?? []);
        $this->paymentIntents = new FakeStripePaymentIntentsService($store['payment_intents'] ?? []);
        $this->charges = new FakeStripeChargesService($store['charges'] ?? []);
    }
}

final class FakeStripeInvoicesService
{
    /** @var array<string, object> */
    private array $invoices;

    /** @param array<string, object> $invoices */
    public function __construct(array $invoices)
    {
        $this->invoices = $invoices;
    }

    /** @param array<string, mixed> $opts */
    public function retrieve(string $id, array $opts = []): object
    {
        if (! array_key_exists($id, $this->invoices)) {
            throw new \RuntimeException("FakeStripeClient: invoice not found [{$id}]");
        }

        return $this->invoices[$id];
    }
}

final class FakeStripePaymentIntentsService
{
    /** @var array<string, object> */
    private array $pis;

    /** @param array<string, object> $pis */
    public function __construct(array $pis)
    {
        $this->pis = $pis;
    }

    /** @param array<string, mixed> $opts */
    public function retrieve(string $id, array $opts = []): object
    {
        if (! array_key_exists($id, $this->pis)) {
            throw new \RuntimeException("FakeStripeClient: payment_intent not found [{$id}]");
        }

        return $this->pis[$id];
    }
}

final class FakeStripeChargesService
{
    /** @var array<string, object> */
    private array $charges;

    /** @param array<string, object> $charges */
    public function __construct(array $charges)
    {
        $this->charges = $charges;
    }

    /** @param array<string, mixed> $opts */
    public function retrieve(string $id, array $opts = []): object
    {
        if (! array_key_exists($id, $this->charges)) {
            throw new \RuntimeException("FakeStripeClient: charge not found [{$id}]");
        }

        return $this->charges[$id];
    }
}

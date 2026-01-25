<?php

declare(strict_types=1);

namespace Tests\Support\Stripe;

use RuntimeException;
use Stripe\StripeClient;

final class FakeStripeClient extends StripeClient
{
    public object $invoices;
    public object $paymentIntents;
    public object $charges;

    /**
     * @param array{
     *   invoices?: array<string, object>,
     *   payment_intents?: array<string, object>,
     *   charges?: array<string, object>,
     * } $fixtures
     */
    public function __construct(array $fixtures = [])
    {
        // Parent constructor does not “call the network” by itself.
        // It only sets up service access.
        parent::__construct('sk_test_fake_no_network');

        $invoiceMap = $fixtures['invoices'] ?? [];
        $piMap      = $fixtures['payment_intents'] ?? [];
        $chargeMap  = $fixtures['charges'] ?? [];

        $this->invoices = new class($invoiceMap) {
            public function __construct(private array $map) {}

            public function retrieve(string $id, array $opts = []): object
            {
                if (! array_key_exists($id, $this->map)) {
                    throw new RuntimeException("FakeStripeClient: missing invoice fixture for {$id}");
                }
                return $this->map[$id];
            }
        };

        $this->paymentIntents = new class($piMap) {
            public function __construct(private array $map) {}

            public function retrieve(string $id, array $opts = []): object
            {
                if (! array_key_exists($id, $this->map)) {
                    throw new RuntimeException("FakeStripeClient: missing payment_intent fixture for {$id}");
                }
                return $this->map[$id];
            }
        };

        $this->charges = new class($chargeMap) {
            public function __construct(private array $map) {}

            public function retrieve(string $id, array $opts = []): object
            {
                if (! array_key_exists($id, $this->map)) {
                    throw new RuntimeException("FakeStripeClient: missing charge fixture for {$id}");
                }
                return $this->map[$id];
            }
        };
    }
}

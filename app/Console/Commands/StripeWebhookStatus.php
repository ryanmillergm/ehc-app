<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class StripeWebhookStatus extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'stripe:webhook-status';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Shows webhook secret status + last webhook hit + best-effort detection of stripe listen';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $secretSet = (bool) config('services.stripe.webhook_secret');
        $lastHit   = Cache::get('stripe:last_webhook_hit_at');

        $this->info('Stripe Webhook Status');
        $this->line('--------------------');
        $this->line('STRIPE_WEBHOOK_SECRET set: ' . ($secretSet ? 'YES' : 'NO'));
        $this->line('Last webhook hit at: ' . ($lastHit ?: 'Never'));

        // Best-effort process check (dev convenience, not gospel)
        $running = $this->isStripeListenRunning();
        $this->line('stripe listen running: ' . ($running ? 'PROBABLY YES' : 'PROBABLY NO'));

        if (! $secretSet) {
            $this->warn('Set STRIPE_WEBHOOK_SECRET (from stripe listen output) or webhook signature verification will fail.');
        }

        if (! $lastHit) {
            $this->warn('No webhook hits recorded yet. If you expect them, check stripe listen + route + logs.');
        }

        return self::SUCCESS;
    }

    protected function isStripeListenRunning(): bool
    {
        // Windows
        if (str_starts_with(strtoupper(PHP_OS_FAMILY), 'WINDOWS')) {
            $out = @shell_exec('tasklist /FI "IMAGENAME eq stripe.exe"');
            return is_string($out) && str_contains($out, 'stripe.exe');
        }

        // macOS/Linux
        $out = @shell_exec('pgrep -f "stripe listen"');
        return is_string($out) && trim($out) !== '';
    }
}

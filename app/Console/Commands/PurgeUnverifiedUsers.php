<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class PurgeUnverifiedUsers extends Command
{
    protected $signature = 'users:purge-unverified {--days=3 : Purge users unverified for this many days}';
    protected $description = 'Soft-delete users who never verified their email after a grace period';

    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));
        $cutoff = now()->subDays($days);

        $query = User::query()
            ->whereNull('email_verified_at')
            ->whereNull('deleted_at')
            ->where('created_at', '<', $cutoff);

        $count = (clone $query)->count();

        if ($count === 0) {
            $this->info('No unverified users to purge.');
            return self::SUCCESS;
        }

        $query->delete();

        $this->info("Purged {$count} unverified user(s) older than {$days} day(s).");
        return self::SUCCESS;
    }
}

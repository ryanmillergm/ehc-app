<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Models\Pledge;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function __invoke()
    {
        $user = Auth::user();

        // Base query for this user's successful, paid transactions
        $baseQuery = Transaction::query()
            ->where('user_id', $user->id)
            ->where('status', 'succeeded')
            ->whereNotNull('paid_at');
            // ->whereIn('type', ['one_time', 'subscription_recurring', 'subscription_initial'])
            // uncomment / adjust if you want to restrict which types count as “giving”

        // LIFETIME total (in dollars)
        $lifetimeTotalCents = (clone $baseQuery)->sum('amount_cents');
        $lifetimeTotal = $lifetimeTotalCents / 100;

        // YEAR-TO-DATE total (current calendar year, in dollars)
        $year = now()->year;
        $yearToDateTotalCents = (clone $baseQuery)
            ->whereYear('paid_at', $year)
            ->sum('amount_cents');
        $yearToDateTotal = $yearToDateTotalCents / 100;

        // ACTIVE RECURRING COUNT
        // Assuming Pledges table tracks recurring subscriptions
        $activeRecurringCount = Pledge::query()
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->count();

        // RECENT GIFTS (latest 5 successful transactions)
        $recentGifts = (clone $baseQuery)
            ->orderByDesc('paid_at')
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();

        return view('dashboard', [
            'lifetimeTotal'        => $lifetimeTotal,
            'yearToDateTotal'      => $yearToDateTotal,
            'activeRecurringCount' => $activeRecurringCount,
            'recentGifts'          => $recentGifts,
        ]);
    }
}

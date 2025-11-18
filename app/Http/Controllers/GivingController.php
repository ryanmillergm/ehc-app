<?php

namespace App\Http\Controllers;

use App\Models\Pledge;
use App\Models\Transaction;
use App\Services\StripeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GivingController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        $transactions = Transaction::query()
            ->where('user_id', $user->id)
            ->whereNotNull('paid_at')
            ->latest('paid_at')
            ->paginate(10, ['*'], 'transactions_page');

        $pledges = Pledge::query()
            ->where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->get();

        return view('giving.index', compact('transactions', 'pledges'));
    }

    /**
     * Cancel a subscription at period end (for the current user only).
     */
    public function cancelSubscription(
        Request $request,
        Pledge $pledge,
        StripeService $stripe
    ): RedirectResponse {
        $this->assertUserOwnsPledge($request, $pledge);

        $stripe->cancelSubscriptionAtPeriodEnd($pledge);

        return back()->with('status', 'Your subscription will be cancelled at the end of the current period.');
    }

    /**
     * Update the monthly amount on a subscription.
     */
    public function updateSubscriptionAmount(
        Request $request,
        Pledge $pledge,
        StripeService $stripe
    ): RedirectResponse {
        $this->assertUserOwnsPledge($request, $pledge);

        $data = $request->validate([
            'amount_dollars' => ['required', 'numeric', 'min:1'],
        ]);

        $newAmountCents = (int) round($data['amount_dollars'] * 100);

        $stripe->updateSubscriptionAmount($pledge, $newAmountCents);

        return back()->with('status', 'Your monthly amount has been updated.');
    }

    protected function assertUserOwnsPledge(Request $request, Pledge $pledge): void
    {
        $user = $request->user();

        abort_unless($user && $pledge->user_id === $user->id, 403);
    }
}

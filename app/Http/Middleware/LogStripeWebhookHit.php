<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class LogStripeWebhookHit
{
    public function handle(Request $request, Closure $next)
    {
        // Only run for the Stripe webhook endpoint.
        // This protects you even if the middleware gets accidentally applied globally.
        if (! $request->is('stripe/webhook')) {
            return $next($request);
        }

        $now = now()->toDateTimeString();

        Log::alert(str_repeat('=', 80));
        Log::alert("🔥🔥🔥 STRIPE WEBHOOK HIT at {$now} 🔥🔥🔥");
        Log::alert("Path: {$request->path()}  IP: {$request->ip()}");
        Log::alert(str_repeat('=', 80));

        Cache::put('stripe:last_webhook_hit_at', $now, now()->addDays(7));

        return $next($request);
    }
}

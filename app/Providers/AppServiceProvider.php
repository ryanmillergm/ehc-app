<?php

namespace App\Providers;

use RuntimeException;
use Filament\Actions\CreateAction;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Gate;
use Stripe\StripeClient;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(StripeClient::class, function () {
            $secret = config('services.stripe.secret');

            throw_if(
                empty($secret),
                RuntimeException::class,
                'Stripe secret is missing. Set STRIPE_SECRET in .env and map it in config/services.php.'
            );

            return new StripeClient($secret);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Implicitly grant "Super Admin" role all permissions
        // This works in the app by using gate-related functions like auth()->user->can() and @can()
        Gate::before(function ($user, $ability) {
            return $user->hasRole('Super Admin') ? true : null;
        });

        // Set default action for filament when creating a relational model from a resource. Remove this to have the default modal popup.
        CreateAction::configureUsing( function ($action) {
            return $action->slideOver();
        });
    }
}

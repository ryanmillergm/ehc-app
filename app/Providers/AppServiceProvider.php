<?php

namespace App\Providers;

use Filament\Tables\Actions\CreateAction;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
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

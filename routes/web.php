<?php

use App\Livewire\Home;
use App\Livewire\Pages\ShowPage;
use App\Livewire\Pages\IndexPage;
use App\Http\Middleware\Localization;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\LanguageSwitch;
use App\Http\Controllers\PageController;
use App\Http\Controllers\TeamController;
use App\Http\Controllers\GivingController;
use App\Http\Controllers\AddressController;
use App\Http\Controllers\Admin\EmailAssetController;
use App\Http\Controllers\ChildrenController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LanguagesController;
use App\Http\Controllers\StripeWebhookController;
use App\Http\Controllers\PageTranslationController;
use App\Http\Controllers\Donations\DonationsController;
use App\Http\Controllers\EmailPreferencesController;
use App\Http\Middleware\LogStripeWebhookHit;
use App\Http\Controllers\EmailUnsubscribeController;

Route::get('lang/{lang}', LanguageSwitch::class)->name('lang');

Route::get('/', Home::class)->name('home');

Route::view('/emails/subscribe', 'emails.subscribe')
    ->name('emails.subscribe');

Route::get('/unsubscribe/{token}', EmailUnsubscribeController::class)
    ->name('emails.unsubscribe');

Route::get('/email-preferences/{token}', EmailPreferencesController::class)
    ->name('emails.preferences');

Route::get('/dev/stripe/webhook-status', function () {
    abort_unless(app()->environment('local'), 404);

    return view('dev.stripe-webhook-status', [
        'webhookSecretSet' => (bool) config('services.stripe.webhook_secret'),
        'lastHitAt'        => Cache::get('stripe:last_webhook_hit_at'),
        'appUrl'           => config('app.url'),
    ]);
})->name('dev.stripe.webhook-status');

/*
|--------------------------------------------------------------------------
| Public donation flow (no auth required)
|--------------------------------------------------------------------------
*/

Route::get('/give', [DonationsController::class, 'show'])
    ->name('donations.show');

Route::prefix('donations')->name('donations.')->group(function () {
    Route::post('start', [DonationsController::class, 'start'])
        ->name('start');

    Route::post('complete', [DonationsController::class, 'complete'])
        ->name('complete');

    // Stripe redirect always comes here
    Route::get('return', [DonationsController::class, 'stripeReturn'])->name('return');

    Route::get('thank-you', [DonationsController::class, 'thankYou'])
        ->name('thankyou');

    Route::get('thank-you-subscription', [DonationsController::class, 'thankYouSubscription'])
        ->name('thankyou-subscription');
});

/*
|--------------------------------------------------------------------------
| Stripe webhook (Stripe → your app)
|--------------------------------------------------------------------------
| Protected by Stripe's signing secret, not by auth.
*/
Route::post('/stripe/webhook', StripeWebhookController::class)
    ->name('stripe.webhook')
    ->middleware(LogStripeWebhookHit::class)
    ->withoutMiddleware([
        Localization::class,
    ]);

/*
|--------------------------------------------------------------------------
| Authenticated area (Jetstream dashboard, My Giving, etc.)
|--------------------------------------------------------------------------
*/

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    // Addresses
    Route::get('/addresses', [AddressController::class, 'index'])->name('addresses.index');
    Route::post('/addresses', [AddressController::class, 'store'])->name('addresses.store');
    Route::get('/addresses/{address}/edit', [AddressController::class, 'edit'])->name('addresses.edit');
    Route::put('/addresses/{address}', [AddressController::class, 'update'])->name('addresses.update');
    Route::delete('/addresses/{address}', [AddressController::class, 'destroy'])->name('addresses.destroy');
    Route::post('/addresses/{address}/primary', [AddressController::class, 'makePrimary'])->name('addresses.make-primary');

    Route::prefix('admin')->group(function () {
        Route::get('/email-assets', [EmailAssetController::class, 'index'])->name('email-assets.index');
        Route::post('/email-assets', [EmailAssetController::class, 'store'])->name('email-assets.store');
    });

    /*
    |--------------------------------------------------------------------------
    | My Giving (user-facing giving dashboard)
    |--------------------------------------------------------------------------
    */
    Route::prefix('giving')->name('giving.')->group(function () {
        Route::get('/', [GivingController::class, 'index'])->name('index');

        // Manage subscriptions
        Route::post('/subscriptions/{pledge}/cancel', [GivingController::class, 'cancelSubscription'])
            ->name('subscriptions.cancel');

        Route::post('/subscriptions/{pledge}/amount', [GivingController::class, 'updateSubscriptionAmount'])
            ->name('subscriptions.amount');

        Route::post('/subscriptions/{pledge}/resume', [GivingController::class, 'resumeSubscription'])
            ->name('subscriptions.resume');
    });

    // Children
    Route::resource('children', ChildrenController::class);

    // Languages
    Route::resource('languages', LanguagesController::class);

    // Teams
    Route::resource('teams', TeamController::class);

    // Pages + translations
    Route::resource('pages', PageController::class)->only(['store']);

    Route::prefix('pages/{page}')->group(function () {
        Route::resource('translations', PageTranslationController::class)->only(['store']);
    });
});

/*
|--------------------------------------------------------------------------
| Public pages (Livewire)
|--------------------------------------------------------------------------
*/
Route::get('/pages/{slug}', ShowPage::class);
Route::get('/pages', IndexPage::class);

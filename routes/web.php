<?php

use App\Http\Controllers\ChildrenController;
use App\Http\Controllers\Donations\DonationsController;
use App\Http\Controllers\LanguagesController;
use App\Http\Controllers\LanguageSwitch;
use App\Http\Controllers\PageController;
use App\Http\Controllers\PageTranslationController;
use App\Http\Controllers\StripeWebhookController;
use App\Http\Controllers\TeamController;
use App\Http\Middleware\Localization;
use App\Livewire\Pages\IndexPage;
use App\Livewire\Pages\ShowPage;
use Illuminate\Support\Facades\Route;

Route::get('lang/{lang}', LanguageSwitch::class)->name('lang');

Route::get('/', function () {
    return view('welcome');
})->name('welcome');

Route::get('/give', [DonationsController::class, 'show'])
    ->name('donations.show');

Route::prefix('donations')->name('donations.')->group(function () {
    Route::post('start', [DonationsController::class, 'start'])
        ->name('start');

    Route::post('complete', [DonationsController::class, 'complete'])
        ->name('complete');

    Route::get('thank-you/{transaction}', [DonationsController::class, 'thankYou'])
        ->name('thankyou');

    Route::get('thank-you-subscription/{pledge}', [DonationsController::class, 'thankYouSubscription'])
        ->name('thankyou-subscription');
});


// Webhook endpoint (Stripe dashboard will call this)
Route::post('/stripe/webhook', StripeWebhookController::class)
    ->name('stripe.webhook');

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
    ])->group(function () {
        Route::get('/dashboard', function () {
            return view('dashboard');
        })->name('dashboard');

        Route::resource('children', ChildrenController::class);
        Route::resource('languages', LanguagesController::class);
        Route::resource('teams', TeamController::class);
        Route::resource('pages', PageController::class)->only([
            'store'
        ]);

        Route::prefix('pages/{page}')->group(function () {
            Route::resource('translations', PageTranslationController::class)->only([
                'store'
            ]);
        });
});

Route::get('/pages/{slug}', ShowPage::class);
Route::get('/pages/', IndexPage::class);

<?php

use App\Http\Controllers\TeamController;
use App\Http\Controllers\ChildrenController;
use App\Http\Controllers\LanguagesController;
use App\Http\Controllers\LanguageSwitch;
use App\Http\Controllers\PageController;
use App\Http\Controllers\PageTranslationController;
use App\Http\Middleware\Localization;
use App\Livewire\Pages\IndexPage;
use App\Livewire\Pages\ShowPage;
use Illuminate\Support\Facades\Route;

Route::get('lang/{lang}', LanguageSwitch::class)->name('lang');

Route::get('/', function () {
    return view('welcome');
})->name('welcome');

Route::get('/pages/', IndexPage::class);
Route::get('/pages/{slug}', ShowPage::class);

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
        Route::resource('pages', PageController::class);

        Route::prefix('pages/{page}')->group(function () {
            Route::resource('translations', PageTranslationController::class);
        });

});

<?php

use App\Http\Controllers\TeamController;
use App\Http\Controllers\ChildrenController;
use App\Http\Controllers\LanguagesController;
use App\Http\Controllers\LanguageSwitch;
use App\Http\Middleware\Localization;
use Illuminate\Support\Facades\Route;

Route::get('lang/{lang}', LanguageSwitch::class)->name('lang');

Route::get('/', function () {
    return view('welcome');
})->name('welcome');


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

});

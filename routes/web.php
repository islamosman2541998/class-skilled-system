<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\LookupController;
use App\Http\Controllers\NumberingController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\UserController;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\IdleTimeout;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:5,1')->name('login.store');
});

Route::middleware(['auth', EnsureUserIsActive::class, IdleTimeout::class])->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

    Route::get('/', HomeController::class)->name('home');
    Route::post('/locale/{locale}', LocaleController::class)->whereIn('locale', ['ar', 'en'])->name('locale.switch');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profile/password', [ProfileController::class, 'password'])->name('profile.password');

    Route::resource('users', UserController::class)->except(['show', 'destroy']);
    Route::patch('/users/{user}/toggle-active', [UserController::class, 'toggleActive'])->name('users.toggle-active');
    Route::put('/users/{user}/reset-password', [UserController::class, 'resetPassword'])->name('users.reset-password');

    Route::resource('roles', RoleController::class)->except(['show', 'destroy']);

    Route::prefix('settings')->group(function () {
        Route::get('/lists/{type}', [LookupController::class, 'index'])->name('lookups.index');
        Route::post('/lists/{type}', [LookupController::class, 'store'])->name('lookups.store');
        Route::post('/lists/{type}/reorder', [LookupController::class, 'reorder'])->name('lookups.reorder');
        Route::put('/lists/{type}/{lookup}', [LookupController::class, 'update'])->name('lookups.update');

        Route::get('/numbering', [NumberingController::class, 'edit'])->name('settings.numbering');
        Route::put('/numbering', [NumberingController::class, 'update'])->name('settings.numbering.update');

        Route::get('/', [SettingsController::class, 'index'])->name('settings.index');
        Route::get('/{group}', [SettingsController::class, 'edit'])->whereIn('group', array_keys(SettingsController::GROUPS))->name('settings.edit');
        Route::put('/{group}', [SettingsController::class, 'update'])->whereIn('group', array_keys(SettingsController::GROUPS))->name('settings.update');
    });
});

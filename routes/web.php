<?php

use App\Http\Controllers\ActivityController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DesignController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\IntegrationController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\LookupController;
use App\Http\Controllers\MediaController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\NotificationSettingsController;
use App\Http\Controllers\NumberingController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PublicDocumentController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\StageTemplateController;
use App\Http\Controllers\TemplateSettingsController;
use App\Http\Controllers\TranslationController;
use App\Http\Controllers\UploadController;
use App\Http\Controllers\UserController;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\IdleTimeout;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:5,1')->name('login.store');
});

// Documents opened by clients from a signed, expiring link (spec §5.10).
Route::get('/d/q/{quotation}', [PublicDocumentController::class, 'quotation'])->middleware(['signed', 'throttle:30,1'])->name('public.quotation');
Route::get('/d/i/{invoice}', [PublicDocumentController::class, 'invoice'])->middleware(['signed', 'throttle:30,1'])->name('public.invoice');
Route::get('/d/r/{receipt}', [PublicDocumentController::class, 'receipt'])->middleware(['signed', 'throttle:30,1'])->name('public.receipt');
Route::get('/d/c/{claim}', [PublicDocumentController::class, 'claim'])->middleware(['signed', 'throttle:30,1'])->name('public.claim');

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

    Route::post('/users/{user}/attachments', [UserController::class, 'attach'])->name('users.attachments.store');

    Route::post('/uploads', [UploadController::class, 'store'])->name('uploads.store');
    Route::match(['HEAD', 'GET'], '/uploads/{id}', [UploadController::class, 'head'])->whereUuid('id')->name('uploads.head');
    Route::patch('/uploads/{id}', [UploadController::class, 'patch'])->whereUuid('id')->name('uploads.patch');
    Route::delete('/uploads/{id}', [UploadController::class, 'destroy'])->whereUuid('id')->name('uploads.destroy');
    Route::post('/uploads/{id}/poster', [UploadController::class, 'poster'])->whereUuid('id')->name('uploads.poster');

    Route::get('/media/{media}', [MediaController::class, 'show'])->name('media.show');
    Route::get('/media/{media}/thumb', [MediaController::class, 'thumb'])->name('media.thumb');
    Route::patch('/media/{media}', [MediaController::class, 'update'])->name('media.update');
    Route::delete('/media/{media}', [MediaController::class, 'destroy'])->name('media.destroy');

    Route::resource('roles', RoleController::class)->except(['show', 'destroy']);

    Route::get('/activity', ActivityController::class)->name('activity.index');
    require __DIR__.'/sales.php';
    require __DIR__.'/projects.php';
    require __DIR__.'/finance.php';
    require __DIR__.'/site.php';

    Route::get('/design', [DesignController::class, 'index'])->name('design.index');
    Route::get('/design/record', [DesignController::class, 'record'])->name('design.record');
    Route::get('/search', SearchController::class)->middleware('throttle:60,1')->name('search');

    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('/notifications/latest', [NotificationController::class, 'latest'])->name('notifications.latest');
    Route::post('/notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
    Route::post('/notifications/{id}/read', [NotificationController::class, 'read'])->whereUuid('id')->name('notifications.read');

    Route::prefix('settings')->group(function () {
        Route::get('/lists/{type}', [LookupController::class, 'index'])->name('lookups.index');
        Route::post('/lists/{type}', [LookupController::class, 'store'])->name('lookups.store');
        Route::post('/lists/{type}/reorder', [LookupController::class, 'reorder'])->name('lookups.reorder');
        Route::put('/lists/{type}/{lookup}', [LookupController::class, 'update'])->name('lookups.update');

        Route::get('/notifications', [NotificationSettingsController::class, 'edit'])->name('settings.notifications');
        Route::put('/notifications', [NotificationSettingsController::class, 'update'])->name('settings.notifications.update');
        Route::get('/translations', [TranslationController::class, 'index'])->name('translations.index');
        Route::put('/translations', [TranslationController::class, 'update'])->name('translations.update');
        Route::delete('/translations', [TranslationController::class, 'reset'])->name('translations.reset');
        Route::get('/integration', [IntegrationController::class, 'edit'])->name('settings.integration');
        Route::put('/integration', [IntegrationController::class, 'update'])->name('settings.integration.update');
        Route::post('/integration/key', [IntegrationController::class, 'generateKey'])->name('settings.integration.key');
        Route::get('/stage-templates', [StageTemplateController::class, 'edit'])->name('settings.stage-templates');
        Route::put('/stage-templates', [StageTemplateController::class, 'update'])->name('settings.stage-templates.update');
        Route::get('/templates', [TemplateSettingsController::class, 'edit'])->name('settings.templates');
        Route::put('/templates', [TemplateSettingsController::class, 'update'])->name('settings.templates.update');
        Route::get('/numbering', [NumberingController::class, 'edit'])->name('settings.numbering');
        Route::put('/numbering', [NumberingController::class, 'update'])->name('settings.numbering.update');

        Route::get('/', [SettingsController::class, 'index'])->name('settings.index');
        Route::get('/{group}', [SettingsController::class, 'edit'])->whereIn('group', array_keys(SettingsController::GROUPS))->name('settings.edit');
        Route::put('/{group}', [SettingsController::class, 'update'])->whereIn('group', array_keys(SettingsController::GROUPS))->name('settings.update');
    });
});

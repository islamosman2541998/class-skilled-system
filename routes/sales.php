<?php

use App\Http\Controllers\ClientContactController;
use App\Http\Controllers\ClientController;
use Illuminate\Support\Facades\Route;

/*
| Stage 1 — sales. Loaded from routes/web.php inside the authenticated group.
*/

Route::get('/clients/duplicates', [ClientController::class, 'duplicates'])->name('clients.duplicates');
Route::post('/clients/quick', [ClientController::class, 'quickStore'])->name('clients.quick-store');
Route::resource('clients', ClientController::class)->except(['destroy']);
Route::post('/clients/{client}/attachments', [ClientController::class, 'attach'])->name('clients.attachments.store');
Route::post('/clients/{client}/contacts', [ClientContactController::class, 'store'])->name('clients.contacts.store');
Route::put('/clients/{client}/contacts/{contact}', [ClientContactController::class, 'update'])->name('clients.contacts.update');
Route::delete('/clients/{client}/contacts/{contact}', [ClientContactController::class, 'destroy'])->name('clients.contacts.destroy');

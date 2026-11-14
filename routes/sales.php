<?php

use App\Http\Controllers\ClientContactController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\LeadActionController;
use App\Http\Controllers\LeadController;
use Illuminate\Support\Facades\Route;

/*
| Stage 1 — sales. Loaded from routes/web.php inside the authenticated group.
*/

Route::get('/clients/duplicates', [ClientController::class, 'duplicates'])->name('clients.duplicates');
Route::get('/clients/options', [ClientController::class, 'options'])->name('clients.options');
Route::post('/clients/quick', [ClientController::class, 'quickStore'])->name('clients.quick-store');
Route::resource('clients', ClientController::class)->except(['destroy']);
Route::post('/clients/{client}/attachments', [ClientController::class, 'attach'])->name('clients.attachments.store');
Route::post('/clients/{client}/contacts', [ClientContactController::class, 'store'])->name('clients.contacts.store');
Route::put('/clients/{client}/contacts/{contact}', [ClientContactController::class, 'update'])->name('clients.contacts.update');
Route::delete('/clients/{client}/contacts/{contact}', [ClientContactController::class, 'destroy'])->name('clients.contacts.destroy');

Route::resource('leads', LeadController::class)->except(['destroy']);
Route::post('/leads/{lead}/attachments', [LeadController::class, 'attach'])->name('leads.attachments.store');
Route::post('/leads/{lead}/follow-ups', [LeadActionController::class, 'followUp'])->name('leads.follow-ups.store');
Route::patch('/leads/{lead}/assign', [LeadActionController::class, 'assign'])->name('leads.assign');
Route::patch('/leads/{lead}/schedule', [LeadActionController::class, 'schedule'])->name('leads.schedule');
Route::patch('/leads/{lead}/close', [LeadActionController::class, 'close'])->name('leads.close');
Route::patch('/leads/{lead}/reopen', [LeadActionController::class, 'reopen'])->name('leads.reopen');

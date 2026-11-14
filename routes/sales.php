<?php

use App\Http\Controllers\ClientContactController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\LeadActionController;
use App\Http\Controllers\LeadController;
use App\Http\Controllers\MyVisitsController;
use App\Http\Controllers\PriceItemController;
use App\Http\Controllers\QuotationController;
use App\Http\Controllers\SiteVisitController;
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

Route::get('/my/visits', MyVisitsController::class)->name('my-visits');
Route::resource('site-visits', SiteVisitController::class)->except(['destroy']);
Route::get('/site-visits/{site_visit}/result', [SiteVisitController::class, 'result'])->name('site-visits.result');
Route::put('/site-visits/{site_visit}/result', [SiteVisitController::class, 'storeResult'])->name('site-visits.result.store');
Route::post('/site-visits/{site_visit}/attachments', [SiteVisitController::class, 'attach'])->name('site-visits.attachments.store');

Route::get('/price-items', [PriceItemController::class, 'index'])->name('price-items.index');
Route::post('/price-items', [PriceItemController::class, 'store'])->name('price-items.store');
Route::get('/price-items/search', [PriceItemController::class, 'search'])->name('price-items.search');
Route::get('/price-items/import-template', [PriceItemController::class, 'template'])->name('price-items.import-template');
Route::post('/price-items/import', [PriceItemController::class, 'import'])->name('price-items.import');
Route::put('/price-items/{price_item}', [PriceItemController::class, 'update'])->name('price-items.update');

Route::resource('quotations', QuotationController::class)->except(['destroy']);
Route::post('/quotations/{quotation}/attachments', [QuotationController::class, 'attach'])->name('quotations.attachments.store');

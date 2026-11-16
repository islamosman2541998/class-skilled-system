<?php

use App\Http\Controllers\PurchaseController;
use App\Http\Controllers\SupplierController;
use Illuminate\Support\Facades\Route;

/*
| Stage 5 (resources): suppliers, purchases, subcontracts, payment vouchers, labour and equipment.
| Included from routes/web.php inside the authenticated group.
*/

Route::resource('suppliers', SupplierController::class)->except(['destroy']);
Route::post('/suppliers/{supplier}/attachments', [SupplierController::class, 'attach'])->name('suppliers.attachments.store');

Route::resource('purchases', PurchaseController::class);
Route::post('/purchases/{purchase}/attachments', [PurchaseController::class, 'attach'])->name('purchases.attachments.store');

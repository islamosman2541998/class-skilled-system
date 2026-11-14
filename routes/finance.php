<?php

use App\Http\Controllers\InvoiceController;
use Illuminate\Support\Facades\Route;

/*
| Stage 3 — finance. Loaded from routes/web.php inside the authenticated group.
*/

Route::resource('invoices', InvoiceController::class);
Route::patch('/invoices/{invoice}/issue', [InvoiceController::class, 'issue'])->name('invoices.issue');
Route::patch('/invoices/{invoice}/cancel', [InvoiceController::class, 'cancel'])->name('invoices.cancel');

<?php

use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\InvoicePdfController;
use App\Http\Controllers\ReceiptController;
use App\Http\Controllers\ReceiptPdfController;
use Illuminate\Support\Facades\Route;

/*
| Stage 3 — finance. Loaded from routes/web.php inside the authenticated group.
*/

Route::resource('invoices', InvoiceController::class);
Route::patch('/invoices/{invoice}/issue', [InvoiceController::class, 'issue'])->name('invoices.issue');
Route::patch('/invoices/{invoice}/cancel', [InvoiceController::class, 'cancel'])->name('invoices.cancel');
Route::get('/invoices/{invoice}/pdf', [InvoicePdfController::class, 'show'])->name('invoices.pdf');
Route::post('/invoices/{invoice}/share', [InvoicePdfController::class, 'share'])->name('invoices.share');
Route::post('/projects/{project}/installments/{installment}/invoice', [InvoicePdfController::class, 'fromInstallment'])->name('projects.installments.invoice');

Route::resource('receipts', ReceiptController::class)->except(['destroy']);
Route::put('/receipts/{receipt}/allocations', [ReceiptController::class, 'allocate'])->name('receipts.allocations.update');
Route::patch('/receipts/{receipt}/cancel', [ReceiptController::class, 'cancel'])->name('receipts.cancel');
Route::post('/receipts/{receipt}/attachments', [ReceiptController::class, 'attach'])->name('receipts.attachments.store');
Route::get('/receipts/{receipt}/pdf', [ReceiptPdfController::class, 'show'])->name('receipts.pdf');
Route::post('/receipts/{receipt}/share', [ReceiptPdfController::class, 'share'])->name('receipts.share');

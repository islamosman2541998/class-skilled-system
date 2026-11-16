<?php

use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\PaymentVoucherController;
use App\Http\Controllers\PurchaseController;
use App\Http\Controllers\SubcontractController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\SupplierStatementController;
use App\Http\Controllers\WorkerController;
use Illuminate\Support\Facades\Route;

/*
| Stage 5 (resources): suppliers, purchases, subcontracts, payment vouchers, labour and equipment.
| Included from routes/web.php inside the authenticated group.
*/

Route::resource('suppliers', SupplierController::class)->except(['destroy']);
Route::post('/suppliers/{supplier}/attachments', [SupplierController::class, 'attach'])->name('suppliers.attachments.store');
Route::get('/suppliers/{supplier}/statement/pdf', [SupplierStatementController::class, 'pdf'])->name('suppliers.statement.pdf');

Route::resource('purchases', PurchaseController::class);
Route::post('/purchases/{purchase}/attachments', [PurchaseController::class, 'attach'])->name('purchases.attachments.store');

Route::resource('subcontracts', SubcontractController::class)->except(['destroy']);
Route::post('/subcontracts/{subcontract}/payments/{payment}/bill', [SubcontractController::class, 'bill'])->name('subcontracts.bill');

Route::resource('payment-vouchers', PaymentVoucherController::class)->except(['destroy']);
Route::put('/payment-vouchers/{payment_voucher}/allocations', [PaymentVoucherController::class, 'allocate'])->name('payment-vouchers.allocations.update');
Route::patch('/payment-vouchers/{payment_voucher}/cancel', [PaymentVoucherController::class, 'cancel'])->name('payment-vouchers.cancel');
Route::post('/payment-vouchers/{payment_voucher}/attachments', [PaymentVoucherController::class, 'attach'])->name('payment-vouchers.attachments.store');

Route::resource('workers', WorkerController::class)->except(['show', 'destroy']);
Route::get('/attendance', [AttendanceController::class, 'index'])->name('attendance.index');
Route::get('/attendance/sheet', [AttendanceController::class, 'sheet'])->name('attendance.sheet');
Route::post('/attendance', [AttendanceController::class, 'store'])->name('attendance.store');

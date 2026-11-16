<?php

use App\Http\Controllers\ClaimController;
use App\Http\Controllers\ClaimPdfController;
use App\Http\Controllers\DailyReportController;
use App\Http\Controllers\ProjectItemController;
use App\Http\Controllers\RetentionInvoiceController;
use Illuminate\Support\Facades\Route;

/*
| Stage 4 (site): daily reports and progress claims. Loaded from routes/web.php inside the
| authenticated group.
*/

Route::resource('daily-reports', DailyReportController::class)->except(['destroy']);
Route::post('/daily-reports/{daily_report}/attachments', [DailyReportController::class, 'attach'])->name('daily-reports.attachments.store');

Route::put('/projects/{project}/claim-setup', [ProjectItemController::class, 'setup'])->name('projects.claim-setup.update');
Route::post('/projects/{project}/items', [ProjectItemController::class, 'store'])->name('projects.items.store');
Route::put('/projects/{project}/items/{item}', [ProjectItemController::class, 'update'])->name('projects.items.update');
Route::delete('/projects/{project}/items/{item}', [ProjectItemController::class, 'destroy'])->name('projects.items.destroy');

Route::get('/claims', [ClaimController::class, 'index'])->name('claims.index');
Route::post('/projects/{project}/claims', [ClaimController::class, 'store'])->name('claims.store');
Route::get('/claims/{claim}', [ClaimController::class, 'show'])->name('claims.show');
Route::get('/claims/{claim}/edit', [ClaimController::class, 'edit'])->name('claims.edit');
Route::put('/claims/{claim}', [ClaimController::class, 'update'])->name('claims.update');
Route::delete('/claims/{claim}', [ClaimController::class, 'destroy'])->name('claims.destroy');
Route::patch('/claims/{claim}/submit', [ClaimController::class, 'submit'])->name('claims.submit');
Route::patch('/claims/{claim}/return', [ClaimController::class, 'return'])->name('claims.return');
Route::patch('/claims/{claim}/approve', [ClaimController::class, 'approve'])->name('claims.approve');
Route::get('/claims/{claim}/pdf', [ClaimPdfController::class, 'show'])->name('claims.pdf');
Route::post('/claims/{claim}/share', [ClaimPdfController::class, 'share'])->name('claims.share');
Route::post('/projects/{project}/retention-invoice', [RetentionInvoiceController::class, 'store'])->name('projects.retention-invoice');

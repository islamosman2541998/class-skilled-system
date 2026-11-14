<?php

use App\Http\Controllers\ConvertQuotationController;
use App\Http\Controllers\MaintenanceController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ProjectHandoverController;
use App\Http\Controllers\ProjectInstallmentController;
use App\Http\Controllers\ProjectStageController;
use App\Http\Controllers\VariationController;
use Illuminate\Support\Facades\Route;

/*
| Stage 2 — projects. Loaded from routes/web.php inside the authenticated group.
*/

Route::resource('projects', ProjectController::class)->except(['destroy']);
Route::post('/projects/{project}/attachments', [ProjectController::class, 'attach'])->name('projects.attachments.store');

Route::post('/projects/{project}/stages', [ProjectStageController::class, 'store'])->name('projects.stages.store');
Route::post('/projects/{project}/stages/reorder', [ProjectStageController::class, 'reorder'])->name('projects.stages.reorder');
Route::put('/projects/{project}/stages/{stage}', [ProjectStageController::class, 'update'])->name('projects.stages.update');
Route::delete('/projects/{project}/stages/{stage}', [ProjectStageController::class, 'destroy'])->name('projects.stages.destroy');
Route::post('/projects/{project}/stages/{stage}/attachments', [ProjectStageController::class, 'attach'])->name('projects.stages.attachments.store');

Route::post('/projects/{project}/installments', [ProjectInstallmentController::class, 'store'])->name('projects.installments.store');
Route::put('/projects/{project}/installments/{installment}', [ProjectInstallmentController::class, 'update'])->name('projects.installments.update');
Route::delete('/projects/{project}/installments/{installment}', [ProjectInstallmentController::class, 'destroy'])->name('projects.installments.destroy');

Route::get('/quotations/{quotation}/convert', [ConvertQuotationController::class, 'create'])->name('quotations.convert.create');
Route::post('/quotations/{quotation}/convert', [ConvertQuotationController::class, 'store'])->name('quotations.convert');

Route::get('/projects/{project}/variations/create', [VariationController::class, 'create'])->name('projects.variations.create');
Route::post('/projects/{project}/variations', [VariationController::class, 'store'])->name('projects.variations.store');
Route::get('/projects/{project}/variations/{variation}', [VariationController::class, 'show'])->name('projects.variations.show');
Route::get('/projects/{project}/variations/{variation}/edit', [VariationController::class, 'edit'])->name('projects.variations.edit');
Route::put('/projects/{project}/variations/{variation}', [VariationController::class, 'update'])->name('projects.variations.update');
Route::patch('/projects/{project}/variations/{variation}/approve', [VariationController::class, 'approve'])->name('projects.variations.approve');
Route::patch('/projects/{project}/variations/{variation}/reject', [VariationController::class, 'reject'])->name('projects.variations.reject');
Route::post('/projects/{project}/variations/{variation}/attachments', [VariationController::class, 'attach'])->name('projects.variations.attachments.store');

Route::put('/projects/{project}/handover', [ProjectHandoverController::class, 'update'])->name('projects.handover.update');
Route::get('/projects/{project}/handover/pdf', [ProjectHandoverController::class, 'pdf'])->name('projects.handover.pdf');
Route::post('/projects/{project}/handover/attachments', [ProjectHandoverController::class, 'attach'])->name('projects.handover.attachments.store');

Route::resource('maintenance', MaintenanceController::class)->except(['destroy']);
Route::post('/maintenance/{maintenance}/attachments', [MaintenanceController::class, 'attach'])->name('maintenance.attachments.store');

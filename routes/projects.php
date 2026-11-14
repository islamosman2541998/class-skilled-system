<?php

use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ProjectStageController;
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

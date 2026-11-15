<?php

use App\Http\Controllers\DailyReportController;
use App\Http\Controllers\ProjectItemController;
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

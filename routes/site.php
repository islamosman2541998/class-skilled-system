<?php

use App\Http\Controllers\DailyReportController;
use Illuminate\Support\Facades\Route;

/*
| Stage 4 (site): daily reports and progress claims. Loaded from routes/web.php inside the
| authenticated group.
*/

Route::resource('daily-reports', DailyReportController::class)->except(['destroy']);
Route::post('/daily-reports/{daily_report}/attachments', [DailyReportController::class, 'attach'])->name('daily-reports.attachments.store');

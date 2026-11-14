<?php

use App\Http\Controllers\ProjectController;
use Illuminate\Support\Facades\Route;

/*
| Stage 2 — projects. Loaded from routes/web.php inside the authenticated group.
*/

Route::resource('projects', ProjectController::class)->except(['destroy']);
Route::post('/projects/{project}/attachments', [ProjectController::class, 'attach'])->name('projects.attachments.store');

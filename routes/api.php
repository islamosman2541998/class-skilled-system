<?php

use App\Http\Controllers\Api\WebsiteLeadController;
use App\Http\Middleware\VerifyWebsiteKey;
use Illuminate\Support\Facades\Route;

/*
| Machine-to-machine endpoints. The website sends quote requests here (spec §10).
*/

Route::post('/v1/website/leads', [WebsiteLeadController::class, 'store'])
    ->middleware(['throttle:website', VerifyWebsiteKey::class])
    ->name('api.website.leads');

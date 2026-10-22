<?php

use Illuminate\Support\Facades\Route;
use Modules\WhatsappShare\Http\Controllers\WhatsappSettingsController;

/*
|--------------------------------------------------------------------------
| WhatsApp Share API Routes
|--------------------------------------------------------------------------
|
| Loaded by the module's RouteServiceProvider under the /api prefix.
| Sanctum + company middleware mirror the host's /api/v1 routes, so the
| frontend http client (which sends the auth token and company header)
| works against these endpoints unchanged.
|
*/

Route::middleware(['auth:sanctum', 'company'])
    ->prefix('m/whatsapp-share')
    ->group(function () {
        Route::get('/settings', [WhatsappSettingsController::class, 'show']);
        Route::post('/settings', [WhatsappSettingsController::class, 'store']);
    });

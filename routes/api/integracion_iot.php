<?php

use App\Modulos\IntegracionIot\Controllers\IntegracionIotController;
use Illuminate\Support\Facades\Route;

Route::prefix('integracion/iot')->group(function (): void {
    Route::post('/webhook', [IntegracionIotController::class, 'Webhook']);

    Route::middleware('auth:api_negocio')->group(function (): void {
        Route::get('/evento', [IntegracionIotController::class, 'ListarEventos']);
        Route::get('/evento/{tnEvento}', [IntegracionIotController::class, 'ObtenerEvento']);
    });
});

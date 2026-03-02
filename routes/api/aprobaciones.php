<?php

use App\Modulos\Aprobacion\Controllers\AprobacionController;
use Illuminate\Support\Facades\Route;

Route::prefix('aprobaciones')->group(function (): void {
    Route::post('/solicitar', [AprobacionController::class, 'Solicitar']);
    Route::post('/{tnAprobacion}/aprobar', [AprobacionController::class, 'Aprobar']);
    Route::post('/{tnAprobacion}/rechazar', [AprobacionController::class, 'Rechazar']);
    Route::get('/', [AprobacionController::class, 'Listar']);
});

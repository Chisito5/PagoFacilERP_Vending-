<?php

use App\Modulos\Tablero\Controllers\TableroController;
use Illuminate\Support\Facades\Route;

Route::prefix('tablero')->group(function () {
    Route::get('/resumen', [TableroController::class, 'Resumen']);

    Route::prefix('ejecutivo')->group(function (): void {
        Route::get('/resumen', [TableroController::class, 'EjecutivoResumen']);
        Route::get('/maquinas', [TableroController::class, 'EjecutivoMaquinas']);
        Route::get('/mapa', [TableroController::class, 'EjecutivoMapa']);
        Route::get('/ranking', [TableroController::class, 'EjecutivoRanking']);
        Route::get('/maquina/{tnMaquina}/detalle', [TableroController::class, 'EjecutivoDetalleMaquina']);
        Route::get('/unificado', [TableroController::class, 'EjecutivoUnificado']);
    });
});

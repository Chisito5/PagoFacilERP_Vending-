<?php

use App\Modulos\MaquinaCeldas\Controllers\MaquinaCeldasController;
use Illuminate\Support\Facades\Route;

Route::prefix('maquina/{tnMaquina}/celdas')->group(function (): void {
    Route::get('/matriz', [MaquinaCeldasController::class, 'Matriz']);
    Route::get('/conflictos', [MaquinaCeldasController::class, 'Conflictos']);
    Route::post('/simular-ocupacion', [MaquinaCeldasController::class, 'SimularOcupacion']);
    Route::post('/asignar-producto', [MaquinaCeldasController::class, 'AsignarProducto'])->middleware('idempotencia.requerida');
    Route::post('/liberar', [MaquinaCeldasController::class, 'Liberar'])->middleware('idempotencia.requerida');
});


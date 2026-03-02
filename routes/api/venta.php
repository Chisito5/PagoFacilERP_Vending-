<?php

use Illuminate\Support\Facades\Route;
use App\Modulos\Venta\Controllers\VentaController;


Route::prefix('venta')->group(function () {
    Route::post('/', [VentaController::class, 'Crear'])->middleware('idempotencia.requerida');
    Route::get('/', [VentaController::class, 'Listar']);
    Route::get('/maquina/{tnMaquina}', [VentaController::class, 'ListarPorMaquina']);
    Route::post('/reversa', [VentaController::class, 'Reversar'])->middleware('idempotencia.requerida');
});

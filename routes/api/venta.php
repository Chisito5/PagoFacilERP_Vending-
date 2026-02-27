<?php

use Illuminate\Support\Facades\Route;
use App\Modulos\Venta\Controllers\VentaController;


Route::prefix('venta')->group(function () {
    Route::post('/', [VentaController::class, 'Crear']);              // procesar venta
    Route::get('/', [VentaController::class, 'Listar']);              // listar ventas
    Route::get('/maquina/{Maquina}', [VentaController::class, 'ListarPorMaquina']); // filtrar por maquina
    Route::post('/reversa', [VentaController::class, 'Reversar']);
});

<?php

use Illuminate\Support\Facades\Route;
use App\Modulos\Stock\Controllers\StockController;

Route::prefix('stock')->group(function () {
    // GET /api/stock/maquina/1
    Route::get('maquina/{IdMaquina}', [StockController::class, 'StockPorMaquina']);
    Route::get('maquina/{tnMaquina}/seleccion/{tcCodigoSeleccion}', [StockController::class, 'StockPorSeleccion']);
});

<?php

use Illuminate\Support\Facades\Route;
use App\Modulos\Stock\Controllers\StockController;

Route::prefix('stock')->group(function () {
    Route::get('/movimientos', [StockController::class, 'Movimientos']);
    Route::get('/maquina/{IdMaquina}', [StockController::class, 'StockPorMaquina']);
    Route::get('/maquina/{IdMaquina}/seleccion/{CodigoSeleccion}', [StockController::class, 'StockPorSeleccion']);
});

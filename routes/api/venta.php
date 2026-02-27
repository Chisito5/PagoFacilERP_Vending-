<?php

use Illuminate\Support\Facades\Route;
use App\Modulos\Venta\Controllers\VentaController;

Route::prefix('venta')->group(function () {
    Route::post('/', [VentaController::class, 'Vender']);
});
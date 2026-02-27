<?php

use Illuminate\Support\Facades\Route;
use App\Modulos\Venta\Controllers\VentaReversaController;

Route::prefix('venta')->group(function () {
    Route::post('/reversa', [VentaReversaController::class, 'Reversa']);
});
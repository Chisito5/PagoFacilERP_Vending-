<?php

use Illuminate\Support\Facades\Route;
use App\Modulos\Reserva\Controllers\ReservaController;

Route::prefix('reserva')->group(function () {
    Route::post('/', [ReservaController::class, 'Reservar']);
    Route::post('/cancelar', [ReservaController::class, 'Cancelar']);
    Route::post('/confirmar', [ReservaController::class, 'Confirmar']);
});
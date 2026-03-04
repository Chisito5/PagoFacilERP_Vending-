<?php

use Illuminate\Support\Facades\Route;
use App\Modulos\Kiosko\Controllers\KioskoController;

Route::prefix('kiosko/v1')->group(function (): void {
    Route::get('/maquina/{Maquina}/catalogo', [KioskoController::class, 'Catalogo']);

    Route::post('/venta', [KioskoController::class, 'Venta'])
        ->middleware('idempotencia.requerida');

    Route::post('/venta/reversa', [KioskoController::class, 'ReversaVenta'])
        ->middleware('idempotencia.requerida');

    Route::post('/reserva', [KioskoController::class, 'CrearReserva'])
        ->middleware('idempotencia.requerida');

    Route::post('/reserva/confirmar', [KioskoController::class, 'ConfirmarReserva'])
        ->middleware('idempotencia.requerida');

    Route::post('/reserva/cancelar', [KioskoController::class, 'CancelarReserva'])
        ->middleware('idempotencia.requerida');
});

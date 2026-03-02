<?php

use App\Modulos\Deposito\Controllers\DepositoController;
use Illuminate\Support\Facades\Route;

Route::prefix('deposito')->group(function (): void {
    Route::get('/', [DepositoController::class, 'Listar']);
    Route::get('/{tnDeposito}/stock', [DepositoController::class, 'Stock']);
    Route::get('/movimientos', [DepositoController::class, 'Movimientos']);

    Route::post('/movimiento/entrada', [DepositoController::class, 'Entrada'])->middleware('idempotencia.requerida');
    Route::post('/movimiento/salida', [DepositoController::class, 'Salida'])->middleware('idempotencia.requerida');
    Route::post('/transferir-a-maquina', [DepositoController::class, 'TransferirAMaquina'])->middleware('idempotencia.requerida');
});


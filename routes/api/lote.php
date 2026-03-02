<?php

use App\Modulos\Lote\Controllers\LoteController;
use Illuminate\Support\Facades\Route;

Route::prefix('lote')->group(function (): void {
    Route::get('/', [LoteController::class, 'Listar']);
    Route::get('/{tnLote}', [LoteController::class, 'Obtener']);
    Route::post('/', [LoteController::class, 'Crear']);
    Route::put('/{tnLote}', [LoteController::class, 'Actualizar']);
    Route::patch('/{tnLote}', [LoteController::class, 'ActualizarParcial']);
    Route::delete('/{tnLote}', [LoteController::class, 'EliminarLogico']);
});

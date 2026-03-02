<?php

use App\Modulos\Celda\Controllers\CeldaController;
use Illuminate\Support\Facades\Route;

Route::prefix('celda')->group(function (): void {
    Route::get('/', [CeldaController::class, 'Listar']);
    Route::get('/{tnCelda}', [CeldaController::class, 'Obtener']);
    Route::post('/', [CeldaController::class, 'Crear']);
    Route::put('/{tnCelda}', [CeldaController::class, 'Actualizar']);
    Route::patch('/{tnCelda}', [CeldaController::class, 'ActualizarParcial']);
    Route::delete('/{tnCelda}', [CeldaController::class, 'EliminarLogico']);
});

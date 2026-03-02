<?php

use App\Modulos\Merma\Controllers\MermaController;
use Illuminate\Support\Facades\Route;

Route::prefix('merma')->group(function (): void {
    Route::get('/', [MermaController::class, 'Listar']);
    Route::get('/{tnMerma}', [MermaController::class, 'Obtener']);
    Route::post('/', [MermaController::class, 'Crear']);
    Route::put('/{tnMerma}', [MermaController::class, 'Actualizar']);
    Route::patch('/{tnMerma}', [MermaController::class, 'ActualizarParcial']);
    Route::delete('/{tnMerma}', [MermaController::class, 'Eliminar']);

    Route::post('/{tnMerma}/detalle', [MermaController::class, 'AgregarDetalle']);
    Route::delete('/{tnMerma}/detalle/{tnMermaDetalle}', [MermaController::class, 'QuitarDetalle']);

    Route::post('/{tnMerma}/evidencia/subir', [MermaController::class, 'SubirEvidencia']);
    Route::get('/{tnMerma}/evidencia', [MermaController::class, 'ListarEvidencias']);

    Route::post('/{tnMerma}/aprobar', [MermaController::class, 'Aprobar']);
    Route::post('/{tnMerma}/rechazar', [MermaController::class, 'Rechazar']);
});

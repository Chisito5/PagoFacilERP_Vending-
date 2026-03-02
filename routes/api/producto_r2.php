<?php

use App\Modulos\ProductoDiseno\Controllers\ProductoDisenoController;
use Illuminate\Support\Facades\Route;

Route::prefix('producto/{tnProducto}')->group(function (): void {
    Route::get('/diseno', [ProductoDisenoController::class, 'ObtenerDiseno']);
    Route::post('/diseno', [ProductoDisenoController::class, 'CrearDiseno']);
    Route::patch('/diseno', [ProductoDisenoController::class, 'ActualizarDiseno']);
    Route::post('/diseno/render', [ProductoDisenoController::class, 'RenderDiseno']);

    Route::get('/galeria', [ProductoDisenoController::class, 'Galeria']);
    Route::post('/imagen/lote-subir', [ProductoDisenoController::class, 'SubirImagenLote']);
    Route::post('/galeria/reordenar', [ProductoDisenoController::class, 'ReordenarGaleria']);
});


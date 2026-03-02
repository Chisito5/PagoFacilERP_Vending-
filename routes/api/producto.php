<?php

use App\Modulos\Producto\Controllers\ProductoController;
use Illuminate\Support\Facades\Route;

Route::prefix('producto')->group(function (): void {
    Route::get('/', [ProductoController::class, 'Listar']);
    Route::get('/{tnProducto}', [ProductoController::class, 'Obtener']);
    Route::post('/', [ProductoController::class, 'Crear']);
    Route::put('/{tnProducto}', [ProductoController::class, 'Actualizar']);
    Route::patch('/{tnProducto}', [ProductoController::class, 'ActualizarParcial']);
    Route::delete('/{tnProducto}', [ProductoController::class, 'EliminarLogico']);
});

<?php

use App\Modulos\Maquina\Controllers\MaquinaController;
use Illuminate\Support\Facades\Route;

Route::prefix('maquina')->group(function (): void {
    Route::get('/', [MaquinaController::class, 'Listar']);
    Route::get('/{IdMaquina}', [MaquinaController::class, 'Obtener']);
    Route::get('/{IdMaquina}/celda', [MaquinaController::class, 'ListarCeldas']);
    Route::get('/{IdMaquina}/foto', [MaquinaController::class, 'ListarFotos']);
    Route::post('/{IdMaquina}/foto/lote-subir', [MaquinaController::class, 'SubirFotosLote']);
    Route::delete('/{IdMaquina}/foto/{IdMaquinaFoto}', [MaquinaController::class, 'EliminarFoto']);
    Route::post('/', [MaquinaController::class, 'Crear']);
    Route::put('/{IdMaquina}', [MaquinaController::class, 'Actualizar']);
    Route::patch('/{IdMaquina}', [MaquinaController::class, 'ActualizarParcial']);
    Route::delete('/{IdMaquina}', [MaquinaController::class, 'EliminarLogico']);
});

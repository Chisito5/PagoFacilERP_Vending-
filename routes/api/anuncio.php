<?php

use App\Modulos\Anuncio\Controllers\AnuncioController;
use Illuminate\Support\Facades\Route;

Route::prefix('anuncio')->group(function (): void {
    Route::get('/', [AnuncioController::class, 'Listar']);
    Route::get('/{tnAnuncio}', [AnuncioController::class, 'Obtener']);
    Route::post('/', [AnuncioController::class, 'Crear']);
    Route::put('/{tnAnuncio}', [AnuncioController::class, 'Actualizar']);
    Route::patch('/{tnAnuncio}', [AnuncioController::class, 'ActualizarParcial']);
    Route::delete('/{tnAnuncio}', [AnuncioController::class, 'Eliminar']);

    Route::post('/{tnAnuncio}/maquina', [AnuncioController::class, 'AsignarMaquina']);
    Route::delete('/{tnAnuncio}/maquina/{tnMaquina}', [AnuncioController::class, 'QuitarMaquina']);
    Route::post('/{tnAnuncio}/producto', [AnuncioController::class, 'AsignarProducto']);
    Route::delete('/{tnAnuncio}/producto/{tnProducto}', [AnuncioController::class, 'QuitarProducto']);

    Route::post('/{tnAnuncio}/publicar', [AnuncioController::class, 'Publicar']);
    Route::post('/{tnAnuncio}/detener', [AnuncioController::class, 'Detener']);
    Route::get('/{tnAnuncio}/impacto', [AnuncioController::class, 'Impacto']);
});

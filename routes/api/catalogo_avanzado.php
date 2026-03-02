<?php

use App\Modulos\CatalogoAvanzado\Controllers\CatalogoAvanzadoController;
use Illuminate\Support\Facades\Route;

Route::prefix('productofamilia')->group(function (): void {
    Route::get('/', [CatalogoAvanzadoController::class, 'ListarFamilias']);
    Route::get('/{tnId}', [CatalogoAvanzadoController::class, 'ObtenerFamilia']);
    Route::post('/', [CatalogoAvanzadoController::class, 'CrearFamilia']);
    Route::put('/{tnId}', [CatalogoAvanzadoController::class, 'ActualizarFamilia']);
    Route::patch('/{tnId}', [CatalogoAvanzadoController::class, 'ActualizarFamiliaParcial']);
    Route::delete('/{tnId}', [CatalogoAvanzadoController::class, 'EliminarFamilia']);
});

Route::prefix('productogrupo')->group(function (): void {
    Route::get('/', [CatalogoAvanzadoController::class, 'ListarGrupos']);
    Route::get('/{tnId}', [CatalogoAvanzadoController::class, 'ObtenerGrupo']);
    Route::post('/', [CatalogoAvanzadoController::class, 'CrearGrupo']);
    Route::put('/{tnId}', [CatalogoAvanzadoController::class, 'ActualizarGrupo']);
    Route::patch('/{tnId}', [CatalogoAvanzadoController::class, 'ActualizarGrupoParcial']);
    Route::delete('/{tnId}', [CatalogoAvanzadoController::class, 'EliminarGrupo']);
});

Route::prefix('productosubgrupo')->group(function (): void {
    Route::get('/', [CatalogoAvanzadoController::class, 'ListarSubgrupos']);
    Route::get('/{tnId}', [CatalogoAvanzadoController::class, 'ObtenerSubgrupo']);
    Route::post('/', [CatalogoAvanzadoController::class, 'CrearSubgrupo']);
    Route::put('/{tnId}', [CatalogoAvanzadoController::class, 'ActualizarSubgrupo']);
    Route::patch('/{tnId}', [CatalogoAvanzadoController::class, 'ActualizarSubgrupoParcial']);
    Route::delete('/{tnId}', [CatalogoAvanzadoController::class, 'EliminarSubgrupo']);
});

Route::prefix('productoimagen')->group(function (): void {
    Route::get('/', [CatalogoAvanzadoController::class, 'ListarImagenes']);
    Route::get('/{tnId}', [CatalogoAvanzadoController::class, 'ObtenerImagen']);
    Route::post('/', [CatalogoAvanzadoController::class, 'CrearImagen']);
    Route::put('/{tnId}', [CatalogoAvanzadoController::class, 'ActualizarImagen']);
    Route::patch('/{tnId}', [CatalogoAvanzadoController::class, 'ActualizarImagenParcial']);
    Route::delete('/{tnId}', [CatalogoAvanzadoController::class, 'EliminarImagen']);
});

Route::post('/producto/{tnProducto}/imagen/subir', [CatalogoAvanzadoController::class, 'SubirImagenProducto']);
Route::delete('/producto/{tnProducto}/imagen/{tnProductoImagen}', [CatalogoAvanzadoController::class, 'EliminarImagenProducto']);

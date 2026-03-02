<?php

use Illuminate\Support\Facades\Route;
use App\Modulos\Reposicion\Controllers\ReposicionController;

Route::prefix('reposicion')->group(function () {
    Route::post('/prevalidar', [ReposicionController::class, 'Prevalidar']);
    Route::post('/', [ReposicionController::class, 'RecargarPorSeleccion'])->middleware('idempotencia.requerida');
    Route::get('/', [ReposicionController::class, 'Listar']);
    Route::get('/maquina/{tnMaquina}', [ReposicionController::class, 'ListarPorMaquina']);
    Route::get('/{tnReposicion}', [ReposicionController::class, 'Obtener']);
});

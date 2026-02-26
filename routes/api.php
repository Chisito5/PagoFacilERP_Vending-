<?php

use Illuminate\Support\Facades\Route;
use App\Modulos\Empresa\Controllers\EmpresaController;
use App\Modulos\Estado\Controllers\EstadoController;
use App\Modulos\TipoEmpresa\Controllers\TipoEmpresaController;

Route::prefix('empresa')->group(function () {
    Route::get('/', [EmpresaController::class, 'Listar']);
    Route::post('/', [EmpresaController::class, 'Crear']);
});

Route::get('/estado', [EstadoController::class, 'Listar']);
Route::get('/tipoempresa', [TipoEmpresaController::class, 'Listar']);
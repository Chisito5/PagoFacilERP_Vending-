<?php

use Illuminate\Support\Facades\Route;
use App\Modulos\Empresa\Controllers\EmpresaController;

Route::prefix('empresa')->group(function () {
    Route::get('/', [EmpresaController::class, 'Listar']);
    Route::post('/', [EmpresaController::class, 'Crear']);
});
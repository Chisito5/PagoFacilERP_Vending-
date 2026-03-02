<?php

use App\Modulos\Empresa\Controllers\EmpresaController;
use Illuminate\Support\Facades\Route;

Route::prefix('empresa')->group(function (): void {
    Route::get('/', [EmpresaController::class, 'Listar']);
    Route::get('/{tnEmpresa}', [EmpresaController::class, 'Obtener']);
    Route::post('/', [EmpresaController::class, 'Crear']);
    Route::put('/{tnEmpresa}', [EmpresaController::class, 'Actualizar']);
    Route::patch('/{tnEmpresa}', [EmpresaController::class, 'ActualizarParcial']);
    Route::delete('/{tnEmpresa}', [EmpresaController::class, 'EliminarLogico']);
});

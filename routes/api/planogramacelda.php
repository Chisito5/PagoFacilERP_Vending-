<?php

use App\Modulos\PlanogramaCelda\Controllers\PlanogramaCeldaController;
use Illuminate\Support\Facades\Route;

Route::prefix('planogramacelda')->group(function (): void {
    Route::get('/', [PlanogramaCeldaController::class, 'Listar']);
    Route::get('/{tnPlanogramaCelda}', [PlanogramaCeldaController::class, 'Obtener']);
    Route::post('/', [PlanogramaCeldaController::class, 'Crear']);
    Route::put('/{tnPlanogramaCelda}', [PlanogramaCeldaController::class, 'Actualizar']);
    Route::patch('/{tnPlanogramaCelda}', [PlanogramaCeldaController::class, 'ActualizarParcial']);
    Route::patch('/{tnPlanogramaCelda}/precio', [PlanogramaCeldaController::class, 'ActualizarPrecio']);
    Route::delete('/{tnPlanogramaCelda}', [PlanogramaCeldaController::class, 'EliminarLogico']);
});

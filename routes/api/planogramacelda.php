<?php

use Illuminate\Support\Facades\Route;
use App\Modulos\PlanogramaCelda\Controllers\PlanogramaCeldaController;

Route::get('/planogramacelda', [PlanogramaCeldaController::class, 'Listar']);
Route::get('/planogramacelda/celda/{IdCelda}', [PlanogramaCeldaController::class, 'ListarPorCelda']);
Route::get('/planogramacelda/planograma/{IdPlanograma}', [PlanogramaCeldaController::class, 'ListarPorPlanograma']);
Route::patch('/{PlanogramaCelda}/precio', [PlanogramaCeldaController::class, 'ActualizarPrecio']);

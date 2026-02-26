<?php

use Illuminate\Support\Facades\Route;
use App\Modulos\ExistenciaCelda\Controllers\ExistenciaCeldaController;

Route::get('/existenciacelda', [ExistenciaCeldaController::class, 'Listar']);
Route::get('/existenciacelda/celda/{IdCelda}', [ExistenciaCeldaController::class, 'ListarPorCelda']);
<?php

use Illuminate\Support\Facades\Route;
use App\Modulos\Maquina\Controllers\MaquinaController;

Route::get('/maquina', [MaquinaController::class, 'Listar']);
Route::get('/maquina/{IdMaquina}/celda', [MaquinaController::class, 'ListarCeldas']);
<?php

use Illuminate\Support\Facades\Route;
use App\Modulos\Estado\Controllers\EstadoController;

Route::get('/estado', [EstadoController::class, 'Listar']);
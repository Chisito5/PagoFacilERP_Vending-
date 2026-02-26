<?php

use Illuminate\Support\Facades\Route;
use App\Modulos\Producto\Controllers\ProductoController;

Route::get('/producto', [ProductoController::class, 'Listar']);
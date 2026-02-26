<?php

use Illuminate\Support\Facades\Route;
use App\Modulos\TipoEmpresa\Controllers\TipoEmpresaController;

Route::get('/tipoempresa', [TipoEmpresaController::class, 'Listar']);
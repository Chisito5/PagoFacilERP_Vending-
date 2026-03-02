<?php

use App\Modulos\TipoLugarInstalacion\Controllers\TipoLugarInstalacionController;
use Illuminate\Support\Facades\Route;

Route::get('/tipolugarinstalacion', [TipoLugarInstalacionController::class, 'Listar']);

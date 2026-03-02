<?php

use App\Modulos\TipoInternet\Controllers\TipoInternetController;
use Illuminate\Support\Facades\Route;

Route::get('/tipointernet', [TipoInternetController::class, 'Listar']);

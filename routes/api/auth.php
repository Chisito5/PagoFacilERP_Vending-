<?php

use App\Modulos\Autenticacion\Controllers\AutenticacionController;
use Illuminate\Broadcasting\BroadcastController;
use Illuminate\Support\Facades\Route;

Route::post('/broadcasting/auth', [BroadcastController::class, 'authenticate'])
    ->middleware('auth:api_negocio');

Route::prefix('auth')->group(function () {
    Route::post('/login', [AutenticacionController::class, 'Login']);
    Route::post('/refresh', [AutenticacionController::class, 'Refresh']);

    Route::middleware('auth:api_negocio')->group(function () {
        Route::post('/logout', [AutenticacionController::class, 'Logout']);
        Route::get('/me', [AutenticacionController::class, 'Me']);
        Route::get('/permisos', [AutenticacionController::class, 'Permisos']);
    });
});

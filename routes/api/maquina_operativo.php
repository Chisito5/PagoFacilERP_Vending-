<?php

use App\Modulos\MaquinaOperativo\Controllers\MaquinaOperativoController;
use Illuminate\Support\Facades\Route;

Route::get('/maquina/{tnMaquina}/ubicacion', [MaquinaOperativoController::class, 'ObtenerUbicacion']);
Route::put('/maquina/{tnMaquina}/ubicacion', [MaquinaOperativoController::class, 'ActualizarUbicacion']);
Route::get('/maquina/{tnMaquina}/estado-operativo', [MaquinaOperativoController::class, 'ObtenerEstadoOperativo']);
Route::post('/maquina/{tnMaquina}/estado-operativo', [MaquinaOperativoController::class, 'ActualizarEstadoOperativo']);
Route::get('/maquina/{tnMaquina}/estado-operativo/historial', [MaquinaOperativoController::class, 'HistorialEstadoOperativo']);

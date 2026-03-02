<?php

use App\Modulos\Alerta\Controllers\AlertaController;
use Illuminate\Support\Facades\Route;

Route::prefix('reglaalerta')->group(function (): void {
    Route::get('/', [AlertaController::class, 'ListarReglas']);
    Route::get('/{tnRegla}', [AlertaController::class, 'ObtenerRegla']);
    Route::post('/', [AlertaController::class, 'CrearRegla']);
    Route::put('/{tnRegla}', [AlertaController::class, 'ActualizarRegla']);
    Route::patch('/{tnRegla}', [AlertaController::class, 'ActualizarReglaParcial']);
    Route::delete('/{tnRegla}', [AlertaController::class, 'EliminarRegla']);
});

Route::prefix('alerta')->group(function (): void {
    Route::get('/', [AlertaController::class, 'ListarAlertas']);
    Route::get('/{tnAlerta}', [AlertaController::class, 'ObtenerAlerta']);
    Route::post('/{tnAlerta}/atender', [AlertaController::class, 'AtenderAlerta']);
    Route::post('/{tnAlerta}/escalar', [AlertaController::class, 'EscalarAlerta']);
    Route::post('/{tnAlerta}/cerrar', [AlertaController::class, 'CerrarAlerta']);
});

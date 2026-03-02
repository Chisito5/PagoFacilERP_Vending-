<?php

use App\Modulos\Analitica\Controllers\AnaliticaController;
use Illuminate\Support\Facades\Route;

Route::prefix('analitica')->group(function (): void {
    Route::get('/ventas', [AnaliticaController::class, 'Ventas']);
    Route::get('/rotacion', [AnaliticaController::class, 'Rotacion']);
    Route::get('/stockout', [AnaliticaController::class, 'Stockout']);
    Route::get('/rentabilidad', [AnaliticaController::class, 'Rentabilidad']);
    Route::get('/mermas', [AnaliticaController::class, 'Mermas']);
    Route::get('/resumen', [AnaliticaController::class, 'Resumen']);
});

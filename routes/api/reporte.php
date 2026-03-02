<?php

use App\Modulos\Reporte\Controllers\ReporteController;
use Illuminate\Support\Facades\Route;

Route::prefix('reporte')->group(function (): void {
    Route::post('/generar', [ReporteController::class, 'Generar']);
    Route::get('/', [ReporteController::class, 'Listar']);
    Route::get('/{tnReporte}', [ReporteController::class, 'Obtener']);
    Route::get('/{tnReporte}/descargar', [ReporteController::class, 'Descargar'])->name('reporte.descargar');
});

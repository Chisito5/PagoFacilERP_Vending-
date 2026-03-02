<?php

use App\Modulos\Auditoria\Controllers\AuditoriaController;
use Illuminate\Support\Facades\Route;

Route::prefix('auditoria')->group(function (): void {
    Route::get('/{tcEntidad}/{tcEntidadId}', [AuditoriaController::class, 'Historial']);
});

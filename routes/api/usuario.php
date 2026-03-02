<?php

use App\Modulos\Usuario\Controllers\UsuarioController;
use Illuminate\Support\Facades\Route;

Route::prefix('usuario')->group(function (): void {
    Route::get('/', [UsuarioController::class, 'Listar']);
    Route::get('/{tnUsuario}', [UsuarioController::class, 'Obtener']);
    Route::post('/', [UsuarioController::class, 'Crear']);
    Route::put('/{tnUsuario}', [UsuarioController::class, 'Actualizar']);
    Route::patch('/{tnUsuario}', [UsuarioController::class, 'ActualizarParcial']);
    Route::delete('/{tnUsuario}', [UsuarioController::class, 'EliminarLogico']);

    Route::get('/{tnUsuario}/rol', [UsuarioController::class, 'ListarRoles']);
    Route::put('/{tnUsuario}/rol', [UsuarioController::class, 'ActualizarRoles']);

    Route::get('/{tnUsuario}/maquina', [UsuarioController::class, 'ListarMaquinas']);
    Route::post('/{tnUsuario}/maquina', [UsuarioController::class, 'AsignarMaquina']);
    Route::delete('/{tnUsuario}/maquina/{tnMaquina}', [UsuarioController::class, 'QuitarMaquina']);
});

Route::get('/maquina/{tnMaquina}/administradores', [UsuarioController::class, 'ListarAdministradoresMaquina']);
Route::get('/maquina/{tnMaquina}/operadores', [UsuarioController::class, 'ListarOperadoresMaquina']);

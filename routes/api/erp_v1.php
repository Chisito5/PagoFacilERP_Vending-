<?php

use App\Modulos\ErpV1\Controllers\AnaliticaErpController;
use App\Modulos\ErpV1\Controllers\AuthErpController;
use App\Modulos\ErpV1\Controllers\CatalogoErpController;
use App\Modulos\ErpV1\Controllers\TableroErpController;
use App\Modulos\ErpV1\Controllers\TransaccionErpController;
use App\Modulos\ErpV1\Controllers\VentaErpController;
use Illuminate\Support\Facades\Route;

Route::prefix('erp/v1')
    ->middleware(['erp.v1.habilitado', 'api.correlacion'])
    ->group(function (): void {
        Route::prefix('auth')->group(function (): void {
            Route::post('/login', [AuthErpController::class, 'Login']);
            Route::post('/refresh', [AuthErpController::class, 'Refresh']);

            Route::middleware('auth:api_negocio')->group(function (): void {
                Route::post('/logout', [AuthErpController::class, 'Logout']);
                Route::get('/perfil', [AuthErpController::class, 'Perfil']);
                Route::get('/permisos', [AuthErpController::class, 'Permisos']);
            });
        });

        Route::middleware('auth:api_negocio')->group(function (): void {
            Route::prefix('tablero/ejecutivo')->group(function (): void {
                Route::get('/unificado', [TableroErpController::class, 'Unificado']);
                Route::get('/resumen', [TableroErpController::class, 'Resumen']);
                Route::get('/maquinas', [TableroErpController::class, 'Maquinas']);
                Route::get('/mapa', [TableroErpController::class, 'Mapa']);
                Route::get('/ranking', [TableroErpController::class, 'Ranking']);
                Route::get('/maquina/{IdMaquina}/detalle', [TableroErpController::class, 'Detalle']);
            });

            Route::prefix('venta')->group(function (): void {
                Route::post('/', [VentaErpController::class, 'Crear'])->middleware('idempotencia.requerida');
                Route::get('/', [VentaErpController::class, 'Listar']);
                Route::get('/{IdVenta}', [VentaErpController::class, 'Obtener']);
                Route::get('/maquina/{IdMaquina}', [VentaErpController::class, 'ListarPorMaquina']);
                Route::post('/reversa', [VentaErpController::class, 'Reversar'])->middleware('idempotencia.requerida');
                Route::get('/{IdVenta}/historial', [VentaErpController::class, 'Historial']);
            });

            Route::prefix('transacciones')->group(function (): void {
                Route::get('/', [TransaccionErpController::class, 'Listar']);
                Route::get('/{IdTransaccion}', [TransaccionErpController::class, 'Obtener']);
            });

            Route::prefix('analitica')->group(function (): void {
                Route::get('/resumen', [AnaliticaErpController::class, 'Resumen']);
                Route::get('/ventas', [AnaliticaErpController::class, 'Ventas']);
                Route::get('/rotacion', [AnaliticaErpController::class, 'Rotacion']);
                Route::get('/stockout', [AnaliticaErpController::class, 'Stockout']);
                Route::get('/rentabilidad', [AnaliticaErpController::class, 'Rentabilidad']);
                Route::get('/mermas', [AnaliticaErpController::class, 'Mermas']);
            });

            Route::prefix('catalogo')->group(function (): void {
                Route::get('/maquina', [CatalogoErpController::class, 'Maquina']);
                Route::get('/producto', [CatalogoErpController::class, 'Producto']);
                Route::get('/oferta', [CatalogoErpController::class, 'Oferta']);
                Route::get('/metodo-pago', [CatalogoErpController::class, 'MetodoPago']);
                Route::get('/estado-transaccion', [CatalogoErpController::class, 'EstadoTransaccion']);
            });
        });
    });


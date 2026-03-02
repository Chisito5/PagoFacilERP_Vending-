<?php

use Illuminate\Support\Facades\Route;

require __DIR__ . '/api/auth.php';
require __DIR__ . '/api/integracion_iot.php';

Route::middleware('auth:api_negocio')->group(function (): void {
    require __DIR__ . '/api/empresa.php';
    require __DIR__ . '/api/usuario.php';
    require __DIR__ . '/api/estado.php';
    require __DIR__ . '/api/tipoempresa.php';
    require __DIR__ . '/api/tipointernet.php';
    require __DIR__ . '/api/tipolugarinstalacion.php';
    require __DIR__ . '/api/venta.php';
    require __DIR__ . '/api/maquina.php';
    require __DIR__ . '/api/maquina_celdas.php';
    require __DIR__ . '/api/maquina_operativo.php';
    require __DIR__ . '/api/celda.php';
    require __DIR__ . '/api/lote.php';
    require __DIR__ . '/api/producto.php';
    require __DIR__ . '/api/producto_r2.php';
    require __DIR__ . '/api/catalogo_avanzado.php';
    require __DIR__ . '/api/planogramacelda.php';
    require __DIR__ . '/api/existenciacelda.php';
    require __DIR__ . '/api/stock.php';
    require __DIR__ . '/api/reposicion.php';
    require __DIR__ . '/api/deposito.php';
    require __DIR__ . '/api/reserva.php';
    require __DIR__ . '/api/venta_reversa.php';
    require __DIR__ . '/api/tablero.php';
    require __DIR__ . '/api/anuncio.php';
    require __DIR__ . '/api/merma.php';
    require __DIR__ . '/api/alerta.php';
    require __DIR__ . '/api/reporte.php';
    require __DIR__ . '/api/analitica.php';
    require __DIR__ . '/api/aprobaciones.php';
    require __DIR__ . '/api/auditoria.php';
});

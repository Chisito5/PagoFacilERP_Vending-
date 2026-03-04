<?php

namespace App\Modulos\ErpV1\Controllers;

use App\Http\Controllers\Controller;
use App\Modulos\ErpV1\Services\VentaErpService;
use App\Soporte\RespuestaApi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TransaccionErpController extends Controller
{
    public function __construct(private VentaErpService $toVentaErpService)
    {
    }

    public function Listar(Request $toRequest): JsonResponse
    {
        if ($toRequest->query('TamanoPagina') !== null && (int)$toRequest->query('TamanoPagina') > 200) {
            return RespuestaApi::error('Error de validacion', 422, [[
                'Codigo' => 'VAL_422',
                'Campo' => 'TamanoPagina',
                'Detalle' => 'TamanoPagina no puede ser mayor a 200'
            ]]);
        }

        $laFiltros = [
            'FechaDesde' => $toRequest->filled('FechaDesde') ? (string)$toRequest->query('FechaDesde') : null,
            'FechaHasta' => $toRequest->filled('FechaHasta') ? (string)$toRequest->query('FechaHasta') : null,
            'Maquina' => $toRequest->filled('Maquina') ? (int)$toRequest->query('Maquina') : null,
            'Estado' => $toRequest->filled('Estado') ? (int)$toRequest->query('Estado') : null,
            'Busqueda' => $toRequest->filled('Busqueda') ? (string)$toRequest->query('Busqueda') : null,
            'MontoDesde' => $toRequest->filled('MontoDesde') ? (float)$toRequest->query('MontoDesde') : null,
            'MontoHasta' => $toRequest->filled('MontoHasta') ? (float)$toRequest->query('MontoHasta') : null,
            'Orden' => $toRequest->filled('Orden') ? (string)$toRequest->query('Orden') : 'FechaHoraVenta',
            'Direccion' => $toRequest->filled('Direccion') ? (string)$toRequest->query('Direccion') : 'DESC',
        ];

        $toPaginador = $this->toVentaErpService->listarTransacciones(
            $laFiltros,
            (int)$toRequest->query('Pagina', 1),
            (int)$toRequest->query('TamanoPagina', 20)
        );

        return RespuestaApi::paginado('Listado de transacciones', $toPaginador);
    }

    public function Obtener(int $IdTransaccion): JsonResponse
    {
        $la = $this->toVentaErpService->obtenerTransaccion($IdTransaccion);
        if (!$la) {
            return RespuestaApi::error('Transaccion no encontrada', 404, [[
                'Codigo' => 'NEG_404',
                'Campo' => 'IdTransaccion',
                'Detalle' => 'No existe la transaccion solicitada'
            ]]);
        }

        return RespuestaApi::exito('Detalle de transaccion', $la);
    }
}


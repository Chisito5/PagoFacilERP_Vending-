<?php

namespace App\Modulos\Stock\Controllers;

use App\Http\Controllers\Controller;
use App\Modulos\Stock\Services\StockService;
use App\Soporte\RespuestaApi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StockController extends Controller
{
    public function __construct(private StockService $toStockService)
    {
    }

    public function StockPorSeleccion(int $IdMaquina, string $CodigoSeleccion): JsonResponse
    {
        $laDatos = $this->toStockService->StockPorSeleccion($IdMaquina, $CodigoSeleccion);
        if (!$laDatos) {
            return RespuestaApi::error(
                'No se encontro la seleccion para esa maquina',
                404,
                [
                    [
                        'Codigo' => 'STOCK_404',
                        'Campo' => 'CodigoSeleccion',
                        'Detalle' => 'No existe configuracion de stock para la seleccion solicitada',
                    ]
                ]
            );
        }

        return RespuestaApi::exito('Stock por seleccion', $laDatos);
    }

    public function StockPorMaquina(int $IdMaquina, Request $toRequest): JsonResponse
    {
        $tnCelda = $toRequest->filled('Celda') ? (int)$toRequest->query('Celda') : null;
        $tcCodigoSeleccion = $toRequest->filled('CodigoSeleccion') ? (string)$toRequest->query('CodigoSeleccion') : null;
        $tnLote = $toRequest->filled('Lote') ? (int)$toRequest->query('Lote') : null;
        $tnPagina = (int)$toRequest->query('Pagina', 1);
        $tnTamanoPagina = (int)$toRequest->query('TamanoPagina', 30);

        $toPaginador = $this->toStockService->StockPorMaquina(
            $IdMaquina,
            $tnCelda,
            $tcCodigoSeleccion,
            $tnLote,
            $tnPagina,
            $tnTamanoPagina
        );

        return RespuestaApi::paginado('Stock por maquina', $toPaginador);
    }

    public function Movimientos(Request $toRequest): JsonResponse
    {
        $tnPagina = (int)$toRequest->query('Pagina', 1);
        $tnTamanoPagina = (int)$toRequest->query('TamanoPagina', 30);

        $laFiltros = [
            'Maquina' => $toRequest->query('Maquina'),
            'Celda' => $toRequest->query('Celda'),
            'Lote' => $toRequest->query('Lote'),
            'TipoMovimiento' => $toRequest->query('TipoMovimiento'),
            'FechaDesde' => $toRequest->query('FechaDesde'),
            'FechaHasta' => $toRequest->query('FechaHasta'),
        ];

        $toPaginador = $this->toStockService->Movimientos($laFiltros, $tnPagina, $tnTamanoPagina);

        return RespuestaApi::paginado('Movimientos de inventario', $toPaginador);
    }
}

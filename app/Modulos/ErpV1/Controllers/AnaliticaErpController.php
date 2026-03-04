<?php

namespace App\Modulos\ErpV1\Controllers;

use App\Http\Controllers\Controller;
use App\Modulos\Analitica\Services\AnaliticaService;
use App\Soporte\RespuestaApi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AnaliticaErpController extends Controller
{
    public function __construct(private AnaliticaService $toAnaliticaService)
    {
    }

    public function Resumen(Request $toRequest): JsonResponse
    {
        return RespuestaApi::exito('Resumen analitico', $this->toAnaliticaService->resumen($this->filtros($toRequest)));
    }

    public function Ventas(Request $toRequest): JsonResponse
    {
        return $this->paginado('Analitica de ventas', $toRequest, fn () => $this->toAnaliticaService->ventas($this->filtros($toRequest), (int)$toRequest->query('Pagina', 1), (int)$toRequest->query('TamanoPagina', 20)));
    }

    public function Rotacion(Request $toRequest): JsonResponse
    {
        return $this->paginado('Analitica de rotacion', $toRequest, fn () => $this->toAnaliticaService->rotacion($this->filtros($toRequest), (int)$toRequest->query('Pagina', 1), (int)$toRequest->query('TamanoPagina', 20)));
    }

    public function Stockout(Request $toRequest): JsonResponse
    {
        return $this->paginado('Analitica de stockout', $toRequest, fn () => $this->toAnaliticaService->stockout($this->filtros($toRequest), (int)$toRequest->query('Pagina', 1), (int)$toRequest->query('TamanoPagina', 20)));
    }

    public function Rentabilidad(Request $toRequest): JsonResponse
    {
        return $this->paginado('Analitica de rentabilidad', $toRequest, fn () => $this->toAnaliticaService->rentabilidad($this->filtros($toRequest), (int)$toRequest->query('Pagina', 1), (int)$toRequest->query('TamanoPagina', 20)));
    }

    public function Mermas(Request $toRequest): JsonResponse
    {
        return $this->paginado('Analitica de mermas', $toRequest, fn () => $this->toAnaliticaService->mermas($this->filtros($toRequest), (int)$toRequest->query('Pagina', 1), (int)$toRequest->query('TamanoPagina', 20)));
    }

    /**
     * @return array<string,mixed>
     */
    private function filtros(Request $toRequest): array
    {
        return [
            'Empresa' => $toRequest->filled('Empresa') ? (int)$toRequest->query('Empresa') : null,
            'FechaDesde' => $toRequest->filled('FechaDesde') ? (string)$toRequest->query('FechaDesde') : null,
            'FechaHasta' => $toRequest->filled('FechaHasta') ? (string)$toRequest->query('FechaHasta') : null,
            'Maquina' => $toRequest->filled('Maquina') ? (int)$toRequest->query('Maquina') : null,
            'Producto' => $toRequest->filled('Producto') ? (int)$toRequest->query('Producto') : null,
        ];
    }

    private function paginado(string $tcMensaje, Request $toRequest, callable $tfConsulta): JsonResponse
    {
        if ($toRequest->query('TamanoPagina') !== null && (int)$toRequest->query('TamanoPagina') > 200) {
            return RespuestaApi::error('Error de validacion', 422, [[
                'Codigo' => 'VAL_422',
                'Campo' => 'TamanoPagina',
                'Detalle' => 'TamanoPagina no puede ser mayor a 200'
            ]]);
        }

        $toPaginador = $tfConsulta();
        return RespuestaApi::paginado($tcMensaje, $toPaginador);
    }
}


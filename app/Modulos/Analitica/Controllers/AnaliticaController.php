<?php

namespace App\Modulos\Analitica\Controllers;

use App\Http\Controllers\Controller;
use App\Modulos\Analitica\Services\AnaliticaService;
use App\Soporte\RespuestaApi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AnaliticaController extends Controller
{
    public function __construct(private AnaliticaService $toService)
    {
    }

    public function Ventas(Request $toRequest): JsonResponse
    {
        return $this->paginado('Detalle de ventas', fn () => $this->toService->ventas($this->filtros($toRequest), (int)$toRequest->query('Pagina', 1), (int)$toRequest->query('TamanoPagina', 20)));
    }

    public function Rotacion(Request $toRequest): JsonResponse
    {
        return $this->paginado('Rotacion de productos', fn () => $this->toService->rotacion($this->filtros($toRequest), (int)$toRequest->query('Pagina', 1), (int)$toRequest->query('TamanoPagina', 20)));
    }

    public function Stockout(Request $toRequest): JsonResponse
    {
        return $this->paginado('Celdas en stockout', fn () => $this->toService->stockout($this->filtros($toRequest), (int)$toRequest->query('Pagina', 1), (int)$toRequest->query('TamanoPagina', 20)));
    }

    public function Rentabilidad(Request $toRequest): JsonResponse
    {
        return $this->paginado('Rentabilidad aproximada', fn () => $this->toService->rentabilidad($this->filtros($toRequest), (int)$toRequest->query('Pagina', 1), (int)$toRequest->query('TamanoPagina', 20)));
    }

    public function Mermas(Request $toRequest): JsonResponse
    {
        return $this->paginado('Analitica de mermas', fn () => $this->toService->mermas($this->filtros($toRequest), (int)$toRequest->query('Pagina', 1), (int)$toRequest->query('TamanoPagina', 20)));
    }

    public function Resumen(Request $toRequest): JsonResponse
    {
        return RespuestaApi::exito('Resumen ejecutivo', $this->toService->resumen($this->filtros($toRequest)));
    }

    /** @return array<string,mixed> */
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

    private function paginado(string $tcMensaje, callable $tfConsulta): JsonResponse
    {
        $toPaginador = $tfConsulta();
        return RespuestaApi::paginado($tcMensaje, $toPaginador);
    }
}

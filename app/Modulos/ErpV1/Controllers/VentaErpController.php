<?php

namespace App\Modulos\ErpV1\Controllers;

use App\Http\Controllers\Controller;
use App\Modulos\ErpV1\Services\VentaErpService;
use App\Modulos\Venta\Services\VentaService;
use App\Soporte\RespuestaApi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VentaErpController extends Controller
{
    public function __construct(
        private VentaService $toVentaService,
        private VentaErpService $toVentaErpService
    ) {
    }

    public function Crear(Request $toRequest): JsonResponse
    {
        return $this->ejecutarIdempotente($toRequest, function () use ($toRequest) {
            $toRequest->validate([
                'Maquina' => ['required', 'integer', 'min:1'],
                'CodigoSeleccion' => ['required', 'string', 'max:10'],
                'Cantidad' => ['required', 'integer', 'min:1'],
                'Moneda' => ['nullable', 'in:BOB'],
            ]);

            $toCore = $this->toVentaService->VenderPorSeleccion(
                (int)$toRequest->input('Maquina'),
                (string)$toRequest->input('CodigoSeleccion'),
                (int)$toRequest->input('Cantidad')
            );

            $laBody = $toCore->getData(true);
            if (!is_array($laBody) || (($laBody['Ok'] ?? false) !== true)) {
                return $toCore;
            }

            $laDatos = is_array($laBody['Datos'] ?? null) ? $laBody['Datos'] : [];
            $laDatos['Moneda'] = 'BOB';
            return response()->json([
                'Ok' => true,
                'Mensaje' => (string)($laBody['Mensaje'] ?? 'Venta procesada correctamente'),
                'Datos' => $laDatos,
                'Errores' => [],
                'Meta' => [],
            ], $toCore->getStatusCode());
        });
    }

    public function Listar(Request $toRequest): JsonResponse
    {
        $toErr = $this->validarPaginacion($toRequest);
        if ($toErr) {
            return $toErr;
        }

        $toPaginador = $this->toVentaErpService->listarVentas($this->filtros($toRequest), (int)$toRequest->query('Pagina', 1), (int)$toRequest->query('TamanoPagina', 20));
        return RespuestaApi::paginado('Listado de ventas', $toPaginador);
    }

    public function Obtener(int $IdVenta): JsonResponse
    {
        $laVenta = $this->toVentaErpService->obtenerVenta($IdVenta);
        if (!$laVenta) {
            return RespuestaApi::error('Venta no encontrada', 404, [[
                'Codigo' => 'NEG_404',
                'Campo' => 'IdVenta',
                'Detalle' => 'No existe la venta solicitada'
            ]]);
        }

        return RespuestaApi::exito('Detalle de venta', $laVenta);
    }

    public function ListarPorMaquina(int $IdMaquina, Request $toRequest): JsonResponse
    {
        $toErr = $this->validarPaginacion($toRequest);
        if ($toErr) {
            return $toErr;
        }

        $laFiltros = $this->filtros($toRequest);
        $laFiltros['Maquina'] = $IdMaquina;

        $toPaginador = $this->toVentaErpService->listarVentas($laFiltros, (int)$toRequest->query('Pagina', 1), (int)$toRequest->query('TamanoPagina', 20));
        return RespuestaApi::paginado('Listado de ventas por maquina', $toPaginador);
    }

    public function Reversar(Request $toRequest): JsonResponse
    {
        return $this->ejecutarIdempotente($toRequest, function () use ($toRequest) {
            $toRequest->validate([
                'Venta' => ['required', 'integer', 'min:1'],
                'Motivo' => ['required', 'string', 'max:255'],
                'Moneda' => ['nullable', 'in:BOB'],
            ]);

            $toCore = $this->toVentaService->Reversar(
                (int)$toRequest->input('Venta'),
                (string)$toRequest->input('Motivo')
            );

            return $toCore;
        });
    }

    public function Historial(int $IdVenta, Request $toRequest): JsonResponse
    {
        $toErr = $this->validarPaginacion($toRequest);
        if ($toErr) {
            return $toErr;
        }

        $laHistorial = $this->toVentaErpService->historialVenta(
            $IdVenta,
            (int)$toRequest->query('Pagina', 1),
            (int)$toRequest->query('TamanoPagina', 20)
        );

        if (($laHistorial['Estado'] ?? '') === 'NO_ENCONTRADO') {
            return RespuestaApi::error('Venta no encontrada', 404, [[
                'Codigo' => 'NEG_404',
                'Campo' => 'IdVenta',
                'Detalle' => 'No existe la venta solicitada'
            ]]);
        }

        return RespuestaApi::exito('Historial de venta', $laHistorial['Datos'] ?? [], 200, $laHistorial['Meta'] ?? []);
    }

    /**
     * @return array<string,mixed>
     */
    private function filtros(Request $toRequest): array
    {
        return [
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
    }

    private function validarPaginacion(Request $toRequest): ?JsonResponse
    {
        if ($toRequest->query('TamanoPagina') !== null && (int)$toRequest->query('TamanoPagina') > 200) {
            return RespuestaApi::error('Error de validacion', 422, [[
                'Codigo' => 'VAL_422',
                'Campo' => 'TamanoPagina',
                'Detalle' => 'TamanoPagina no puede ser mayor a 200'
            ]]);
        }

        return null;
    }
}


<?php

namespace App\Modulos\Tablero\Controllers;

use App\Http\Controllers\Controller;
use App\Modulos\Tablero\Services\TableroService;
use App\Soporte\RespuestaApi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TableroController extends Controller
{
    public function __construct(private TableroService $toTableroService)
    {
    }

    public function Resumen(): JsonResponse
    {
        return RespuestaApi::exito(
            'Resumen del tablero',
            $this->toTableroService->Resumen()
        );
    }

    public function EjecutivoResumen(Request $toRequest): JsonResponse
    {
        $laDatos = $this->toTableroService->ejecutivoResumen(
            (int)(auth()->id() ?? 0),
            $toRequest->filled('Empresa') ? (int)$toRequest->query('Empresa') : null,
            $toRequest->filled('FechaDesde') ? (string)$toRequest->query('FechaDesde') : null,
            $toRequest->filled('FechaHasta') ? (string)$toRequest->query('FechaHasta') : null
        );

        return RespuestaApi::exito('Resumen ejecutivo del tablero', $laDatos);
    }

    public function EjecutivoMaquinas(Request $toRequest): JsonResponse
    {
        $la = $this->toTableroService->ejecutivoMaquinas(
            (int)(auth()->id() ?? 0),
            $toRequest->filled('Empresa') ? (int)$toRequest->query('Empresa') : null,
            $toRequest->filled('Estado') ? (int)$toRequest->query('Estado') : null,
            $toRequest->filled('Busqueda') ? (string)$toRequest->query('Busqueda') : null,
            (int)$toRequest->query('Pagina', 1),
            (int)$toRequest->query('TamanoPagina', 20),
            $toRequest->filled('Orden') ? (string)$toRequest->query('Orden') : 'ingresos',
            $toRequest->filled('FechaDesde') ? (string)$toRequest->query('FechaDesde') : null,
            $toRequest->filled('FechaHasta') ? (string)$toRequest->query('FechaHasta') : null
        );

        return RespuestaApi::exito('Listado ejecutivo de maquinas', $la['Datos'], 200, $la['Meta']);
    }

    public function EjecutivoMapa(Request $toRequest): JsonResponse
    {
        $laDatos = $this->toTableroService->ejecutivoMapa(
            (int)(auth()->id() ?? 0),
            $toRequest->filled('Empresa') ? (int)$toRequest->query('Empresa') : null,
            $toRequest->filled('Estado') ? (int)$toRequest->query('Estado') : null,
            (int)$toRequest->query('SoloConCoordenadas', 1),
            $toRequest->filled('FechaDesde') ? (string)$toRequest->query('FechaDesde') : null,
            $toRequest->filled('FechaHasta') ? (string)$toRequest->query('FechaHasta') : null
        );

        return RespuestaApi::exito('Mapa ejecutivo del tablero', $laDatos);
    }

    public function EjecutivoRanking(Request $toRequest): JsonResponse
    {
        $laDatos = $this->toTableroService->ejecutivoRanking(
            (int)(auth()->id() ?? 0),
            $toRequest->filled('Empresa') ? (int)$toRequest->query('Empresa') : null,
            $toRequest->filled('FechaDesde') ? (string)$toRequest->query('FechaDesde') : null,
            $toRequest->filled('FechaHasta') ? (string)$toRequest->query('FechaHasta') : null,
            (int)$toRequest->query('Top', 10),
            $toRequest->filled('Por') ? (string)$toRequest->query('Por') : 'ingresos'
        );

        return RespuestaApi::exito('Ranking ejecutivo de maquinas', $laDatos);
    }

    public function EjecutivoDetalleMaquina(int $tnMaquina, Request $toRequest): JsonResponse
    {
        $la = $this->toTableroService->ejecutivoDetalleMaquina(
            (int)(auth()->id() ?? 0),
            $tnMaquina,
            $toRequest->filled('FechaDesde') ? (string)$toRequest->query('FechaDesde') : null,
            $toRequest->filled('FechaHasta') ? (string)$toRequest->query('FechaHasta') : null
        );

        if (($la['Estado'] ?? '') === 'NO_ENCONTRADO') {
            return RespuestaApi::error('Maquina no encontrada', 404);
        }
        if (($la['Estado'] ?? '') === 'NO_AUTORIZADO') {
            return RespuestaApi::error('No autorizado para consultar esta maquina', 403);
        }

        return RespuestaApi::exito('Detalle ejecutivo de maquina', $la['Datos'] ?? []);
    }
}

<?php

namespace App\Modulos\Aprobacion\Controllers;

use App\Http\Controllers\Controller;
use App\Modulos\Aprobacion\Services\AprobacionService;
use App\Soporte\RespuestaApi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AprobacionController extends Controller
{
    public function __construct(private AprobacionService $toService)
    {
    }

    public function Solicitar(Request $toRequest): JsonResponse
    {
        $toRequest->validate([
            'Entidad' => ['required', 'string', 'max:80'],
            'EntidadId' => ['nullable', 'string', 'max:80'],
            'AccionSolicitada' => ['required', 'string', 'max:30'],
            'DatosPropuestos' => ['nullable', 'array'],
            'Motivo' => ['nullable', 'string', 'max:255'],
        ]);

        $la = $this->toService->Solicitar($toRequest->all(), (int)(auth()->id() ?? 0));

        return RespuestaApi::exito('Aprobacion solicitada correctamente', $la, 201);
    }

    public function Aprobar(Request $toRequest, int $tnAprobacion): JsonResponse
    {
        $toRequest->validate([
            'Motivo' => ['nullable', 'string', 'max:255'],
        ]);

        $la = $this->toService->Aprobar(
            $tnAprobacion,
            (int)(auth()->id() ?? 0),
            $toRequest->filled('Motivo') ? (string)$toRequest->input('Motivo') : null
        );

        return $this->resolverMutacion($la, 'Aprobacion aprobada correctamente', 'Aprobacion ya aprobada');
    }

    public function Rechazar(Request $toRequest, int $tnAprobacion): JsonResponse
    {
        $toRequest->validate([
            'Motivo' => ['nullable', 'string', 'max:255'],
        ]);

        $la = $this->toService->Rechazar(
            $tnAprobacion,
            (int)(auth()->id() ?? 0),
            $toRequest->filled('Motivo') ? (string)$toRequest->input('Motivo') : null
        );

        return $this->resolverMutacion($la, 'Aprobacion rechazada correctamente', 'Aprobacion ya rechazada');
    }

    public function Listar(Request $toRequest): JsonResponse
    {
        $tnEstado = $toRequest->filled('Estado') ? (int)$toRequest->query('Estado') : null;
        $tcEntidad = $toRequest->filled('Entidad') ? (string)$toRequest->query('Entidad') : null;
        $tcFechaDesde = $toRequest->filled('FechaDesde') ? (string)$toRequest->query('FechaDesde') : null;
        $tcFechaHasta = $toRequest->filled('FechaHasta') ? (string)$toRequest->query('FechaHasta') : null;
        $tnPagina = (int)$toRequest->query('Pagina', 1);
        $tnTamanoPagina = (int)$toRequest->query('TamanoPagina', 20);

        $toPaginador = $this->toService->Listar($tnEstado, $tcEntidad, $tcFechaDesde, $tcFechaHasta, $tnPagina, $tnTamanoPagina);

        return RespuestaApi::paginado('Listado de aprobaciones', $toPaginador);
    }

    /** @param array<string,mixed> $la */
    private function resolverMutacion(array $la, string $tcMensajeOk, string $tcMensajeIdempotente): JsonResponse
    {
        if (($la['Estado'] ?? '') === 'NO_ENCONTRADO') {
            return RespuestaApi::error('Aprobacion no encontrada', 404);
        }

        if (($la['Estado'] ?? '') === 'TRANSICION_INVALIDA') {
            return RespuestaApi::error('Transicion de estado invalida', 409, [
                ['Codigo' => 'APROBACION_409', 'Campo' => 'Estado', 'Detalle' => 'La aprobacion ya esta cerrada']
            ], $la['Datos'] ?? []);
        }

        if (($la['Estado'] ?? '') === 'IDEMPOTENTE') {
            return RespuestaApi::exito($tcMensajeIdempotente, $la['Datos'] ?? []);
        }

        return RespuestaApi::exito($tcMensajeOk, $la['Datos'] ?? []);
    }
}

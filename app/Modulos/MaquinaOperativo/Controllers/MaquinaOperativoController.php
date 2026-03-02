<?php

namespace App\Modulos\MaquinaOperativo\Controllers;

use App\Http\Controllers\Controller;
use App\Modulos\MaquinaOperativo\Services\MaquinaOperativoService;
use App\Soporte\AutorizacionNegocioService;
use App\Soporte\RespuestaApi;
use App\Soporte\ValidacionAprobacionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MaquinaOperativoController extends Controller
{
    public function __construct(
        private MaquinaOperativoService $toService,
        private AutorizacionNegocioService $toAutorizacion,
        private ValidacionAprobacionService $toAprobacion
    ) {
    }

    public function ObtenerUbicacion(int $tnMaquina): JsonResponse
    {
        $tnUsuarioSesion = (int)(auth()->id() ?? 0);
        if (!$this->toAutorizacion->puedeAccederMaquina($tnUsuarioSesion, $tnMaquina)) {
            return RespuestaApi::error('No autorizado para consultar ubicacion de esta maquina', 403);
        }

        $la = $this->toService->obtenerUbicacion($tnMaquina);
        if (!$la) {
            return RespuestaApi::error('Maquina no encontrada', 404);
        }

        return RespuestaApi::exito('Ubicacion de maquina', $la);
    }

    public function ActualizarUbicacion(Request $toRequest, int $tnMaquina): JsonResponse
    {
        $toRequest->validate([
            'Ubicacion' => ['required', 'integer', 'min:1'],
            'TipoLugarInstalacion' => ['sometimes', 'nullable', 'integer', 'min:1', 'exists:mysqlNegocio.TIPOLUGARINSTALACION,TipoLugarInstalacion'],
            'Version' => ['required', 'string', 'max:30'],
            'Motivo' => ['nullable', 'string', 'max:255'],
        ]);

        $tnUsuarioSesion = (int)(auth()->id() ?? 0);
        if (!$this->toAutorizacion->puedeAccederMaquina($tnUsuarioSesion, $tnMaquina)) {
            return RespuestaApi::error('No autorizado para actualizar ubicacion de esta maquina', 403);
        }

        $la = $this->toService->actualizarUbicacion(
            $tnMaquina,
            (int)$toRequest->input('Ubicacion'),
            $toRequest->has('TipoLugarInstalacion')
                ? ($toRequest->filled('TipoLugarInstalacion') ? (int)$toRequest->input('TipoLugarInstalacion') : null)
                : null,
            (string)$toRequest->input('Version'),
            $tnUsuarioSesion,
            $toRequest->filled('Motivo') ? (string)$toRequest->input('Motivo') : null
        );

        if (($la['Estado'] ?? '') === 'NO_ENCONTRADO') {
            return RespuestaApi::error('Maquina no encontrada', 404);
        }
        if (($la['Estado'] ?? '') === 'CONFLICTO_VERSION') {
            return RespuestaApi::error('Conflicto de version', 409, [], $la['Actual'] ?? []);
        }

        return RespuestaApi::exito('Ubicacion actualizada correctamente', $la['Datos'] ?? []);
    }

    public function ObtenerEstadoOperativo(int $tnMaquina): JsonResponse
    {
        $tnUsuarioSesion = (int)(auth()->id() ?? 0);
        if (!$this->toAutorizacion->puedeAccederMaquina($tnUsuarioSesion, $tnMaquina)) {
            return RespuestaApi::error('No autorizado para consultar estado operativo', 403);
        }

        $la = $this->toService->obtenerEstadoOperativo($tnMaquina);
        return RespuestaApi::exito('Estado operativo de maquina', $la ?? []);
    }

    public function ActualizarEstadoOperativo(Request $toRequest, int $tnMaquina): JsonResponse
    {
        $toRequest->validate([
            'EstadoOperativo' => ['required', 'string', 'max:40'],
            'Aprobacion' => ['required', 'integer', 'min:1'],
            'Motivo' => ['nullable', 'string', 'max:255'],
        ]);

        $tnUsuarioSesion = (int)(auth()->id() ?? 0);
        if (!$this->toAutorizacion->puedeAccederMaquina($tnUsuarioSesion, $tnMaquina)) {
            return RespuestaApi::error('No autorizado para cambiar estado operativo', 403);
        }

        if (!$this->toAprobacion->estaAprobadaParaEntidad((int)$toRequest->input('Aprobacion'), 'MAQUINA', $tnMaquina)) {
            return RespuestaApi::error('Aprobacion invalida para cambio de estado operativo', 409);
        }

        $la = $this->toService->actualizarEstadoOperativo(
            $tnMaquina,
            (string)$toRequest->input('EstadoOperativo'),
            $tnUsuarioSesion,
            $toRequest->filled('Motivo') ? (string)$toRequest->input('Motivo') : null
        );

        if (($la['Estado'] ?? '') === 'ESTADO_INVALIDO') {
            return RespuestaApi::error('Estado operativo invalido', 400);
        }

        if (($la['Estado'] ?? '') === 'IDEMPOTENTE') {
            return RespuestaApi::exito('Estado operativo ya estaba aplicado', $la['Datos'] ?? []);
        }

        return RespuestaApi::exito('Estado operativo actualizado correctamente', $la['Datos'] ?? []);
    }

    public function HistorialEstadoOperativo(int $tnMaquina, Request $toRequest): JsonResponse
    {
        $tnPagina = (int)$toRequest->query('Pagina', 1);
        $tnTamanoPagina = (int)$toRequest->query('TamanoPagina', 20);

        $toPaginador = $this->toService->listarHistorial($tnMaquina, $tnPagina, $tnTamanoPagina);
        return RespuestaApi::paginado('Historial de estado operativo', $toPaginador);
    }
}

<?php

namespace App\Modulos\Anuncio\Controllers;

use App\Http\Controllers\Controller;
use App\Modulos\Anuncio\Services\AnuncioService;
use App\Soporte\AutorizacionNegocioService;
use App\Soporte\RespuestaApi;
use App\Soporte\ValidacionAprobacionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AnuncioController extends Controller
{
    public function __construct(
        private AnuncioService $toService,
        private AutorizacionNegocioService $toAutorizacion,
        private ValidacionAprobacionService $toAprobacion
    ) {
    }

    public function Listar(Request $toRequest): JsonResponse
    {
        $to = $this->toService->listar(
            $toRequest->filled('Empresa') ? (int)$toRequest->query('Empresa') : null,
            $toRequest->filled('Estado') ? (int)$toRequest->query('Estado') : null,
            (int)$toRequest->query('Pagina', 1),
            (int)$toRequest->query('TamanoPagina', 20)
        );

        return RespuestaApi::paginado('Listado de anuncios', $to);
    }

    public function Obtener(int $tnAnuncio): JsonResponse
    {
        $la = $this->toService->obtener($tnAnuncio);
        if (!$la) {
            return RespuestaApi::error('Anuncio no encontrado', 404);
        }
        return RespuestaApi::exito('Anuncio encontrado', $la);
    }

    public function Crear(Request $toRequest): JsonResponse
    {
        $toRequest->validate([
            'Empresa' => ['required', 'integer', 'min:1'],
            'AlcanceAnuncio' => ['required', 'integer', 'min:1'],
            'Titulo' => ['required', 'string', 'max:120'],
            'Descripcion' => ['nullable', 'string'],
            'RutaImagen' => ['nullable', 'string', 'max:255'],
            'FechaHoraInicio' => ['required', 'date'],
            'FechaHoraFin' => ['nullable', 'date'],
            'Prioridad' => ['nullable', 'integer', 'min:1'],
            'Estado' => ['nullable', 'integer', 'min:1'],
            'Motivo' => ['nullable', 'string', 'max:255'],
        ]);

        $tnUsuarioSesion = (int)(auth()->id() ?? 0);
        if (!$this->toAutorizacion->puedeGestionarEmpresa($tnUsuarioSesion, (int)$toRequest->input('Empresa')) && !$this->toAutorizacion->esDueno($tnUsuarioSesion)) {
            return RespuestaApi::error('No autorizado para crear anuncio en esta empresa', 403);
        }

        return RespuestaApi::exito('Anuncio creado correctamente', $this->toService->crear($toRequest->all(), $tnUsuarioSesion), 201);
    }

    public function Actualizar(Request $toRequest, int $tnAnuncio): JsonResponse
    {
        $toRequest->validate([
            'Version' => ['required', 'string', 'max:30'],
            'Empresa' => ['required', 'integer', 'min:1'],
            'AlcanceAnuncio' => ['required', 'integer', 'min:1'],
            'Titulo' => ['required', 'string', 'max:120'],
            'Descripcion' => ['nullable', 'string'],
            'RutaImagen' => ['nullable', 'string', 'max:255'],
            'FechaHoraInicio' => ['required', 'date'],
            'FechaHoraFin' => ['nullable', 'date'],
            'Prioridad' => ['required', 'integer', 'min:1'],
            'Estado' => ['required', 'integer', 'min:1'],
            'Motivo' => ['nullable', 'string', 'max:255'],
        ]);

        return $this->resolverMutacion($this->toService->actualizar($tnAnuncio, $toRequest->all(), (string)$toRequest->input('Version'), (int)(auth()->id() ?? 0), false), 'Anuncio actualizado correctamente');
    }

    public function ActualizarParcial(Request $toRequest, int $tnAnuncio): JsonResponse
    {
        $toRequest->validate([
            'Version' => ['required', 'string', 'max:30'],
            'Empresa' => ['sometimes', 'integer', 'min:1'],
            'AlcanceAnuncio' => ['sometimes', 'integer', 'min:1'],
            'Titulo' => ['sometimes', 'string', 'max:120'],
            'Descripcion' => ['sometimes', 'nullable', 'string'],
            'RutaImagen' => ['sometimes', 'nullable', 'string', 'max:255'],
            'FechaHoraInicio' => ['sometimes', 'date'],
            'FechaHoraFin' => ['sometimes', 'nullable', 'date'],
            'Prioridad' => ['sometimes', 'integer', 'min:1'],
            'Estado' => ['sometimes', 'integer', 'min:1'],
            'Motivo' => ['nullable', 'string', 'max:255'],
        ]);

        return $this->resolverMutacion($this->toService->actualizar($tnAnuncio, $toRequest->all(), (string)$toRequest->input('Version'), (int)(auth()->id() ?? 0), true), 'Anuncio actualizado correctamente');
    }

    public function Eliminar(Request $toRequest, int $tnAnuncio): JsonResponse
    {
        $toRequest->validate(['Version' => ['required', 'string', 'max:30'], 'Motivo' => ['nullable', 'string', 'max:255']]);

        return $this->resolverMutacion($this->toService->eliminarLogico($tnAnuncio, (string)$toRequest->input('Version'), (int)(auth()->id() ?? 0), $toRequest->input('Motivo')), 'Anuncio eliminado logicamente');
    }

    public function AsignarMaquina(Request $toRequest, int $tnAnuncio): JsonResponse
    {
        $toRequest->validate(['Maquina' => ['required', 'integer', 'min:1'], 'Motivo' => ['nullable', 'string', 'max:255']]);
        return RespuestaApi::exito('Maquina asignada al anuncio', $this->toService->asignarMaquina($tnAnuncio, (int)$toRequest->input('Maquina'), (int)(auth()->id() ?? 0), $toRequest->input('Motivo')));
    }

    public function QuitarMaquina(Request $toRequest, int $tnAnuncio, int $tnMaquina): JsonResponse
    {
        $toRequest->validate(['Motivo' => ['nullable', 'string', 'max:255']]);
        return $this->resolverMutacion($this->toService->quitarMaquina($tnAnuncio, $tnMaquina, (int)(auth()->id() ?? 0), $toRequest->input('Motivo')), 'Asignacion anuncio-maquina eliminada logicamente');
    }

    public function AsignarProducto(Request $toRequest, int $tnAnuncio): JsonResponse
    {
        $toRequest->validate(['Producto' => ['required', 'integer', 'min:1'], 'Motivo' => ['nullable', 'string', 'max:255']]);
        return RespuestaApi::exito('Producto asignado al anuncio', $this->toService->asignarProducto($tnAnuncio, (int)$toRequest->input('Producto'), (int)(auth()->id() ?? 0), $toRequest->input('Motivo')));
    }

    public function QuitarProducto(Request $toRequest, int $tnAnuncio, int $tnProducto): JsonResponse
    {
        $toRequest->validate(['Motivo' => ['nullable', 'string', 'max:255']]);
        return $this->resolverMutacion($this->toService->quitarProducto($tnAnuncio, $tnProducto, (int)(auth()->id() ?? 0), $toRequest->input('Motivo')), 'Asignacion anuncio-producto eliminada logicamente');
    }

    public function Publicar(Request $toRequest, int $tnAnuncio): JsonResponse
    {
        $toRequest->validate([
            'Aprobacion' => ['required', 'integer', 'min:1'],
            'Motivo' => ['nullable', 'string', 'max:255'],
        ]);

        if (!$this->toAprobacion->estaAprobadaParaEntidad((int)$toRequest->input('Aprobacion'), 'ANUNCIO', $tnAnuncio)) {
            return RespuestaApi::error('Aprobacion invalida para publicar anuncio', 409);
        }

        return $this->resolverMutacion($this->toService->publicar($tnAnuncio, (int)(auth()->id() ?? 0), $toRequest->input('Motivo')), 'Anuncio publicado correctamente', 'Anuncio ya estaba publicado');
    }

    public function Detener(Request $toRequest, int $tnAnuncio): JsonResponse
    {
        $toRequest->validate([
            'Aprobacion' => ['required', 'integer', 'min:1'],
            'Motivo' => ['nullable', 'string', 'max:255'],
        ]);

        if (!$this->toAprobacion->estaAprobadaParaEntidad((int)$toRequest->input('Aprobacion'), 'ANUNCIO', $tnAnuncio)) {
            return RespuestaApi::error('Aprobacion invalida para detener anuncio', 409);
        }

        return $this->resolverMutacion($this->toService->detener($tnAnuncio, (int)(auth()->id() ?? 0), $toRequest->input('Motivo')), 'Anuncio detenido correctamente', 'Anuncio ya estaba detenido');
    }

    public function Impacto(int $tnAnuncio): JsonResponse
    {
        $la = $this->toService->impacto($tnAnuncio);
        if (!$la) {
            return RespuestaApi::error('Anuncio no encontrado', 404);
        }
        return RespuestaApi::exito('Impacto del anuncio', $la);
    }

    /** @param array<string,mixed> $la */
    private function resolverMutacion(array $la, string $tcMensajeOk, string $tcMensajeIdempotente = 'Operacion ya aplicada'): JsonResponse
    {
        if (($la['Estado'] ?? '') === 'NO_ENCONTRADO') {
            return RespuestaApi::error('Registro no encontrado', 404);
        }
        if (($la['Estado'] ?? '') === 'CONFLICTO_VERSION') {
            return RespuestaApi::error('Conflicto de version', 409, [], $la['Actual'] ?? []);
        }
        if (($la['Estado'] ?? '') === 'IDEMPOTENTE') {
            return RespuestaApi::exito($tcMensajeIdempotente, $la['Datos'] ?? []);
        }

        return RespuestaApi::exito($tcMensajeOk, $la['Datos'] ?? []);
    }
}

<?php

namespace App\Modulos\Merma\Controllers;

use App\Http\Controllers\Controller;
use App\Modulos\Merma\Services\MermaService;
use App\Soporte\RespuestaApi;
use App\Soporte\ValidacionAprobacionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MermaController extends Controller
{
    public function __construct(
        private MermaService $toService,
        private ValidacionAprobacionService $toAprobacion
    ) {
    }

    public function Listar(Request $toRequest): JsonResponse
    {
        $to = $this->toService->listar(
            $toRequest->filled('Maquina') ? (int)$toRequest->query('Maquina') : null,
            $toRequest->filled('Estado') ? (int)$toRequest->query('Estado') : null,
            (int)$toRequest->query('Pagina', 1),
            (int)$toRequest->query('TamanoPagina', 20)
        );

        return RespuestaApi::paginado('Listado de mermas', $to);
    }

    public function Obtener(int $tnMerma): JsonResponse
    {
        $la = $this->toService->obtener($tnMerma);
        if (!$la) {
            return RespuestaApi::error('Merma no encontrada', 404);
        }
        return RespuestaApi::exito('Merma encontrada', $la);
    }

    public function Crear(Request $toRequest): JsonResponse
    {
        $toRequest->validate([
            'Maquina' => ['required', 'integer', 'min:1'],
            'UsuarioOperador' => ['required', 'integer', 'min:1'],
            'TipoMerma' => ['required', 'integer', 'min:1'],
            'FechaHora' => ['nullable', 'date'],
            'Observacion' => ['nullable', 'string', 'max:255'],
            'Estado' => ['nullable', 'integer', 'min:1'],
            'Motivo' => ['nullable', 'string', 'max:255'],
        ]);

        return RespuestaApi::exito('Merma creada correctamente', $this->toService->crear($toRequest->all(), (int)(auth()->id() ?? 0)), 201);
    }

    public function Actualizar(Request $toRequest, int $tnMerma): JsonResponse
    {
        $toRequest->validate([
            'Version' => ['required', 'string', 'max:30'],
            'Maquina' => ['required', 'integer', 'min:1'],
            'UsuarioOperador' => ['required', 'integer', 'min:1'],
            'TipoMerma' => ['required', 'integer', 'min:1'],
            'FechaHora' => ['required', 'date'],
            'Observacion' => ['nullable', 'string', 'max:255'],
            'Estado' => ['required', 'integer', 'min:1'],
            'Motivo' => ['nullable', 'string', 'max:255'],
        ]);

        return $this->resolverMutacion($this->toService->actualizar($tnMerma, $toRequest->all(), (string)$toRequest->input('Version'), (int)(auth()->id() ?? 0), false), 'Merma actualizada correctamente');
    }

    public function ActualizarParcial(Request $toRequest, int $tnMerma): JsonResponse
    {
        $toRequest->validate([
            'Version' => ['required', 'string', 'max:30'],
            'Maquina' => ['sometimes', 'integer', 'min:1'],
            'UsuarioOperador' => ['sometimes', 'integer', 'min:1'],
            'TipoMerma' => ['sometimes', 'integer', 'min:1'],
            'FechaHora' => ['sometimes', 'date'],
            'Observacion' => ['sometimes', 'nullable', 'string', 'max:255'],
            'Estado' => ['sometimes', 'integer', 'min:1'],
            'Motivo' => ['nullable', 'string', 'max:255'],
        ]);

        return $this->resolverMutacion($this->toService->actualizar($tnMerma, $toRequest->all(), (string)$toRequest->input('Version'), (int)(auth()->id() ?? 0), true), 'Merma actualizada correctamente');
    }

    public function Eliminar(Request $toRequest, int $tnMerma): JsonResponse
    {
        $toRequest->validate(['Version' => ['required', 'string', 'max:30'], 'Motivo' => ['nullable', 'string', 'max:255']]);
        return $this->resolverMutacion($this->toService->eliminarLogico($tnMerma, (string)$toRequest->input('Version'), (int)(auth()->id() ?? 0), $toRequest->input('Motivo')), 'Merma eliminada logicamente');
    }

    public function AgregarDetalle(Request $toRequest, int $tnMerma): JsonResponse
    {
        $toRequest->validate([
            'Celda' => ['required', 'integer', 'min:1'],
            'ProductoEmpresa' => ['required', 'integer', 'min:1'],
            'Lote' => ['nullable', 'integer', 'min:1'],
            'CantidadRetirada' => ['required', 'integer', 'min:1'],
            'Motivo' => ['nullable', 'string', 'max:255'],
        ]);

        $la = $this->toService->agregarDetalle($tnMerma, $toRequest->all(), (int)(auth()->id() ?? 0), $toRequest->input('Motivo'));
        return RespuestaApi::exito('Detalle de merma agregado', $la, 201);
    }

    public function QuitarDetalle(Request $toRequest, int $tnMerma, int $tnMermaDetalle): JsonResponse
    {
        $toRequest->validate(['Motivo' => ['nullable', 'string', 'max:255']]);
        return $this->resolverMutacion($this->toService->quitarDetalle($tnMerma, $tnMermaDetalle, (int)(auth()->id() ?? 0), $toRequest->input('Motivo')), 'Detalle de merma eliminado logicamente');
    }

    public function SubirEvidencia(Request $toRequest, int $tnMerma): JsonResponse
    {
        $toRequest->validate([
            'Archivo' => ['required', 'file', 'max:8192'],
            'Motivo' => ['nullable', 'string', 'max:255'],
        ]);

        $la = $this->toService->subirEvidencia($tnMerma, $toRequest->file('Archivo'), (int)(auth()->id() ?? 0), $toRequest->input('Motivo'));
        return RespuestaApi::exito('Evidencia subida correctamente', $la, 201);
    }

    public function ListarEvidencias(int $tnMerma): JsonResponse
    {
        return RespuestaApi::exito('Evidencias de merma', $this->toService->listarEvidencias($tnMerma));
    }

    public function Aprobar(Request $toRequest, int $tnMerma): JsonResponse
    {
        $toRequest->validate([
            'Aprobacion' => ['required', 'integer', 'min:1'],
            'Motivo' => ['nullable', 'string', 'max:255'],
        ]);

        if (!$this->toAprobacion->estaAprobadaParaEntidad((int)$toRequest->input('Aprobacion'), 'MERMA', $tnMerma)) {
            return RespuestaApi::error('Aprobacion invalida para merma', 409);
        }

        return $this->resolverMutacion($this->toService->aprobar($tnMerma, (int)(auth()->id() ?? 0), $toRequest->input('Motivo')), 'Merma aprobada correctamente', 'Merma ya aprobada');
    }

    public function Rechazar(Request $toRequest, int $tnMerma): JsonResponse
    {
        $toRequest->validate([
            'Aprobacion' => ['required', 'integer', 'min:1'],
            'Motivo' => ['nullable', 'string', 'max:255'],
        ]);

        if (!$this->toAprobacion->estaAprobadaParaEntidad((int)$toRequest->input('Aprobacion'), 'MERMA', $tnMerma)) {
            return RespuestaApi::error('Aprobacion invalida para merma', 409);
        }

        return $this->resolverMutacion($this->toService->rechazar($tnMerma, (int)(auth()->id() ?? 0), $toRequest->input('Motivo')), 'Merma rechazada correctamente', 'Merma ya rechazada');
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
        if (($la['Estado'] ?? '') === 'TRANSICION_INVALIDA') {
            return RespuestaApi::error('Transicion de estado invalida', 409, [], $la['Datos'] ?? []);
        }
        if (($la['Estado'] ?? '') === 'SIN_DETALLE') {
            return RespuestaApi::error('La merma no tiene detalle para procesar', 409);
        }
        if (($la['Estado'] ?? '') === 'STOCK_INSUFICIENTE') {
            return RespuestaApi::error('Stock insuficiente para aprobar merma', 409, [], ['Detalle' => $la['Detalle'] ?? null]);
        }
        if (($la['Estado'] ?? '') === 'EXISTENCIA_NO_ENCONTRADA') {
            return RespuestaApi::error('No existe stock asociado al detalle de merma', 409, [], ['Detalle' => $la['Detalle'] ?? null]);
        }

        return RespuestaApi::exito($tcMensajeOk, $la['Datos'] ?? []);
    }
}

<?php

namespace App\Modulos\Lote\Controllers;

use App\Http\Controllers\Controller;
use App\Modulos\Lote\Services\LoteService;
use App\Soporte\RespuestaApi;
use App\Soporte\ValidacionAprobacionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LoteController extends Controller
{
    public function __construct(private LoteService $toService)
    {
    }

    public function Listar(Request $toRequest): JsonResponse
    {
        $tnProducto = $toRequest->filled('Producto') ? (int)$toRequest->query('Producto') : null;
        $tnEstado = $toRequest->filled('Estado') ? (int)$toRequest->query('Estado') : null;
        $tcBusqueda = $toRequest->filled('Busqueda') ? (string)$toRequest->query('Busqueda') : null;
        $tnPagina = (int)$toRequest->query('Pagina', 1);
        $tnTamanoPagina = (int)$toRequest->query('TamanoPagina', 20);

        $toPaginador = $this->toService->Listar($tnProducto, $tnEstado, $tcBusqueda, $tnPagina, $tnTamanoPagina);

        return RespuestaApi::paginado('Listado de lotes', $toPaginador);
    }

    public function Obtener(int $tnLote): JsonResponse
    {
        $la = $this->toService->Obtener($tnLote);
        if (!$la) {
            return RespuestaApi::error('Lote no encontrado', 404);
        }

        return RespuestaApi::exito('Lote encontrado', $la);
    }

    public function Crear(Request $toRequest): JsonResponse
    {
        $toRequest->validate([
            'Producto' => ['required', 'integer', 'min:1'],
            'CodigoLote' => ['nullable', 'string', 'max:60'],
            'FechaVencimiento' => ['nullable', 'date'],
            'FechaRegistro' => ['nullable', 'date'],
            'CantidadInicial' => ['required', 'integer', 'min:0'],
            'Estado' => ['nullable', 'integer', 'min:1'],
            'Motivo' => ['nullable', 'string', 'max:255'],
        ]);

        $la = $this->toService->Crear($toRequest->all(), (int)(auth()->id() ?? 0));

        return RespuestaApi::exito('Lote creado correctamente', $la, 201);
    }

    public function Actualizar(Request $toRequest, int $tnLote): JsonResponse
    {
        $toRequest->validate([
            'Version' => ['required', 'string', 'max:30'],
            'Producto' => ['required', 'integer', 'min:1'],
            'CodigoLote' => ['nullable', 'string', 'max:60'],
            'FechaVencimiento' => ['nullable', 'date'],
            'FechaRegistro' => ['required', 'date'],
            'CantidadInicial' => ['required', 'integer', 'min:0'],
            'Estado' => ['required', 'integer', 'min:1'],
            'Motivo' => ['nullable', 'string', 'max:255'],
            'Aprobacion' => ['required', 'integer', 'min:1'],
        ]);

        $toError = $this->validarAprobacionCantidad($toRequest, $tnLote);
        if ($toError !== null) {
            return $toError;
        }

        return $this->resolverActualizacion($toRequest, $tnLote, false);
    }

    public function ActualizarParcial(Request $toRequest, int $tnLote): JsonResponse
    {
        $toRequest->validate([
            'Version' => ['required', 'string', 'max:30'],
            'Producto' => ['sometimes', 'integer', 'min:1'],
            'CodigoLote' => ['sometimes', 'nullable', 'string', 'max:60'],
            'FechaVencimiento' => ['sometimes', 'nullable', 'date'],
            'FechaRegistro' => ['sometimes', 'date'],
            'CantidadInicial' => ['sometimes', 'integer', 'min:0'],
            'Estado' => ['sometimes', 'integer', 'min:1'],
            'Motivo' => ['nullable', 'string', 'max:255'],
            'Aprobacion' => ['sometimes', 'integer', 'min:1'],
        ]);

        $toError = $this->validarAprobacionCantidad($toRequest, $tnLote);
        if ($toError !== null) {
            return $toError;
        }

        return $this->resolverActualizacion($toRequest, $tnLote, true);
    }

    public function EliminarLogico(Request $toRequest, int $tnLote): JsonResponse
    {
        $toRequest->validate([
            'Version' => ['required', 'string', 'max:30'],
            'Motivo' => ['nullable', 'string', 'max:255'],
        ]);

        $la = $this->toService->EliminarLogico(
            $tnLote,
            (string)$toRequest->input('Version'),
            (int)(auth()->id() ?? 0),
            $toRequest->filled('Motivo') ? (string)$toRequest->input('Motivo') : null
        );

        return $this->resolverResultadoMutacion($la, 'Lote eliminado logicamente');
    }

    private function resolverActualizacion(Request $toRequest, int $tnLote, bool $lbParcial): JsonResponse
    {
        $la = $this->toService->Actualizar(
            $tnLote,
            $toRequest->all(),
            (string)$toRequest->input('Version'),
            (int)(auth()->id() ?? 0),
            $lbParcial
        );

        return $this->resolverResultadoMutacion($la, 'Lote actualizado correctamente');
    }

    /** @param array<string,mixed> $la */
    private function resolverResultadoMutacion(array $la, string $tcMensaje): JsonResponse
    {
        if (($la['Estado'] ?? '') === 'NO_ENCONTRADO') {
            return RespuestaApi::error('Lote no encontrado', 404);
        }
        if (($la['Estado'] ?? '') === 'CONFLICTO_VERSION') {
            return RespuestaApi::error('Conflicto de version', 409, [
                ['Codigo' => 'VERSION_409', 'Campo' => 'Version', 'Detalle' => 'La version enviada no coincide']
            ], $la['Actual'] ?? []);
        }

        return RespuestaApi::exito($tcMensaje, $la['Datos'] ?? []);
    }

    private function validarAprobacionCantidad(Request $toRequest, int $tnLote): ?JsonResponse
    {
        if (!$toRequest->has('CantidadInicial')) {
            return null;
        }

        if (!$toRequest->filled('Aprobacion')) {
            return RespuestaApi::error('Se requiere aprobacion para cambiar cantidad del lote', 409, [
                ['Codigo' => 'APROBACION_REQUERIDA', 'Campo' => 'Aprobacion', 'Detalle' => 'Debe enviar una aprobacion previa']
            ]);
        }

        /** @var ValidacionAprobacionService $toValidador */
        $toValidador = app(ValidacionAprobacionService::class);
        $lbOk = $toValidador->estaAprobadaParaEntidad((int)$toRequest->input('Aprobacion'), 'LOTE', $tnLote);
        if (!$lbOk) {
            return RespuestaApi::error('Aprobacion invalida para lote', 409, [
                ['Codigo' => 'APROBACION_INVALIDA', 'Campo' => 'Aprobacion', 'Detalle' => 'La aprobacion no corresponde a esta entidad']
            ]);
        }

        return null;
    }
}

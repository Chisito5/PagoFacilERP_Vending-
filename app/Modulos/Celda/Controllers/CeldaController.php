<?php

namespace App\Modulos\Celda\Controllers;

use App\Http\Controllers\Controller;
use App\Modulos\Celda\Services\CeldaService;
use App\Soporte\RespuestaApi;
use App\Soporte\ValidacionAprobacionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CeldaController extends Controller
{
    public function __construct(private CeldaService $toService)
    {
    }

    public function Listar(Request $toRequest): JsonResponse
    {
        $tnMaquina = $toRequest->filled('Maquina') ? (int)$toRequest->query('Maquina') : null;
        $tnEstado = $toRequest->filled('Estado') ? (int)$toRequest->query('Estado') : null;
        $tcBusqueda = $toRequest->filled('Busqueda') ? (string)$toRequest->query('Busqueda') : null;
        $tnPagina = (int)$toRequest->query('Pagina', 1);
        $tnTamanoPagina = (int)$toRequest->query('TamanoPagina', 20);

        $toPaginador = $this->toService->Listar($tnMaquina, $tnEstado, $tcBusqueda, $tnPagina, $tnTamanoPagina);

        return RespuestaApi::paginado('Listado de celdas', $toPaginador);
    }

    public function Obtener(int $tnCelda): JsonResponse
    {
        $la = $this->toService->Obtener($tnCelda);
        if (!$la) {
            return RespuestaApi::error('Celda no encontrada', 404);
        }

        return RespuestaApi::exito('Celda encontrada', $la);
    }

    public function Crear(Request $toRequest): JsonResponse
    {
        $toRequest->validate([
            'Maquina' => ['required', 'integer', 'min:1'],
            'CodigoSeleccion' => ['required', 'string', 'max:10'],
            'Fila' => ['nullable', 'integer', 'min:0'],
            'Columna' => ['nullable', 'integer', 'min:0'],
            'CapacidadMaxima' => ['required', 'integer', 'min:1'],
            'Estado' => ['nullable', 'integer', 'min:1'],
            'ProductoEmpresa' => ['nullable', 'integer', 'min:1'],
            'Lote' => ['nullable', 'integer', 'min:1'],
            'CantidadDisponible' => ['nullable', 'integer', 'min:0'],
            'CantidadReservada' => ['nullable', 'integer', 'min:0'],
            'Motivo' => ['nullable', 'string', 'max:255'],
        ]);

        $la = $this->toService->Crear($toRequest->all(), (int)(auth()->id() ?? 0));

        return RespuestaApi::exito('Celda creada correctamente', $la, 201);
    }

    public function Actualizar(Request $toRequest, int $tnCelda): JsonResponse
    {
        $toRequest->validate([
            'Version' => ['required', 'string', 'max:30'],
            'Maquina' => ['required', 'integer', 'min:1'],
            'CodigoSeleccion' => ['required', 'string', 'max:10'],
            'Fila' => ['nullable', 'integer', 'min:0'],
            'Columna' => ['nullable', 'integer', 'min:0'],
            'CapacidadMaxima' => ['required', 'integer', 'min:1'],
            'Estado' => ['required', 'integer', 'min:1'],
            'Motivo' => ['nullable', 'string', 'max:255'],
            'Aprobacion' => ['required', 'integer', 'min:1'],
        ]);

        $toError = $this->validarAprobacionCapacidad($toRequest, $tnCelda);
        if ($toError !== null) {
            return $toError;
        }

        return $this->resolverActualizacion($toRequest, $tnCelda, false);
    }

    public function ActualizarParcial(Request $toRequest, int $tnCelda): JsonResponse
    {
        $toRequest->validate([
            'Version' => ['required', 'string', 'max:30'],
            'Maquina' => ['sometimes', 'integer', 'min:1'],
            'CodigoSeleccion' => ['sometimes', 'string', 'max:10'],
            'Fila' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'Columna' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'CapacidadMaxima' => ['sometimes', 'integer', 'min:1'],
            'Estado' => ['sometimes', 'integer', 'min:1'],
            'Motivo' => ['nullable', 'string', 'max:255'],
            'Aprobacion' => ['sometimes', 'integer', 'min:1'],
        ]);

        $toError = $this->validarAprobacionCapacidad($toRequest, $tnCelda);
        if ($toError !== null) {
            return $toError;
        }

        return $this->resolverActualizacion($toRequest, $tnCelda, true);
    }

    public function EliminarLogico(Request $toRequest, int $tnCelda): JsonResponse
    {
        $toRequest->validate([
            'Version' => ['required', 'string', 'max:30'],
            'Motivo' => ['nullable', 'string', 'max:255'],
        ]);

        $la = $this->toService->EliminarLogico(
            $tnCelda,
            (string)$toRequest->input('Version'),
            (int)(auth()->id() ?? 0),
            $toRequest->filled('Motivo') ? (string)$toRequest->input('Motivo') : null
        );

        return $this->resolverResultadoMutacion($la, 'Celda eliminada logicamente');
    }

    private function resolverActualizacion(Request $toRequest, int $tnCelda, bool $lbParcial): JsonResponse
    {
        $la = $this->toService->Actualizar(
            $tnCelda,
            $toRequest->all(),
            (string)$toRequest->input('Version'),
            (int)(auth()->id() ?? 0),
            $lbParcial
        );

        return $this->resolverResultadoMutacion($la, 'Celda actualizada correctamente');
    }

    /** @param array<string,mixed> $la */
    private function resolverResultadoMutacion(array $la, string $tcMensaje): JsonResponse
    {
        if (($la['Estado'] ?? '') === 'NO_ENCONTRADO') {
            return RespuestaApi::error('Celda no encontrada', 404);
        }
        if (($la['Estado'] ?? '') === 'CONFLICTO_VERSION') {
            return RespuestaApi::error('Conflicto de version', 409, [
                ['Codigo' => 'VERSION_409', 'Campo' => 'Version', 'Detalle' => 'La version enviada no coincide']
            ], $la['Actual'] ?? []);
        }

        return RespuestaApi::exito($tcMensaje, $la['Datos'] ?? []);
    }

    private function validarAprobacionCapacidad(Request $toRequest, int $tnCelda): ?JsonResponse
    {
        if (!$toRequest->has('CapacidadMaxima')) {
            return null;
        }

        if (!$toRequest->filled('Aprobacion')) {
            return RespuestaApi::error('Se requiere aprobacion para cambiar capacidad de celda', 409, [
                ['Codigo' => 'APROBACION_REQUERIDA', 'Campo' => 'Aprobacion', 'Detalle' => 'Debe enviar una aprobacion previa']
            ]);
        }

        /** @var ValidacionAprobacionService $toValidador */
        $toValidador = app(ValidacionAprobacionService::class);
        $lbOk = $toValidador->estaAprobadaParaEntidad((int)$toRequest->input('Aprobacion'), 'CELDA', $tnCelda);
        if (!$lbOk) {
            return RespuestaApi::error('Aprobacion invalida para celda', 409, [
                ['Codigo' => 'APROBACION_INVALIDA', 'Campo' => 'Aprobacion', 'Detalle' => 'La aprobacion no corresponde a esta entidad']
            ]);
        }

        return null;
    }
}

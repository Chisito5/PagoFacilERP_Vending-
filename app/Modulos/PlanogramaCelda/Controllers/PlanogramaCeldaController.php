<?php

namespace App\Modulos\PlanogramaCelda\Controllers;

use App\Http\Controllers\Controller;
use App\Modulos\PlanogramaCelda\Services\PlanogramaCeldaService;
use App\Soporte\ValidacionAprobacionService;
use App\Soporte\RespuestaApi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlanogramaCeldaController extends Controller
{
    public function __construct(private PlanogramaCeldaService $poPlanogramaCeldaService)
    {
    }

    public function Listar(Request $toRequest): JsonResponse
    {
        $tnPlanograma = $toRequest->filled('Planograma') ? (int)$toRequest->query('Planograma') : null;
        $tnCelda = $toRequest->filled('Celda') ? (int)$toRequest->query('Celda') : null;
        $tnEstado = $toRequest->filled('Estado') ? (int)$toRequest->query('Estado') : null;
        $tnPagina = (int)$toRequest->query('Pagina', 1);
        $tnTamanoPagina = (int)$toRequest->query('TamanoPagina', 20);

        $toPaginador = $this->poPlanogramaCeldaService->Listar($tnPlanograma, $tnCelda, $tnEstado, $tnPagina, $tnTamanoPagina);

        return RespuestaApi::paginado('Listado de planograma celda', $toPaginador);
    }

    public function Obtener(int $tnPlanogramaCelda): JsonResponse
    {
        $la = $this->poPlanogramaCeldaService->Obtener($tnPlanogramaCelda);
        if (!$la) {
            return RespuestaApi::error('PlanogramaCelda no encontrado', 404);
        }

        return RespuestaApi::exito('PlanogramaCelda encontrado', $la);
    }

    public function Crear(Request $toRequest): JsonResponse
    {
        $toRequest->validate([
            'Planograma' => ['required', 'integer', 'min:1'],
            'Celda' => ['required', 'integer', 'min:1'],
            'ProductoEmpresa' => ['nullable', 'integer', 'min:1'],
            'PrecioVenta' => ['nullable', 'numeric', 'min:0'],
            'StockMinimo' => ['nullable', 'integer', 'min:0'],
            'StockMaximo' => ['nullable', 'integer', 'min:0'],
            'PlanogramaCeldaPrincipal' => ['nullable', 'integer', 'min:1'],
            'Estado' => ['nullable', 'integer', 'min:1'],
            'Motivo' => ['nullable', 'string', 'max:255'],
        ]);

        $la = $this->poPlanogramaCeldaService->Crear($toRequest->all(), (int)(auth()->id() ?? 0));
        return RespuestaApi::exito('PlanogramaCelda creado correctamente', $la, 201);
    }

    public function Actualizar(Request $toRequest, int $tnPlanogramaCelda): JsonResponse
    {
        $toRequest->validate([
            'Version' => ['required', 'string', 'max:30'],
            'Planograma' => ['required', 'integer', 'min:1'],
            'Celda' => ['required', 'integer', 'min:1'],
            'ProductoEmpresa' => ['nullable', 'integer', 'min:1'],
            'PrecioVenta' => ['nullable', 'numeric', 'min:0'],
            'StockMinimo' => ['required', 'integer', 'min:0'],
            'StockMaximo' => ['required', 'integer', 'min:0'],
            'PlanogramaCeldaPrincipal' => ['nullable', 'integer', 'min:1'],
            'Estado' => ['required', 'integer', 'min:1'],
            'Motivo' => ['nullable', 'string', 'max:255'],
            'Aprobacion' => ['sometimes', 'integer', 'min:1'],
        ]);

        $toError = $this->validarAprobacionPrecio($toRequest, $tnPlanogramaCelda);
        if ($toError !== null) {
            return $toError;
        }

        return $this->resolverActualizacion($toRequest, $tnPlanogramaCelda, false);
    }

    public function ActualizarParcial(Request $toRequest, int $tnPlanogramaCelda): JsonResponse
    {
        $toRequest->validate([
            'Version' => ['required', 'string', 'max:30'],
            'Planograma' => ['sometimes', 'integer', 'min:1'],
            'Celda' => ['sometimes', 'integer', 'min:1'],
            'ProductoEmpresa' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'PrecioVenta' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'StockMinimo' => ['sometimes', 'integer', 'min:0'],
            'StockMaximo' => ['sometimes', 'integer', 'min:0'],
            'PlanogramaCeldaPrincipal' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'Estado' => ['sometimes', 'integer', 'min:1'],
            'Motivo' => ['nullable', 'string', 'max:255'],
            'Aprobacion' => ['sometimes', 'integer', 'min:1'],
        ]);

        $toError = $this->validarAprobacionPrecio($toRequest, $tnPlanogramaCelda);
        if ($toError !== null) {
            return $toError;
        }

        return $this->resolverActualizacion($toRequest, $tnPlanogramaCelda, true);
    }

    public function EliminarLogico(Request $toRequest, int $tnPlanogramaCelda): JsonResponse
    {
        $toRequest->validate([
            'Version' => ['required', 'string', 'max:30'],
            'Motivo' => ['nullable', 'string', 'max:255'],
        ]);

        $la = $this->poPlanogramaCeldaService->EliminarLogico(
            $tnPlanogramaCelda,
            (string)$toRequest->input('Version'),
            (int)(auth()->id() ?? 0),
            $toRequest->filled('Motivo') ? (string)$toRequest->input('Motivo') : null
        );

        return $this->resolverResultadoMutacion($la, 'PlanogramaCelda eliminado logicamente');
    }

    public function ActualizarPrecio(Request $toRequest, int $tnPlanogramaCelda): JsonResponse
    {
        $toRequest->validate([
            'Version' => ['required', 'string', 'max:30'],
            'PrecioVenta' => ['required', 'numeric', 'min:0'],
            'Motivo' => ['nullable', 'string', 'max:255'],
            'Aprobacion' => ['required', 'integer', 'min:1'],
        ]);

        $toError = $this->validarAprobacionPrecio($toRequest, $tnPlanogramaCelda);
        if ($toError !== null) {
            return $toError;
        }

        $la = $this->poPlanogramaCeldaService->ActualizarPrecio(
            $tnPlanogramaCelda,
            (float)$toRequest->input('PrecioVenta'),
            (string)$toRequest->input('Version'),
            (int)(auth()->id() ?? 0),
            $toRequest->filled('Motivo') ? (string)$toRequest->input('Motivo') : null
        );

        return $this->resolverResultadoMutacion($la, 'Precio actualizado correctamente');
    }

    private function resolverActualizacion(Request $toRequest, int $tnPlanogramaCelda, bool $lbParcial): JsonResponse
    {
        $la = $this->poPlanogramaCeldaService->Actualizar(
            $tnPlanogramaCelda,
            $toRequest->all(),
            (string)$toRequest->input('Version'),
            (int)(auth()->id() ?? 0),
            $lbParcial
        );

        return $this->resolverResultadoMutacion($la, 'PlanogramaCelda actualizado correctamente');
    }

    /** @param array<string,mixed> $la */
    private function resolverResultadoMutacion(array $la, string $tcMensajeOk): JsonResponse
    {
        if (($la['Estado'] ?? '') === 'NO_ENCONTRADO') {
            return RespuestaApi::error('PlanogramaCelda no encontrado', 404);
        }

        if (($la['Estado'] ?? '') === 'CONFLICTO_VERSION') {
            return RespuestaApi::error('Conflicto de version', 409, [
                ['Codigo' => 'VERSION_409', 'Campo' => 'Version', 'Detalle' => 'La version enviada no coincide']
            ], $la['Actual'] ?? []);
        }

        return RespuestaApi::exito($tcMensajeOk, $la['Datos'] ?? []);
    }

    private function validarAprobacionPrecio(Request $toRequest, int $tnPlanogramaCelda): ?JsonResponse
    {
        if (!$toRequest->has('PrecioVenta')) {
            return null;
        }

        if (!$toRequest->filled('Aprobacion')) {
            return RespuestaApi::error('Se requiere aprobacion para cambiar precio', 409, [
                ['Codigo' => 'APROBACION_REQUERIDA', 'Campo' => 'Aprobacion', 'Detalle' => 'Debe enviar una aprobacion previa']
            ]);
        }

        /** @var ValidacionAprobacionService $toValidador */
        $toValidador = app(ValidacionAprobacionService::class);
        $lbOk = $toValidador->estaAprobadaParaEntidad((int)$toRequest->input('Aprobacion'), 'PLANOGRAMACELDA', $tnPlanogramaCelda);
        if (!$lbOk) {
            return RespuestaApi::error('Aprobacion invalida para planograma celda', 409, [
                ['Codigo' => 'APROBACION_INVALIDA', 'Campo' => 'Aprobacion', 'Detalle' => 'La aprobacion no corresponde a esta entidad']
            ]);
        }

        return null;
    }
}

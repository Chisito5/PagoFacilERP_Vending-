<?php

namespace App\Modulos\Empresa\Controllers;

use App\Http\Controllers\Controller;
use App\Modulos\Empresa\Services\EmpresaService;
use App\Soporte\RespuestaApi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmpresaController extends Controller
{
    public function __construct(private EmpresaService $poEmpresaService)
    {
    }

    public function Listar(Request $toRequest): JsonResponse
    {
        $tnEstado = $toRequest->filled('Estado') ? (int)$toRequest->query('Estado') : null;
        $tcBusqueda = $toRequest->filled('Busqueda') ? (string)$toRequest->query('Busqueda') : null;
        $tnPagina = (int)$toRequest->query('Pagina', 1);
        $tnTamanoPagina = (int)$toRequest->query('TamanoPagina', 20);

        $toPaginador = $this->poEmpresaService->Listar($tnEstado, $tcBusqueda, $tnPagina, $tnTamanoPagina);

        return RespuestaApi::paginado('Listado de empresas', $toPaginador);
    }

    public function Obtener(int $tnEmpresa): JsonResponse
    {
        $laEmpresa = $this->poEmpresaService->Obtener($tnEmpresa);
        if (!$laEmpresa) {
            return RespuestaApi::error('Empresa no encontrada', 404, [
                ['Codigo' => 'EMP_404', 'Campo' => 'Empresa', 'Detalle' => 'No existe la empresa solicitada']
            ]);
        }

        return RespuestaApi::exito('Empresa encontrada', $laEmpresa);
    }

    public function Crear(Request $toRequest): JsonResponse
    {
        $toRequest->validate([
            'CodigoEmpresa' => ['required', 'string', 'max:30'],
            'RazonSocial' => ['required', 'string', 'max:150'],
            'NombreComercial' => ['nullable', 'string', 'max:150'],
            'Nit' => ['nullable', 'string', 'max:30'],
            'Telefono' => ['nullable', 'string', 'max:30'],
            'Correo' => ['nullable', 'string', 'email', 'max:120'],
            'DireccionFiscal' => ['nullable', 'string', 'max:255'],
            'TipoEmpresa' => ['required', 'integer', 'min:1'],
            'Estado' => ['nullable', 'integer', 'min:1'],
            'PlantillaVisualPredeterminada' => ['nullable', 'integer', 'min:1'],
            'Motivo' => ['nullable', 'string', 'max:255'],
        ]);

        $tnUsuario = (int)(auth()->id() ?? 0);
        $laEmpresa = $this->poEmpresaService->Crear($toRequest->all(), $tnUsuario);

        return RespuestaApi::exito('Empresa creada correctamente', $laEmpresa, 201);
    }

    public function Actualizar(Request $toRequest, int $tnEmpresa): JsonResponse
    {
        $toRequest->validate([
            'Version' => ['required', 'string', 'max:30'],
            'CodigoEmpresa' => ['required', 'string', 'max:30'],
            'RazonSocial' => ['required', 'string', 'max:150'],
            'NombreComercial' => ['nullable', 'string', 'max:150'],
            'Nit' => ['nullable', 'string', 'max:30'],
            'Telefono' => ['nullable', 'string', 'max:30'],
            'Correo' => ['nullable', 'string', 'email', 'max:120'],
            'DireccionFiscal' => ['nullable', 'string', 'max:255'],
            'TipoEmpresa' => ['required', 'integer', 'min:1'],
            'Estado' => ['required', 'integer', 'min:1'],
            'PlantillaVisualPredeterminada' => ['nullable', 'integer', 'min:1'],
            'Motivo' => ['nullable', 'string', 'max:255'],
        ]);

        return $this->resolverActualizacion($toRequest, $tnEmpresa, false);
    }

    public function ActualizarParcial(Request $toRequest, int $tnEmpresa): JsonResponse
    {
        $toRequest->validate([
            'Version' => ['required', 'string', 'max:30'],
            'CodigoEmpresa' => ['sometimes', 'string', 'max:30'],
            'RazonSocial' => ['sometimes', 'string', 'max:150'],
            'NombreComercial' => ['sometimes', 'nullable', 'string', 'max:150'],
            'Nit' => ['sometimes', 'nullable', 'string', 'max:30'],
            'Telefono' => ['sometimes', 'nullable', 'string', 'max:30'],
            'Correo' => ['sometimes', 'nullable', 'string', 'email', 'max:120'],
            'DireccionFiscal' => ['sometimes', 'nullable', 'string', 'max:255'],
            'TipoEmpresa' => ['sometimes', 'integer', 'min:1'],
            'Estado' => ['sometimes', 'integer', 'min:1'],
            'PlantillaVisualPredeterminada' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'Motivo' => ['nullable', 'string', 'max:255'],
        ]);

        return $this->resolverActualizacion($toRequest, $tnEmpresa, true);
    }

    public function EliminarLogico(Request $toRequest, int $tnEmpresa): JsonResponse
    {
        $toRequest->validate([
            'Version' => ['required', 'string', 'max:30'],
            'Motivo' => ['nullable', 'string', 'max:255'],
        ]);

        $tnUsuario = (int)(auth()->id() ?? 0);
        $laResultado = $this->poEmpresaService->EliminarLogico(
            $tnEmpresa,
            (string)$toRequest->input('Version'),
            $tnUsuario,
            $toRequest->filled('Motivo') ? (string)$toRequest->input('Motivo') : null
        );

        if ($laResultado['Estado'] === 'NO_ENCONTRADO') {
            return RespuestaApi::error('Empresa no encontrada', 404);
        }

        if ($laResultado['Estado'] === 'CONFLICTO_VERSION') {
            return RespuestaApi::error('Conflicto de version', 409, [
                ['Codigo' => 'VERSION_409', 'Campo' => 'Version', 'Detalle' => 'La version enviada no coincide']
            ], $laResultado['Actual'] ?? []);
        }

        return RespuestaApi::exito('Empresa eliminada logicamente', $laResultado['Datos']);
    }

    private function resolverActualizacion(Request $toRequest, int $tnEmpresa, bool $lbParcial): JsonResponse
    {
        $tnUsuario = (int)(auth()->id() ?? 0);
        $laResultado = $this->poEmpresaService->Actualizar(
            $tnEmpresa,
            $toRequest->all(),
            (string)$toRequest->input('Version'),
            $tnUsuario,
            $lbParcial
        );

        if ($laResultado['Estado'] === 'NO_ENCONTRADO') {
            return RespuestaApi::error('Empresa no encontrada', 404);
        }

        if ($laResultado['Estado'] === 'CONFLICTO_VERSION') {
            return RespuestaApi::error('Conflicto de version', 409, [
                ['Codigo' => 'VERSION_409', 'Campo' => 'Version', 'Detalle' => 'La version enviada no coincide']
            ], $laResultado['Actual'] ?? []);
        }

        return RespuestaApi::exito('Empresa actualizada correctamente', $laResultado['Datos']);
    }
}

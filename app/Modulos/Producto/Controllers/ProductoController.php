<?php

namespace App\Modulos\Producto\Controllers;

use App\Http\Controllers\Controller;
use App\Modulos\Producto\Services\ProductoService;
use App\Soporte\RespuestaApi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductoController extends Controller
{
    public function __construct(private ProductoService $poProductoService)
    {
    }

    public function Listar(Request $toRequest): JsonResponse
    {
        $tnEmpresa = $toRequest->filled('Empresa') ? (int)$toRequest->query('Empresa') : null;
        $tnEstado = $toRequest->filled('Estado') ? (int)$toRequest->query('Estado') : null;
        $tcBusqueda = $toRequest->filled('Busqueda') ? (string)$toRequest->query('Busqueda') : null;
        $tnPagina = (int)$toRequest->query('Pagina', 1);
        $tnTamanoPagina = (int)$toRequest->query('TamanoPagina', 20);

        $toPaginador = $this->poProductoService->Listar($tnEmpresa, $tnEstado, $tcBusqueda, $tnPagina, $tnTamanoPagina);

        return RespuestaApi::paginado('Listado de productos', $toPaginador);
    }

    public function Obtener(int $tnProducto): JsonResponse
    {
        $la = $this->poProductoService->Obtener($tnProducto);
        if (!$la) {
            return RespuestaApi::error('Producto no encontrado', 404);
        }

        return RespuestaApi::exito('Producto encontrado', $la);
    }

    public function Crear(Request $toRequest): JsonResponse
    {
        $this->normalizarPayloadProducto($toRequest);

        $toRequest->validate([
            'Empresa' => ['nullable', 'integer', 'min:1'],
            'CodigoSku' => ['required_without:CodigoProducto', 'nullable', 'string', 'max:60'],
            'CodigoProducto' => ['required_without:CodigoSku', 'nullable', 'string', 'max:60'],
            'CodigoBarra' => ['nullable', 'string', 'max:60'],
            'NombreProducto' => ['required', 'string', 'max:200'],
            'Precio' => ['nullable', 'numeric', 'min:0'],
            'Descripcion' => ['nullable', 'string'],
            'Marca' => ['nullable', 'string', 'max:120'],
            'ContenidoCantidad' => ['nullable', 'numeric', 'min:0'],
            'UnidadMedidaContenido' => ['nullable', 'integer', 'min:1'],
            'PesoGramos' => ['nullable', 'numeric', 'min:0'],
            'PesoGr' => ['nullable', 'numeric', 'min:0'],
            'AnchoMm' => ['nullable', 'integer', 'min:0'],
            'AltoMm' => ['nullable', 'integer', 'min:0'],
            'ProfundidadMm' => ['nullable', 'integer', 'min:0'],
            'Orientacion' => ['nullable', 'string', 'max:20'],
            'PermiteGiro' => ['nullable', 'boolean'],
            'UnidadEmpaque' => ['nullable', 'string', 'max:40'],
            'SubgrupoProducto' => ['nullable', 'integer', 'min:1'],
            'Estado' => ['nullable', 'integer', 'min:1'],
            'Motivo' => ['nullable', 'string', 'max:255'],
        ]);

        $la = $this->poProductoService->Crear($toRequest->all(), (int)(auth()->id() ?? 0));

        return RespuestaApi::exito('Producto creado correctamente', $la, 201);
    }

    public function Actualizar(Request $toRequest, int $tnProducto): JsonResponse
    {
        $this->normalizarPayloadProducto($toRequest);

        $toRequest->validate([
            'Version' => ['required', 'string', 'max:30'],
            'Empresa' => ['nullable', 'integer', 'min:1'],
            'CodigoSku' => ['required_without:CodigoProducto', 'nullable', 'string', 'max:60'],
            'CodigoProducto' => ['required_without:CodigoSku', 'nullable', 'string', 'max:60'],
            'CodigoBarra' => ['nullable', 'string', 'max:60'],
            'NombreProducto' => ['required', 'string', 'max:200'],
            'Precio' => ['nullable', 'numeric', 'min:0'],
            'Descripcion' => ['nullable', 'string'],
            'Marca' => ['nullable', 'string', 'max:120'],
            'ContenidoCantidad' => ['nullable', 'numeric', 'min:0'],
            'UnidadMedidaContenido' => ['nullable', 'integer', 'min:1'],
            'PesoGramos' => ['nullable', 'numeric', 'min:0'],
            'PesoGr' => ['nullable', 'numeric', 'min:0'],
            'AnchoMm' => ['nullable', 'integer', 'min:0'],
            'AltoMm' => ['nullable', 'integer', 'min:0'],
            'ProfundidadMm' => ['nullable', 'integer', 'min:0'],
            'Orientacion' => ['nullable', 'string', 'max:20'],
            'PermiteGiro' => ['nullable', 'boolean'],
            'UnidadEmpaque' => ['nullable', 'string', 'max:40'],
            'SubgrupoProducto' => ['nullable', 'integer', 'min:1'],
            'Estado' => ['required', 'integer', 'min:1'],
            'Motivo' => ['nullable', 'string', 'max:255'],
        ]);

        return $this->resolverActualizacion($toRequest, $tnProducto, false);
    }

    public function ActualizarParcial(Request $toRequest, int $tnProducto): JsonResponse
    {
        $this->normalizarPayloadProducto($toRequest);

        $toRequest->validate([
            'Version' => ['required', 'string', 'max:30'],
            'Empresa' => ['sometimes', 'integer', 'min:1'],
            'CodigoSku' => ['sometimes', 'string', 'max:60'],
            'CodigoProducto' => ['sometimes', 'string', 'max:60'],
            'CodigoBarra' => ['sometimes', 'nullable', 'string', 'max:60'],
            'NombreProducto' => ['sometimes', 'string', 'max:200'],
            'Precio' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'Descripcion' => ['sometimes', 'nullable', 'string'],
            'Marca' => ['sometimes', 'nullable', 'string', 'max:120'],
            'ContenidoCantidad' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'UnidadMedidaContenido' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'PesoGramos' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'PesoGr' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'AnchoMm' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'AltoMm' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'ProfundidadMm' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'Orientacion' => ['sometimes', 'nullable', 'string', 'max:20'],
            'PermiteGiro' => ['sometimes', 'nullable', 'boolean'],
            'UnidadEmpaque' => ['sometimes', 'nullable', 'string', 'max:40'],
            'SubgrupoProducto' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'Estado' => ['sometimes', 'integer', 'min:1'],
            'Motivo' => ['nullable', 'string', 'max:255'],
        ]);

        return $this->resolverActualizacion($toRequest, $tnProducto, true);
    }

    public function EliminarLogico(Request $toRequest, int $tnProducto): JsonResponse
    {
        $toRequest->validate([
            'Version' => ['required', 'string', 'max:30'],
            'Motivo' => ['nullable', 'string', 'max:255'],
        ]);

        $la = $this->poProductoService->EliminarLogico(
            $tnProducto,
            (string)$toRequest->input('Version'),
            (int)(auth()->id() ?? 0),
            $toRequest->filled('Motivo') ? (string)$toRequest->input('Motivo') : null
        );

        if ($la['Estado'] === 'NO_ENCONTRADO') {
            return RespuestaApi::error('Producto no encontrado', 404);
        }

        if ($la['Estado'] === 'CONFLICTO_VERSION') {
            return RespuestaApi::error('Conflicto de version', 409, [
                ['Codigo' => 'VERSION_409', 'Campo' => 'Version', 'Detalle' => 'La version enviada no coincide']
            ], $la['Actual'] ?? []);
        }

        return RespuestaApi::exito('Producto eliminado logicamente', $la['Datos']);
    }

    private function resolverActualizacion(Request $toRequest, int $tnProducto, bool $lbParcial): JsonResponse
    {
        $la = $this->poProductoService->Actualizar(
            $tnProducto,
            $toRequest->all(),
            (string)$toRequest->input('Version'),
            (int)(auth()->id() ?? 0),
            $lbParcial
        );

        if ($la['Estado'] === 'NO_ENCONTRADO') {
            return RespuestaApi::error('Producto no encontrado', 404);
        }

        if ($la['Estado'] === 'CONFLICTO_VERSION') {
            return RespuestaApi::error('Conflicto de version', 409, [
                ['Codigo' => 'VERSION_409', 'Campo' => 'Version', 'Detalle' => 'La version enviada no coincide']
            ], $la['Actual'] ?? []);
        }

        return RespuestaApi::exito('Producto actualizado correctamente', $la['Datos']);
    }

    private function normalizarPayloadProducto(Request $toRequest): void
    {
        $la = $toRequest->all();
        if (!array_key_exists('Empresa', $la) || (int)($la['Empresa'] ?? 0) <= 0) {
            $tnEmpresaSesion = (int)(auth()->user()->Empresa ?? 0);
            if ($tnEmpresaSesion > 0) {
                $la['Empresa'] = $tnEmpresaSesion;
            }
        }

        if (!isset($la['CodigoSku']) && isset($la['CodigoProducto'])) {
            $la['CodigoSku'] = $la['CodigoProducto'];
        }
        if (!isset($la['CodigoProducto']) && isset($la['CodigoSku'])) {
            $la['CodigoProducto'] = $la['CodigoSku'];
        }

        if (!isset($la['PesoGramos']) && isset($la['PesoGr'])) {
            $la['PesoGramos'] = $la['PesoGr'];
        }
        if (!isset($la['PesoGr']) && isset($la['PesoGramos'])) {
            $la['PesoGr'] = $la['PesoGramos'];
        }

        $toRequest->merge($la);
    }
}

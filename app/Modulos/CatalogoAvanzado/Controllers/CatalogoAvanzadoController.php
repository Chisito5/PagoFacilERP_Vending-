<?php

namespace App\Modulos\CatalogoAvanzado\Controllers;

use App\Http\Controllers\Controller;
use App\Modulos\CatalogoAvanzado\Services\CatalogoAvanzadoService;
use App\Soporte\RespuestaApi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CatalogoAvanzadoController extends Controller
{
    public function __construct(private CatalogoAvanzadoService $toService)
    {
    }

    public function ListarFamilias(Request $toRequest): JsonResponse
    {
        $to = $this->toService->listarFamilias(
            $toRequest->filled('Empresa') ? (int)$toRequest->query('Empresa') : null,
            $toRequest->filled('Estado') ? (int)$toRequest->query('Estado') : null,
            (int)$toRequest->query('Pagina', 1),
            (int)$toRequest->query('TamanoPagina', 20)
        );
        return RespuestaApi::paginado('Listado de familias de producto', $to);
    }

    public function ObtenerFamilia(int $tnId): JsonResponse
    {
        $la = $this->toService->obtenerFamilia($tnId);
        if (!$la) {
            return RespuestaApi::error('Familia de producto no encontrada', 404);
        }
        return RespuestaApi::exito('Familia de producto encontrada', $la);
    }

    public function CrearFamilia(Request $toRequest): JsonResponse
    {
        $toRequest->validate([
            'Empresa' => ['required', 'integer', 'min:1'],
            'NombreFamiliaProducto' => ['required', 'string', 'max:120'],
            'Descripcion' => ['nullable', 'string', 'max:255'],
            'Estado' => ['nullable', 'integer', 'min:1'],
            'Motivo' => ['nullable', 'string', 'max:255'],
        ]);

        $la = $this->toService->crearFamilia($toRequest->all(), (int)(auth()->id() ?? 0));
        return RespuestaApi::exito('Familia de producto creada correctamente', $la, 201);
    }

    public function ActualizarFamilia(Request $toRequest, int $tnId): JsonResponse
    {
        $toRequest->validate([
            'Version' => ['required', 'string', 'max:30'],
            'Empresa' => ['required', 'integer', 'min:1'],
            'NombreFamiliaProducto' => ['required', 'string', 'max:120'],
            'Descripcion' => ['nullable', 'string', 'max:255'],
            'Estado' => ['required', 'integer', 'min:1'],
            'Motivo' => ['nullable', 'string', 'max:255'],
        ]);
        return $this->resolverMutacion($this->toService->actualizarFamilia($tnId, $toRequest->all(), (string)$toRequest->input('Version'), (int)(auth()->id() ?? 0), false), 'Familia de producto actualizada correctamente');
    }

    public function ActualizarFamiliaParcial(Request $toRequest, int $tnId): JsonResponse
    {
        $toRequest->validate([
            'Version' => ['required', 'string', 'max:30'],
            'Empresa' => ['sometimes', 'integer', 'min:1'],
            'NombreFamiliaProducto' => ['sometimes', 'string', 'max:120'],
            'Descripcion' => ['sometimes', 'nullable', 'string', 'max:255'],
            'Estado' => ['sometimes', 'integer', 'min:1'],
            'Motivo' => ['nullable', 'string', 'max:255'],
        ]);
        return $this->resolverMutacion($this->toService->actualizarFamilia($tnId, $toRequest->all(), (string)$toRequest->input('Version'), (int)(auth()->id() ?? 0), true), 'Familia de producto actualizada correctamente');
    }

    public function EliminarFamilia(Request $toRequest, int $tnId): JsonResponse
    {
        $toRequest->validate(['Version' => ['required', 'string', 'max:30'], 'Motivo' => ['nullable', 'string', 'max:255']]);
        return $this->resolverMutacion($this->toService->eliminarFamilia($tnId, (string)$toRequest->input('Version'), (int)(auth()->id() ?? 0), $toRequest->input('Motivo')), 'Familia de producto eliminada logicamente');
    }

    public function ListarGrupos(Request $toRequest): JsonResponse
    {
        $to = $this->toService->listarGrupos(
            $toRequest->filled('FamiliaProducto') ? (int)$toRequest->query('FamiliaProducto') : null,
            $toRequest->filled('Estado') ? (int)$toRequest->query('Estado') : null,
            (int)$toRequest->query('Pagina', 1),
            (int)$toRequest->query('TamanoPagina', 20)
        );
        return RespuestaApi::paginado('Listado de grupos de producto', $to);
    }

    public function ObtenerGrupo(int $tnId): JsonResponse
    {
        $la = $this->toService->obtenerGrupo($tnId);
        if (!$la) {
            return RespuestaApi::error('Grupo de producto no encontrado', 404);
        }
        return RespuestaApi::exito('Grupo de producto encontrado', $la);
    }

    public function CrearGrupo(Request $toRequest): JsonResponse
    {
        $toRequest->validate([
            'FamiliaProducto' => ['required', 'integer', 'min:1'],
            'NombreGrupoProducto' => ['required', 'string', 'max:120'],
            'Descripcion' => ['nullable', 'string', 'max:255'],
            'Estado' => ['nullable', 'integer', 'min:1'],
            'Motivo' => ['nullable', 'string', 'max:255'],
        ]);

        return RespuestaApi::exito('Grupo de producto creado correctamente', $this->toService->crearGrupo($toRequest->all(), (int)(auth()->id() ?? 0)), 201);
    }

    public function ActualizarGrupo(Request $toRequest, int $tnId): JsonResponse
    {
        $toRequest->validate([
            'Version' => ['required', 'string', 'max:30'],
            'FamiliaProducto' => ['required', 'integer', 'min:1'],
            'NombreGrupoProducto' => ['required', 'string', 'max:120'],
            'Descripcion' => ['nullable', 'string', 'max:255'],
            'Estado' => ['required', 'integer', 'min:1'],
            'Motivo' => ['nullable', 'string', 'max:255'],
        ]);
        return $this->resolverMutacion($this->toService->actualizarGrupo($tnId, $toRequest->all(), (string)$toRequest->input('Version'), (int)(auth()->id() ?? 0), false), 'Grupo de producto actualizado correctamente');
    }

    public function ActualizarGrupoParcial(Request $toRequest, int $tnId): JsonResponse
    {
        $toRequest->validate([
            'Version' => ['required', 'string', 'max:30'],
            'FamiliaProducto' => ['sometimes', 'integer', 'min:1'],
            'NombreGrupoProducto' => ['sometimes', 'string', 'max:120'],
            'Descripcion' => ['sometimes', 'nullable', 'string', 'max:255'],
            'Estado' => ['sometimes', 'integer', 'min:1'],
            'Motivo' => ['nullable', 'string', 'max:255'],
        ]);
        return $this->resolverMutacion($this->toService->actualizarGrupo($tnId, $toRequest->all(), (string)$toRequest->input('Version'), (int)(auth()->id() ?? 0), true), 'Grupo de producto actualizado correctamente');
    }

    public function EliminarGrupo(Request $toRequest, int $tnId): JsonResponse
    {
        $toRequest->validate(['Version' => ['required', 'string', 'max:30'], 'Motivo' => ['nullable', 'string', 'max:255']]);
        return $this->resolverMutacion($this->toService->eliminarGrupo($tnId, (string)$toRequest->input('Version'), (int)(auth()->id() ?? 0), $toRequest->input('Motivo')), 'Grupo de producto eliminado logicamente');
    }

    public function ListarSubgrupos(Request $toRequest): JsonResponse
    {
        $to = $this->toService->listarSubgrupos(
            $toRequest->filled('GrupoProducto') ? (int)$toRequest->query('GrupoProducto') : null,
            $toRequest->filled('Estado') ? (int)$toRequest->query('Estado') : null,
            (int)$toRequest->query('Pagina', 1),
            (int)$toRequest->query('TamanoPagina', 20)
        );
        return RespuestaApi::paginado('Listado de subgrupos de producto', $to);
    }

    public function ObtenerSubgrupo(int $tnId): JsonResponse
    {
        $la = $this->toService->obtenerSubgrupo($tnId);
        if (!$la) {
            return RespuestaApi::error('Subgrupo de producto no encontrado', 404);
        }
        return RespuestaApi::exito('Subgrupo de producto encontrado', $la);
    }

    public function CrearSubgrupo(Request $toRequest): JsonResponse
    {
        $toRequest->validate([
            'GrupoProducto' => ['required', 'integer', 'min:1'],
            'NombreSubgrupoProducto' => ['required', 'string', 'max:120'],
            'Descripcion' => ['nullable', 'string', 'max:255'],
            'Estado' => ['nullable', 'integer', 'min:1'],
            'Motivo' => ['nullable', 'string', 'max:255'],
        ]);

        return RespuestaApi::exito('Subgrupo de producto creado correctamente', $this->toService->crearSubgrupo($toRequest->all(), (int)(auth()->id() ?? 0)), 201);
    }

    public function ActualizarSubgrupo(Request $toRequest, int $tnId): JsonResponse
    {
        $toRequest->validate([
            'Version' => ['required', 'string', 'max:30'],
            'GrupoProducto' => ['required', 'integer', 'min:1'],
            'NombreSubgrupoProducto' => ['required', 'string', 'max:120'],
            'Descripcion' => ['nullable', 'string', 'max:255'],
            'Estado' => ['required', 'integer', 'min:1'],
            'Motivo' => ['nullable', 'string', 'max:255'],
        ]);
        return $this->resolverMutacion($this->toService->actualizarSubgrupo($tnId, $toRequest->all(), (string)$toRequest->input('Version'), (int)(auth()->id() ?? 0), false), 'Subgrupo de producto actualizado correctamente');
    }

    public function ActualizarSubgrupoParcial(Request $toRequest, int $tnId): JsonResponse
    {
        $toRequest->validate([
            'Version' => ['required', 'string', 'max:30'],
            'GrupoProducto' => ['sometimes', 'integer', 'min:1'],
            'NombreSubgrupoProducto' => ['sometimes', 'string', 'max:120'],
            'Descripcion' => ['sometimes', 'nullable', 'string', 'max:255'],
            'Estado' => ['sometimes', 'integer', 'min:1'],
            'Motivo' => ['nullable', 'string', 'max:255'],
        ]);
        return $this->resolverMutacion($this->toService->actualizarSubgrupo($tnId, $toRequest->all(), (string)$toRequest->input('Version'), (int)(auth()->id() ?? 0), true), 'Subgrupo de producto actualizado correctamente');
    }

    public function EliminarSubgrupo(Request $toRequest, int $tnId): JsonResponse
    {
        $toRequest->validate(['Version' => ['required', 'string', 'max:30'], 'Motivo' => ['nullable', 'string', 'max:255']]);
        return $this->resolverMutacion($this->toService->eliminarSubgrupo($tnId, (string)$toRequest->input('Version'), (int)(auth()->id() ?? 0), $toRequest->input('Motivo')), 'Subgrupo de producto eliminado logicamente');
    }

    public function ListarImagenes(Request $toRequest): JsonResponse
    {
        $to = $this->toService->listarImagenes(
            $toRequest->filled('Producto') ? (int)$toRequest->query('Producto') : null,
            $toRequest->filled('Estado') ? (int)$toRequest->query('Estado') : null,
            (int)$toRequest->query('Pagina', 1),
            (int)$toRequest->query('TamanoPagina', 20)
        );
        return RespuestaApi::paginado('Listado de imagenes de producto', $to);
    }

    public function ObtenerImagen(int $tnId): JsonResponse
    {
        $la = $this->toService->obtenerImagen($tnId);
        if (!$la) {
            return RespuestaApi::error('Imagen de producto no encontrada', 404);
        }
        return RespuestaApi::exito('Imagen de producto encontrada', $la);
    }

    public function CrearImagen(Request $toRequest): JsonResponse
    {
        $toRequest->validate([
            'Producto' => ['required', 'integer', 'min:1'],
            'TipoImagen' => ['required_without:TipoImagenNombre', 'integer', 'min:1'],
            'TipoImagenNombre' => ['required_without:TipoImagen', 'string', 'max:40'],
            'RutaImagen' => ['required', 'string', 'max:255'],
            'Orden' => ['nullable', 'integer', 'min:1'],
            'Estado' => ['nullable', 'integer', 'min:1'],
            'Motivo' => ['nullable', 'string', 'max:255'],
        ]);

        $tnTipoImagen = $toRequest->filled('TipoImagen') ? (int)$toRequest->input('TipoImagen') : $this->resolverTipoImagenPorNombre((string)$toRequest->input('TipoImagenNombre'));
        if ($tnTipoImagen <= 0) {
            return RespuestaApi::error('Tipo de imagen invalido', 400);
        }

        $la = $toRequest->all();
        $la['TipoImagen'] = $tnTipoImagen;

        return RespuestaApi::exito('Imagen de producto creada correctamente', $this->toService->crearImagen($la, (int)(auth()->id() ?? 0)), 201);
    }

    public function ActualizarImagen(Request $toRequest, int $tnId): JsonResponse
    {
        $toRequest->validate([
            'Version' => ['required', 'string', 'max:30'],
            'Producto' => ['required', 'integer', 'min:1'],
            'TipoImagen' => ['sometimes', 'integer', 'min:1'],
            'TipoImagenNombre' => ['sometimes', 'string', 'max:40'],
            'RutaImagen' => ['required', 'string', 'max:255'],
            'Orden' => ['required', 'integer', 'min:1'],
            'Estado' => ['required', 'integer', 'min:1'],
            'Motivo' => ['nullable', 'string', 'max:255'],
        ]);

        $laDatos = $toRequest->all();
        if ($toRequest->filled('TipoImagenNombre')) {
            $tnTipoImagen = $this->resolverTipoImagenPorNombre((string)$toRequest->input('TipoImagenNombre'));
            if ($tnTipoImagen <= 0) {
                return RespuestaApi::error('Tipo de imagen invalido', 400);
            }
            $laDatos['TipoImagen'] = $tnTipoImagen;
        }

        return $this->resolverMutacion($this->toService->actualizarImagen($tnId, $laDatos, (string)$toRequest->input('Version'), (int)(auth()->id() ?? 0), false), 'Imagen de producto actualizada correctamente');
    }

    public function ActualizarImagenParcial(Request $toRequest, int $tnId): JsonResponse
    {
        $toRequest->validate([
            'Version' => ['required', 'string', 'max:30'],
            'Producto' => ['sometimes', 'integer', 'min:1'],
            'TipoImagen' => ['sometimes', 'integer', 'min:1'],
            'TipoImagenNombre' => ['sometimes', 'string', 'max:40'],
            'RutaImagen' => ['sometimes', 'string', 'max:255'],
            'Orden' => ['sometimes', 'integer', 'min:1'],
            'Estado' => ['sometimes', 'integer', 'min:1'],
            'Motivo' => ['nullable', 'string', 'max:255'],
        ]);

        $laDatos = $toRequest->all();
        if ($toRequest->filled('TipoImagenNombre')) {
            $tnTipoImagen = $this->resolverTipoImagenPorNombre((string)$toRequest->input('TipoImagenNombre'));
            if ($tnTipoImagen <= 0) {
                return RespuestaApi::error('Tipo de imagen invalido', 400);
            }
            $laDatos['TipoImagen'] = $tnTipoImagen;
        }

        return $this->resolverMutacion($this->toService->actualizarImagen($tnId, $laDatos, (string)$toRequest->input('Version'), (int)(auth()->id() ?? 0), true), 'Imagen de producto actualizada correctamente');
    }

    public function EliminarImagen(Request $toRequest, int $tnId): JsonResponse
    {
        $toRequest->validate(['Version' => ['required', 'string', 'max:30'], 'Motivo' => ['nullable', 'string', 'max:255']]);
        return $this->resolverMutacion($this->toService->eliminarImagen($tnId, (string)$toRequest->input('Version'), (int)(auth()->id() ?? 0), $toRequest->input('Motivo')), 'Imagen de producto eliminada logicamente');
    }

    public function SubirImagenProducto(Request $toRequest, int $tnProducto): JsonResponse
    {
        $toRequest->validate([
            'Imagen' => ['required', 'file', 'max:5120'],
            'TipoImagen' => ['required_without:TipoImagenNombre', 'integer', 'min:1'],
            'TipoImagenNombre' => ['required_without:TipoImagen', 'string', 'max:40'],
            'Motivo' => ['nullable', 'string', 'max:255'],
        ]);

        $tnTipoImagen = $toRequest->filled('TipoImagen') ? (int)$toRequest->input('TipoImagen') : $this->resolverTipoImagenPorNombre((string)$toRequest->input('TipoImagenNombre'));
        if ($tnTipoImagen <= 0) {
            return RespuestaApi::error('Tipo de imagen invalido', 400);
        }

        $la = $this->toService->subirImagenProducto(
            $tnProducto,
            $tnTipoImagen,
            $toRequest->file('Imagen'),
            (int)(auth()->id() ?? 0),
            $toRequest->filled('Motivo') ? (string)$toRequest->input('Motivo') : null
        );

        return RespuestaApi::exito('Imagen subida correctamente', $la, 201);
    }

    public function EliminarImagenProducto(Request $toRequest, int $tnProducto, int $tnProductoImagen): JsonResponse
    {
        $toRequest->validate([
            'Version' => ['required', 'string', 'max:30'],
            'Motivo' => ['nullable', 'string', 'max:255'],
        ]);

        $la = $this->toService->eliminarImagenProducto(
            $tnProducto,
            $tnProductoImagen,
            (string)$toRequest->input('Version'),
            (int)(auth()->id() ?? 0),
            $toRequest->filled('Motivo') ? (string)$toRequest->input('Motivo') : null
        );

        if (($la['Estado'] ?? '') === 'NO_CORRESPONDE') {
            return RespuestaApi::error('La imagen no pertenece al producto enviado', 409);
        }

        return $this->resolverMutacion($la, 'Imagen de producto eliminada logicamente');
    }

    /** @param array<string,mixed> $la */
    private function resolverMutacion(array $la, string $tcMensajeOk): JsonResponse
    {
        if (($la['Estado'] ?? '') === 'NO_ENCONTRADO') {
            return RespuestaApi::error('Registro no encontrado', 404);
        }
        if (($la['Estado'] ?? '') === 'CONFLICTO_VERSION') {
            return RespuestaApi::error('Conflicto de version', 409, [], $la['Actual'] ?? []);
        }
        return RespuestaApi::exito($tcMensajeOk, $la['Datos'] ?? []);
    }

    private function resolverTipoImagenPorNombre(string $tcNombre): int
    {
        return (int)($this->toService->obtenerTipoImagenPorNombre($tcNombre) ?? 0);
    }
}

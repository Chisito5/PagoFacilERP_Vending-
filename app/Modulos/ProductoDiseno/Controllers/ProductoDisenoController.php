<?php

namespace App\Modulos\ProductoDiseno\Controllers;

use App\Http\Controllers\Controller;
use App\Modulos\ProductoDiseno\Services\ProductoDisenoService;
use App\Soporte\RespuestaApi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductoDisenoController extends Controller
{
    /**
     * SYSCOOP
     * category: Controller
     * package: App\Modulos\ProductoDiseno\Controllers
     * author: Vladimir Meriles Velasquez
     * fecha: 01-03-2026
     * param: ProductoDisenoService $toService
     * return: void
     */
    public function __construct(private ProductoDisenoService $toService)
    {
    }

    /**
     * SYSCOOP
     * category: Controller
     * package: App\Modulos\ProductoDiseno\Controllers
     * author: Vladimir Meriles Velasquez
     * fecha: 01-03-2026
     * param: int $tnProducto
     * return: JsonResponse
     */
    public function ObtenerDiseno(int $tnProducto): JsonResponse
    {
        $la = $this->toService->ObtenerDiseno($tnProducto);
        if ($la['Estado'] === 'NO_ENCONTRADO') {
            return RespuestaApi::error('Producto no encontrado', 404);
        }

        return RespuestaApi::exito('Diseno de producto obtenido correctamente', $la['Datos'] ?? []);
    }

    /**
     * SYSCOOP
     * category: Controller
     * package: App\Modulos\ProductoDiseno\Controllers
     * author: Vladimir Meriles Velasquez
     * fecha: 01-03-2026
     * param: int $tnProducto, Request $toRequest
     * return: JsonResponse
     */
    public function CrearDiseno(int $tnProducto, Request $toRequest): JsonResponse
    {
        $toRequest->validate([
            'Lienzo' => ['nullable', 'array'],
            'Capas' => ['nullable', 'array'],
            'Motivo' => ['nullable', 'string', 'max:255'],
        ]);

        $la = $this->toService->CrearDiseno($tnProducto, $toRequest->all(), (int)(auth()->id() ?? 0));
        if ($la['Estado'] === 'NO_ENCONTRADO') {
            return RespuestaApi::error('Producto no encontrado', 404);
        }
        if ($la['Estado'] === 'YA_EXISTE') {
            return RespuestaApi::error('El producto ya tiene diseno activo', 409, [
                ['Codigo' => 'DISENO_409', 'Campo' => 'Producto', 'Detalle' => 'Ya existe diseno activo para el producto']
            ], ['Version' => $la['Version'] ?? null]);
        }

        return RespuestaApi::exito('Diseno de producto creado correctamente', $la['Datos'] ?? [], 201);
    }

    /**
     * SYSCOOP
     * category: Controller
     * package: App\Modulos\ProductoDiseno\Controllers
     * author: Vladimir Meriles Velasquez
     * fecha: 01-03-2026
     * param: int $tnProducto, Request $toRequest
     * return: JsonResponse
     */
    public function ActualizarDiseno(int $tnProducto, Request $toRequest): JsonResponse
    {
        $toRequest->validate([
            'Version' => ['required', 'string', 'max:30'],
            'Lienzo' => ['nullable', 'array'],
            'Capas' => ['nullable', 'array'],
            'Motivo' => ['nullable', 'string', 'max:255'],
        ]);

        $la = $this->toService->ActualizarDiseno($tnProducto, $toRequest->all(), (int)(auth()->id() ?? 0));
        if ($la['Estado'] === 'NO_ENCONTRADO') {
            return RespuestaApi::error('No existe diseno activo para este producto', 404);
        }
        if ($la['Estado'] === 'CONFLICTO_VERSION') {
            return RespuestaApi::error('Conflicto de version', 409, [
                ['Codigo' => 'VERSION_409', 'Campo' => 'Version', 'Detalle' => 'La version enviada no coincide']
            ], $la['Actual'] ?? []);
        }

        return RespuestaApi::exito('Diseno de producto actualizado correctamente', $la['Datos'] ?? []);
    }

    /**
     * SYSCOOP
     * category: Controller
     * package: App\Modulos\ProductoDiseno\Controllers
     * author: Vladimir Meriles Velasquez
     * fecha: 01-03-2026
     * param: int $tnProducto, Request $toRequest
     * return: JsonResponse
     */
    public function RenderDiseno(int $tnProducto, Request $toRequest): JsonResponse
    {
        $toRequest->validate([
            'Lienzo' => ['nullable', 'array'],
            'Capas' => ['nullable', 'array'],
        ]);

        $la = $this->toService->RenderDiseno($tnProducto, $toRequest->all());
        return RespuestaApi::exito('Render de diseno generado correctamente', $la['Datos'] ?? []);
    }

    /**
     * SYSCOOP
     * category: Controller
     * package: App\Modulos\ProductoDiseno\Controllers
     * author: Vladimir Meriles Velasquez
     * fecha: 01-03-2026
     * param: int $tnProducto
     * return: JsonResponse
     */
    public function Galeria(int $tnProducto): JsonResponse
    {
        $la = $this->toService->Galeria($tnProducto);
        return RespuestaApi::exito('Galeria de producto obtenida correctamente', $la['Datos'] ?? []);
    }

    /**
     * SYSCOOP
     * category: Controller
     * package: App\Modulos\ProductoDiseno\Controllers
     * author: Vladimir Meriles Velasquez
     * fecha: 01-03-2026
     * param: int $tnProducto, Request $toRequest
     * return: JsonResponse
     */
    public function SubirImagenLote(int $tnProducto, Request $toRequest): JsonResponse
    {
        $toRequest->validate([
            'Imagenes' => ['nullable', 'array'],
            'Imagenes.*.Url' => ['nullable', 'url', 'max:500'],
            'Imagenes.*.TipoImagen' => ['nullable', 'string', 'max:40'],
            'Imagenes.*.Orden' => ['nullable', 'integer', 'min:1'],
            'Archivos' => ['nullable', 'array'],
            'Archivos.*' => ['file', 'max:10240'],
            'Motivo' => ['nullable', 'string', 'max:255'],
        ]);

        $laArchivos = [];
        if ($toRequest->hasFile('Archivos')) {
            $laArchivos = $toRequest->file('Archivos');
        } elseif ($toRequest->hasFile('Archivo')) {
            $laArchivos = [$toRequest->file('Archivo')];
        }

        $la = $this->toService->SubirImagenLote(
            $tnProducto,
            (array)$toRequest->input('Imagenes', []),
            $laArchivos,
            (int)(auth()->id() ?? 0),
            $toRequest->filled('Motivo') ? (string)$toRequest->input('Motivo') : null
        );

        if ($la['Estado'] === 'NO_ENCONTRADO') {
            return RespuestaApi::error('Producto no encontrado', 404);
        }

        return RespuestaApi::exito('Lote de imagenes registrado correctamente', $la['Datos'] ?? []);
    }

    /**
     * SYSCOOP
     * category: Controller
     * package: App\Modulos\ProductoDiseno\Controllers
     * author: Vladimir Meriles Velasquez
     * fecha: 01-03-2026
     * param: int $tnProducto, Request $toRequest
     * return: JsonResponse
     */
    public function ReordenarGaleria(int $tnProducto, Request $toRequest): JsonResponse
    {
        $toRequest->validate([
            'Version' => ['required', 'string', 'max:30'],
            'Ordenes' => ['required', 'array', 'min:1'],
            'Ordenes.*.ProductoImagen' => ['required', 'integer', 'min:1'],
            'Ordenes.*.Orden' => ['required', 'integer', 'min:1'],
            'Motivo' => ['nullable', 'string', 'max:255'],
        ]);

        $la = $this->toService->ReordenarGaleria(
            $tnProducto,
            (array)$toRequest->input('Ordenes', []),
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

        return RespuestaApi::exito('Galeria reordenada correctamente', $la['Datos'] ?? []);
    }
}

<?php

namespace App\Modulos\MaquinaCeldas\Controllers;

use App\Http\Controllers\Controller;
use App\Modulos\MaquinaCeldas\Services\MaquinaCeldasService;
use App\Soporte\RespuestaApi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MaquinaCeldasController extends Controller
{
    /**
     * SYSCOOP
     * category: Controller
     * package: App\Modulos\MaquinaCeldas\Controllers
     * author: Vladimir Meriles Velasquez
     * fecha: 01-03-2026
     * param: MaquinaCeldasService $toService
     * return: void
     */
    public function __construct(private MaquinaCeldasService $toService)
    {
    }

    /**
     * SYSCOOP
     * category: Controller
     * package: App\Modulos\MaquinaCeldas\Controllers
     * author: Vladimir Meriles Velasquez
     * fecha: 01-03-2026
     * param: int $tnMaquina, Request $toRequest
     * return: JsonResponse
     */
    public function Matriz(int $tnMaquina, Request $toRequest): JsonResponse
    {
        $tnPagina = (int)$toRequest->query('Pagina', 1);
        $tnTamanoPagina = (int)$toRequest->query('TamanoPagina', 54);
        $tcBusqueda = $toRequest->filled('Busqueda') ? (string)$toRequest->query('Busqueda') : null;

        $la = $this->toService->Matriz($tnMaquina, $tcBusqueda, $tnPagina, $tnTamanoPagina, (int)(auth()->id() ?? 0));
        if ($la['Estado'] === 'NO_AUTORIZADO') {
            return RespuestaApi::error('No autorizado para consultar esta maquina', 403);
        }

        return RespuestaApi::paginado('Matriz de celdas por maquina', $la['Paginador']);
    }

    /**
     * SYSCOOP
     * category: Controller
     * package: App\Modulos\MaquinaCeldas\Controllers
     * author: Vladimir Meriles Velasquez
     * fecha: 01-03-2026
     * param: int $tnMaquina, Request $toRequest
     * return: JsonResponse
     */
    public function Conflictos(int $tnMaquina, Request $toRequest): JsonResponse
    {
        $tnPagina = (int)$toRequest->query('Pagina', 1);
        $tnTamanoPagina = (int)$toRequest->query('TamanoPagina', 20);

        $la = $this->toService->Conflictos($tnMaquina, $tnPagina, $tnTamanoPagina, (int)(auth()->id() ?? 0));
        if ($la['Estado'] === 'NO_AUTORIZADO') {
            return RespuestaApi::error('No autorizado para consultar esta maquina', 403);
        }

        return RespuestaApi::paginado('Conflictos de celdas', $la['Paginador']);
    }

    /**
     * SYSCOOP
     * category: Controller
     * package: App\Modulos\MaquinaCeldas\Controllers
     * author: Vladimir Meriles Velasquez
     * fecha: 01-03-2026
     * param: int $tnMaquina, Request $toRequest
     * return: JsonResponse
     */
    public function SimularOcupacion(int $tnMaquina, Request $toRequest): JsonResponse
    {
        $toRequest->validate([
            'CeldaAncla' => ['required', 'integer', 'min:1'],
            'Producto' => ['required', 'integer', 'min:1'],
            'Lote' => ['nullable', 'integer', 'min:1'],
            'Cantidad' => ['required', 'integer', 'min:1'],
            'SpanColumnas' => ['required', 'integer', 'min:1', 'max:9'],
            'SpanFilas' => ['required', 'integer', 'min:1', 'max:6'],
        ]);

        $la = $this->toService->SimularOcupacion($tnMaquina, $toRequest->all(), (int)(auth()->id() ?? 0));
        if ($la['Estado'] === 'NO_AUTORIZADO') {
            return RespuestaApi::error('No autorizado para operar esta maquina', 403);
        }
        if ($la['Estado'] === 'NO_ENCONTRADO') {
            return RespuestaApi::error('Celda ancla no encontrada', 404);
        }
        if ($la['Estado'] === 'CONFLICTO') {
            return RespuestaApi::error('Simulacion con conflictos', 409, $this->mapearConflictosAErrores($la['Datos']['Conflictos'] ?? []), $la['Datos'] ?? []);
        }

        return RespuestaApi::exito('Simulacion de ocupacion correcta', $la['Datos'] ?? []);
    }

    /**
     * SYSCOOP
     * category: Controller
     * package: App\Modulos\MaquinaCeldas\Controllers
     * author: Vladimir Meriles Velasquez
     * fecha: 01-03-2026
     * param: int $tnMaquina, Request $toRequest
     * return: JsonResponse
     */
    public function AsignarProducto(int $tnMaquina, Request $toRequest): JsonResponse
    {
        return $this->ejecutarIdempotente($toRequest, function () use ($tnMaquina, $toRequest) {
            $toRequest->validate([
                'CeldaAncla' => ['required', 'integer', 'min:1'],
                'Producto' => ['required', 'integer', 'min:1'],
                'Lote' => ['nullable', 'integer', 'min:1'],
                'Cantidad' => ['required', 'integer', 'min:1'],
                'SpanColumnas' => ['required', 'integer', 'min:1', 'max:9'],
                'SpanFilas' => ['required', 'integer', 'min:1', 'max:6'],
                'Version' => ['required', 'string', 'max:30'],
                'Motivo' => ['nullable', 'string', 'max:255'],
            ]);

            $la = $this->toService->AsignarProducto($tnMaquina, $toRequest->all(), (int)(auth()->id() ?? 0));
            if ($la['Estado'] === 'NO_AUTORIZADO') {
                return RespuestaApi::error('No autorizado para operar esta maquina', 403);
            }
            if ($la['Estado'] === 'NO_ENCONTRADO') {
                return RespuestaApi::error('Celda ancla no encontrada', 404);
            }
            if ($la['Estado'] === 'PRODUCTO_NO_ENCONTRADO') {
                return RespuestaApi::error('Producto no encontrado', 404);
            }
            if ($la['Estado'] === 'LOTE_NO_ENCONTRADO') {
                return RespuestaApi::error('Lote no encontrado', 404);
            }
            if ($la['Estado'] === 'CONFLICTO_VERSION') {
                return RespuestaApi::error('Conflicto de version', 409, [
                    ['Codigo' => 'VERSION_409', 'Campo' => 'Version', 'Detalle' => 'La version enviada no coincide']
                ], $la['Actual'] ?? []);
            }
            if ($la['Estado'] === 'CONFLICTO') {
                return RespuestaApi::error('No fue posible asignar por conflictos de ocupacion', 409, $this->mapearConflictosAErrores($la['Datos']['Conflictos'] ?? []), $la['Datos'] ?? []);
            }

            return RespuestaApi::exito('Producto asignado correctamente en celdas', $la['Datos'] ?? []);
        });
    }

    /**
     * SYSCOOP
     * category: Controller
     * package: App\Modulos\MaquinaCeldas\Controllers
     * author: Vladimir Meriles Velasquez
     * fecha: 01-03-2026
     * param: int $tnMaquina, Request $toRequest
     * return: JsonResponse
     */
    public function Liberar(int $tnMaquina, Request $toRequest): JsonResponse
    {
        return $this->ejecutarIdempotente($toRequest, function () use ($tnMaquina, $toRequest) {
            $toRequest->validate([
                'CeldaAncla' => ['required', 'integer', 'min:1'],
                'SpanColumnas' => ['nullable', 'integer', 'min:1', 'max:9'],
                'SpanFilas' => ['nullable', 'integer', 'min:1', 'max:6'],
                'Version' => ['required', 'string', 'max:30'],
                'Motivo' => ['nullable', 'string', 'max:255'],
            ]);

            $la = $this->toService->Liberar($tnMaquina, $toRequest->all(), (int)(auth()->id() ?? 0));
            if ($la['Estado'] === 'NO_AUTORIZADO') {
                return RespuestaApi::error('No autorizado para operar esta maquina', 403);
            }
            if ($la['Estado'] === 'NO_ENCONTRADO') {
                return RespuestaApi::error('Celda ancla no encontrada', 404);
            }
            if ($la['Estado'] === 'NO_OCUPACION') {
                return RespuestaApi::error('No existe ocupacion activa para la celda ancla indicada', 404);
            }
            if ($la['Estado'] === 'CONFLICTO_VERSION') {
                return RespuestaApi::error('Conflicto de version', 409, [
                    ['Codigo' => 'VERSION_409', 'Campo' => 'Version', 'Detalle' => 'La version enviada no coincide']
                ], $la['Actual'] ?? []);
            }

            return RespuestaApi::exito('Ocupacion liberada correctamente', $la['Datos'] ?? []);
        });
    }

    /**
     * @param array<int,string> $laConflictos
     * @return array<int,array{Codigo:string,Campo:?string,Detalle:string}>
     */
    private function mapearConflictosAErrores(array $laConflictos): array
    {
        $laErrores = [];
        foreach ($laConflictos as $tcDetalle) {
            $laErrores[] = [
                'Codigo' => 'CELDA_CONFLICTO',
                'Campo' => null,
                'Detalle' => (string)$tcDetalle,
            ];
        }
        return $laErrores;
    }
}


<?php

namespace App\Modulos\Deposito\Controllers;

use App\Http\Controllers\Controller;
use App\Modulos\Deposito\Services\DepositoService;
use App\Soporte\RespuestaApi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DepositoController extends Controller
{
    /**
     * SYSCOOP
     * category: Controller
     * package: App\Modulos\Deposito\Controllers
     * author: Vladimir Meriles Velasquez
     * fecha: 01-03-2026
     * param: DepositoService $toService
     * return: void
     */
    public function __construct(private DepositoService $toService)
    {
    }

    /**
     * SYSCOOP
     * category: Controller
     * package: App\Modulos\Deposito\Controllers
     * author: Vladimir Meriles Velasquez
     * fecha: 01-03-2026
     * param: Request $toRequest
     * return: JsonResponse
     */
    public function Listar(Request $toRequest): JsonResponse
    {
        $la = $this->toService->Listar($toRequest->query(), (int)(auth()->id() ?? 0));
        if ($la['Estado'] === 'NO_AUTORIZADO') {
            return RespuestaApi::error('No autorizado para consultar depositos de otra empresa', 403);
        }

        return RespuestaApi::paginado('Listado de depositos', $la['Paginador']);
    }

    /**
     * SYSCOOP
     * category: Controller
     * package: App\Modulos\Deposito\Controllers
     * author: Vladimir Meriles Velasquez
     * fecha: 01-03-2026
     * param: int $tnDeposito, Request $toRequest
     * return: JsonResponse
     */
    public function Stock(int $tnDeposito, Request $toRequest): JsonResponse
    {
        $la = $this->toService->Stock($tnDeposito, $toRequest->query(), (int)(auth()->id() ?? 0));
        if ($la['Estado'] === 'NO_AUTORIZADO') {
            return RespuestaApi::error('No autorizado para consultar este deposito', 403);
        }

        return RespuestaApi::paginado('Stock de deposito', $la['Paginador']);
    }

    /**
     * SYSCOOP
     * category: Controller
     * package: App\Modulos\Deposito\Controllers
     * author: Vladimir Meriles Velasquez
     * fecha: 01-03-2026
     * param: Request $toRequest
     * return: JsonResponse
     */
    public function Movimientos(Request $toRequest): JsonResponse
    {
        $la = $this->toService->Movimientos($toRequest->query(), (int)(auth()->id() ?? 0));
        if ($la['Estado'] === 'NO_AUTORIZADO') {
            return RespuestaApi::error('No autorizado para consultar movimientos de este deposito', 403);
        }

        return RespuestaApi::paginado('Movimientos de deposito', $la['Paginador']);
    }

    /**
     * SYSCOOP
     * category: Controller
     * package: App\Modulos\Deposito\Controllers
     * author: Vladimir Meriles Velasquez
     * fecha: 01-03-2026
     * param: Request $toRequest
     * return: JsonResponse
     */
    public function Entrada(Request $toRequest): JsonResponse
    {
        return $this->ejecutarIdempotente($toRequest, function () use ($toRequest) {
            $toRequest->validate([
                'Deposito' => ['required', 'integer', 'min:1'],
                'Producto' => ['required', 'integer', 'min:1'],
                'Lote' => ['nullable', 'integer', 'min:1'],
                'Cantidad' => ['required', 'integer', 'min:1'],
                'Motivo' => ['nullable', 'string', 'max:255'],
            ]);

            $la = $this->toService->Entrada($toRequest->all(), (int)(auth()->id() ?? 0));
            if ($la['Estado'] === 'NO_AUTORIZADO') {
                return RespuestaApi::error('No autorizado para operar este deposito', 403);
            }
            if ($la['Estado'] === 'ERROR_STOCK') {
                return RespuestaApi::error('No fue posible crear/obtener stock de deposito', 409);
            }

            return RespuestaApi::exito('Entrada de deposito registrada correctamente', $la['Datos'] ?? []);
        });
    }

    /**
     * SYSCOOP
     * category: Controller
     * package: App\Modulos\Deposito\Controllers
     * author: Vladimir Meriles Velasquez
     * fecha: 01-03-2026
     * param: Request $toRequest
     * return: JsonResponse
     */
    public function Salida(Request $toRequest): JsonResponse
    {
        return $this->ejecutarIdempotente($toRequest, function () use ($toRequest) {
            $toRequest->validate([
                'Deposito' => ['required', 'integer', 'min:1'],
                'Producto' => ['required', 'integer', 'min:1'],
                'Lote' => ['nullable', 'integer', 'min:1'],
                'Cantidad' => ['required', 'integer', 'min:1'],
                'Motivo' => ['nullable', 'string', 'max:255'],
            ]);

            $la = $this->toService->Salida($toRequest->all(), (int)(auth()->id() ?? 0));
            if ($la['Estado'] === 'NO_AUTORIZADO') {
                return RespuestaApi::error('No autorizado para operar este deposito', 403);
            }
            if ($la['Estado'] === 'NO_ENCONTRADO') {
                return RespuestaApi::error('No existe stock en deposito para producto/lote indicado', 404);
            }
            if ($la['Estado'] === 'STOCK_INSUFICIENTE') {
                return RespuestaApi::error('Stock insuficiente en deposito', 409, [
                    ['Codigo' => 'STOCK_409', 'Campo' => 'Cantidad', 'Detalle' => 'La cantidad solicitada excede el disponible']
                ], ['Disponible' => (int)($la['Disponible'] ?? 0)]);
            }

            return RespuestaApi::exito('Salida de deposito registrada correctamente', $la['Datos'] ?? []);
        });
    }

    /**
     * SYSCOOP
     * category: Controller
     * package: App\Modulos\Deposito\Controllers
     * author: Vladimir Meriles Velasquez
     * fecha: 01-03-2026
     * param: Request $toRequest
     * return: JsonResponse
     */
    public function TransferirAMaquina(Request $toRequest): JsonResponse
    {
        return $this->ejecutarIdempotente($toRequest, function () use ($toRequest) {
            $toRequest->validate([
                'Deposito' => ['required', 'integer', 'min:1'],
                'Maquina' => ['required', 'integer', 'min:1'],
                'Celda' => ['required', 'integer', 'min:1'],
                'Producto' => ['required', 'integer', 'min:1'],
                'Lote' => ['nullable', 'integer', 'min:1'],
                'Cantidad' => ['required', 'integer', 'min:1'],
                'Motivo' => ['nullable', 'string', 'max:255'],
            ]);

            $la = $this->toService->TransferirAMaquina($toRequest->all(), (int)(auth()->id() ?? 0));
            if ($la['Estado'] === 'NO_AUTORIZADO') {
                return RespuestaApi::error('No autorizado para operar este deposito', 403);
            }
            if ($la['Estado'] === 'NO_AUTORIZADO_MAQUINA') {
                return RespuestaApi::error('No autorizado para operar la maquina indicada', 403);
            }
            if ($la['Estado'] === 'NO_ENCONTRADO') {
                return RespuestaApi::error('No existe stock en deposito para producto/lote indicado', 404);
            }
            if ($la['Estado'] === 'MAQUINA_NO_ENCONTRADA') {
                return RespuestaApi::error('Maquina no encontrada', 404);
            }
            if ($la['Estado'] === 'CELDA_NO_ENCONTRADA') {
                return RespuestaApi::error('Celda no encontrada para la maquina indicada', 404);
            }
            if ($la['Estado'] === 'ERROR_EXISTENCIA') {
                return RespuestaApi::error('No fue posible preparar existencia de celda para transferencia', 409);
            }
            if ($la['Estado'] === 'STOCK_INSUFICIENTE') {
                return RespuestaApi::error('Stock insuficiente en deposito', 409, [
                    ['Codigo' => 'STOCK_409', 'Campo' => 'Cantidad', 'Detalle' => 'La cantidad solicitada excede el disponible']
                ], ['Disponible' => (int)($la['Disponible'] ?? 0)]);
            }

            return RespuestaApi::exito('Transferencia a maquina registrada correctamente', $la['Datos'] ?? []);
        });
    }
}


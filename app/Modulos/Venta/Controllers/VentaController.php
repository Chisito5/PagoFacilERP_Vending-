<?php

namespace App\Modulos\Venta\Controllers;

use App\Http\Controllers\Controller;
use App\Modulos\Venta\Services\VentaService;
use App\Soporte\RespuestaApi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VentaController extends Controller
{
    public function __construct(private VentaService $toService)
    {
    }

    public function Crear(Request $toRequest): JsonResponse
    {
        return $this->ejecutarIdempotente($toRequest, function () use ($toRequest) {
            $toRequest->validate([
                'Maquina' => ['required', 'integer', 'min:1'],
                'CodigoSeleccion' => ['required', 'string', 'max:10'],
                'Cantidad' => ['required', 'integer', 'min:1'],
            ]);

            return $this->toService->VenderPorSeleccion(
                (int)$toRequest->input('Maquina'),
                (string)$toRequest->input('CodigoSeleccion'),
                (int)$toRequest->input('Cantidad')
            );
        });
    }

    public function Listar(Request $toRequest): JsonResponse
    {
        $tnPagina = (int)$toRequest->query('Pagina', 1);
        $tnTamanoPagina = (int)$toRequest->query('TamanoPagina', 20);

        $toPaginador = $this->toService->Listar($tnPagina, $tnTamanoPagina);

        return RespuestaApi::paginado('Listado de ventas', $toPaginador);
    }

    public function ListarPorMaquina(int $tnMaquina, Request $toRequest): JsonResponse
    {
        if ($tnMaquina <= 0) {
            return RespuestaApi::error('Maquina invalida', 400, [
                ['Codigo' => 'VENTA_001', 'Campo' => 'Maquina', 'Detalle' => 'Debe enviar una maquina valida']
            ]);
        }

        $tnPagina = (int)$toRequest->query('Pagina', 1);
        $tnTamanoPagina = (int)$toRequest->query('TamanoPagina', 20);

        $toPaginador = $this->toService->ListarPorMaquina($tnMaquina, $tnPagina, $tnTamanoPagina);

        return RespuestaApi::paginado('Listado de ventas por maquina', $toPaginador);
    }

    public function Reversar(Request $toRequest): JsonResponse
    {
        return $this->ejecutarIdempotente($toRequest, function () use ($toRequest) {
            $toRequest->validate([
                'Venta' => ['required', 'integer', 'min:1'],
                'Motivo' => ['required', 'string', 'max:255'],
            ]);

            return $this->toService->Reversar(
                (int)$toRequest->input('Venta'),
                (string)$toRequest->input('Motivo')
            );
        });
    }
}

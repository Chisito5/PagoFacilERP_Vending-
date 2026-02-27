<?php

namespace App\Modulos\Venta\Controllers;

use App\Http\Controllers\Controller;
use App\Modulos\Venta\Services\VentaService;
use Illuminate\Http\Request;

class VentaController extends Controller
{
    private VentaService $loService;

    /**
     * SYSCOOP
     * category: Controller
     * package: App\Modulos\Venta\Controllers
     * author: Vladimir Meriles velasquez
     * fecha: 27-02-2026
     * param: VentaService $loService
     * return: void
     *
     * Inyección del servicio de Venta.
     */
    public function __construct(VentaService $loService)
    {
        $this->loService = $loService;
    }

    /**
     * SYSCOOP
     * category: Controller
     * package: App\Modulos\Venta\Controllers
     * author: Vladimir Meriles velasquez
     * fecha: 27-02-2026
     * param: Request $toRequest
     * return: \Illuminate\Http\JsonResponse
     *
     * Procesa una venta por (Maquina + CodigoSeleccion).
     */
    public function Crear(Request $toRequest)
    {
        return $this->ejecutarIdempotente($toRequest, function () use ($toRequest) {
            $toRequest->validate([
                'Maquina' => ['required', 'integer', 'min:1'],
                'CodigoSeleccion' => ['required', 'string', 'max:10'],
                'Cantidad' => ['required', 'integer', 'min:1'],
            ]);

            $tnMaquina = (int)$toRequest->input('Maquina');
            $tcCodigoSeleccion = (string)$toRequest->input('CodigoSeleccion');
            $tnCantidad = (int)$toRequest->input('Cantidad');

            return $this->loService->VenderPorSeleccion($tnMaquina, $tcCodigoSeleccion, $tnCantidad);
        });
    }

    /**
     * SYSCOOP
     * category: Controller
     * package: App\Modulos\Venta\Controllers
     * author: Vladimir Meriles velasquez
     * fecha: 27-02-2026
     * return: \Illuminate\Http\JsonResponse
     *
     * Lista todas las ventas (últimas primero).
     */
    public function Listar()
    {
        return $this->loService->Listar();
    }

    /**
     * SYSCOOP
     * category: Controller
     * package: App\Modulos\Venta\Controllers
     * author: Vladimir Meriles velasquez
     * fecha: 27-02-2026
     * param: int $tnMaquina
     * return: \Illuminate\Http\JsonResponse
     *
     * Lista ventas filtradas por máquina.
     */
    public function ListarPorMaquina(int $tnMaquina)
    {
        if ($tnMaquina <= 0) {
            return response()->json([
                'Ok' => false,
                'Mensaje' => 'Maquina inválida'
            ], 400);
        }

        return $this->loService->ListarPorMaquina($tnMaquina);
    }

    /**
     * SYSCOOP
     * category: Controller
     * package: App\Modulos\Venta\Controllers
     * author: Vladimir Meriles velasquez
     * fecha: 27-02-2026
     * param: Request $toRequest
     * return: \Illuminate\Http\JsonResponse
     *
     * Reversa una venta (anula) y devuelve stock.
     */
    public function Reversar(Request $toRequest)
    {
        return $this->ejecutarIdempotente($toRequest, function () use ($toRequest) {
            $toRequest->validate([
                'Venta' => ['required', 'integer', 'min:1'],
                'Motivo' => ['required', 'string', 'max:255'],
            ]);

            $tnVenta = (int)$toRequest->input('Venta');
            $tcMotivo = (string)$toRequest->input('Motivo');

            return $this->loService->Reversar($tnVenta, $tcMotivo);
        });
    }
}

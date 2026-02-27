<?php

namespace App\Modulos\Venta\Controllers;

use App\Http\Controllers\Controller;
use App\Modulos\Venta\Services\VentaService;
use Illuminate\Http\Request;

class VentaController extends Controller
{
    private VentaService $toVentaService;

    public function __construct(VentaService $toVentaService)
    {
        $this->toVentaService = $toVentaService;
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
        $tnMaquina = (int) $toRequest->input('Maquina');
        $tcCodigoSeleccion = (string) $toRequest->input('CodigoSeleccion');
        $tnCantidad = (int) $toRequest->input('Cantidad');

        if ($tnMaquina <= 0 || $tcCodigoSeleccion === '' || $tnCantidad <= 0) {
            return response()->json([
                'Ok' => false,
                'Mensaje' => 'Datos inválidos. Requiere: Maquina (int), CodigoSeleccion (string), Cantidad (int > 0)'
            ], 400);
        }

        return $this->toVentaService->VenderPorSeleccion($tnMaquina, $tcCodigoSeleccion, $tnCantidad);
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
        return $this->toVentaService->Listar();
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
    public function ListarPorMaquina(int $Maquina)
    {
        $tnMaquina = (int) $Maquina;

        if ($tnMaquina <= 0) {
            return response()->json([
                'Ok' => false,
                'Mensaje' => 'Maquina inválida'
            ], 400);
        }

        return $this->toVentaService->ListarPorMaquina($tnMaquina);
    }
}
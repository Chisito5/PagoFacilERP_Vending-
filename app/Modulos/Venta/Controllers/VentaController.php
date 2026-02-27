<?php

namespace App\Modulos\Venta\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Modulos\Venta\Services\VentaService;

/**
 *
 * Controlador que gestiona el proceso de Venta desde la Máquina.
 *
 * @category     PagoFacil
 * @package      Venta
 * @author       Equipo PagoFacil
 * @fecha        26-02-2026
 */
class VentaController extends Controller
{
    private VentaService $poVentaService;

    public function __construct(VentaService $toVentaService)
    {
        $this->poVentaService = $toVentaService;
    }

    /**
     * Procesa una venta por (Maquina + CodigoSeleccion).
     *
     * @method      Vender()
     * @author      Equipo PagoFacil
     * @fecha       26-02-2026
     * @param       Request $toRequest
     */
    public function Vender(Request $toRequest)
    {
        $laDatos = $toRequest->validate([
            'Maquina' => ['required', 'integer'],
            'CodigoSeleccion' => ['required', 'string', 'max:10'],
            'Cantidad' => ['required', 'integer', 'min:1'],
        ]);

        return $this->poVentaService->Vender(
            (int)$laDatos['Maquina'],
            (string)$laDatos['CodigoSeleccion'],
            (int)$laDatos['Cantidad']
        );
    }
}

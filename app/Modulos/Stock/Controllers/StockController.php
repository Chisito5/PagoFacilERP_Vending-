<?php

namespace App\Modulos\Stock\Controllers;

use App\Http\Controllers\Controller;
use App\Modulos\Stock\Services\StockService;

/**
 *
 * Controlador que gestiona la consulta de Stock por Máquina.
 *
 * @category     PagoFacil
 * @package      Stock
 * @author       Equipo PagoFacil
 * @fecha        26-02-2026
 */
class StockController extends Controller
{
    protected StockService $poStockService;

    public function __construct(StockService $toStockService)
    {
        $this->poStockService = $toStockService;
    }

    /**
     * Devuelve stock por máquina (celdas + producto + existencia).
     *
     * @method      StockPorMaquina()
     * @author      Equipo PagoFacil
     * @fecha       26-02-2026
     * @param       int $tnMaquina
     */
    public function StockPorMaquina(int $tnMaquina)
    {
        $laDatos = $this->poStockService->StockPorMaquina($tnMaquina);

        return response()->json([
            'Ok' => true,
            'Mensaje' => 'Stock por máquina (celdas + producto + existencia)',
            'Datos' => $laDatos
        ]);
    }
}

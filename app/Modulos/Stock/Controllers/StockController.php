<?php

namespace App\Modulos\Stock\Controllers;

use App\Http\Controllers\Controller;
use App\Modulos\Stock\Services\StockService;
use Illuminate\Http\Request;

class StockController extends Controller
{
    public function __construct(private StockService $service) {}

    public function StockPorMaquina(int $IdMaquina, Request $request)
    {
        $datos = $this->service->StockPorMaquina($IdMaquina);

        return response()->json([
            "Ok" => true,
            "Mensaje" => "Stock por máquina (celdas + producto + existencia)",
            "Datos" => $datos
        ]);
    }
}
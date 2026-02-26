<?php

namespace App\Modulos\PlanogramaCelda\Controllers;

use App\Http\Controllers\Controller;
use App\Modulos\PlanogramaCelda\Services\PlanogramaCeldaService;

class PlanogramaCeldaController extends Controller
{
    public function __construct(private PlanogramaCeldaService $service) {}

    public function Listar()
    {
        $datos = $this->service->Listar();

        return response()->json([
            'Ok' => true,
            'Mensaje' => 'Listado de planograma por celda',
            'Datos' => $datos
        ]);
    }

    public function ListarPorCelda(int $IdCelda)
    {
        $datos = $this->service->ListarPorCelda($IdCelda);

        return response()->json([
            'Ok' => true,
            'Mensaje' => 'Listado de planograma por celda (filtrado)',
            'Datos' => $datos
        ]);
    }

    public function ListarPorPlanograma(int $IdPlanograma)
    {
        $datos = $this->service->ListarPorPlanograma($IdPlanograma);

        return response()->json([
            'Ok' => true,
            'Mensaje' => 'Listado de planograma por IdPlanograma (filtrado)',
            'Datos' => $datos
        ]);
    }
}
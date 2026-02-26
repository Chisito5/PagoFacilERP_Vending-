<?php

namespace App\Modulos\ExistenciaCelda\Controllers;

use App\Http\Controllers\Controller;
use App\Modulos\ExistenciaCelda\Services\ExistenciaCeldaService;

class ExistenciaCeldaController extends Controller
{
    public function __construct(private ExistenciaCeldaService $service) {}

    public function Listar()
    {
        $datos = $this->service->Listar();

        return response()->json([
            'Ok' => true,
            'Mensaje' => 'Listado de existencias por celda',
            'Datos' => $datos
        ]);
    }

    public function ListarPorCelda(int $IdCelda)
    {
        $datos = $this->service->ListarPorCelda($IdCelda);

        return response()->json([
            'Ok' => true,
            'Mensaje' => 'Listado de existencias por celda (filtrado)',
            'Datos' => $datos
        ]);
    }
}
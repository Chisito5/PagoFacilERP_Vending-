<?php

namespace App\Modulos\Maquina\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Modulos\Maquina\Services\MaquinaService;

class MaquinaController extends Controller
{
    public function __construct(private MaquinaService $service) {}

    public function Listar()
    {
        $datos = $this->service->Listar();

        return response()->json([
            'Ok' => true,
            'Mensaje' => 'Listado de máquinas',
            'Datos' => $datos
        ]);
    }

    public function ListarCeldas(int $IdMaquina)
    {
        $datos = $this->service->ListarCeldas($IdMaquina);

        return response()->json([
            'Ok' => true,
            'Mensaje' => 'Listado de celdas por máquina',
            'Datos' => $datos
        ]);
    }
}
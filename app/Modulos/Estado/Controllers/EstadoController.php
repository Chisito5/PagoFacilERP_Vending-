<?php

namespace App\Modulos\Estado\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Modulos\Estado\Services\EstadoService;

class EstadoController
{
    protected EstadoService $estadoService;

    public function __construct(EstadoService $estadoService)
    {
        $this->estadoService = $estadoService;
    }

    public function Listar(Request $request): JsonResponse
    {
        $entidad = $request->query('Entidad');

        $estados = $this->estadoService->Listar($entidad);

        return response()->json([
            'Ok' => true,
            'Mensaje' => 'Listado de estados',
            'Datos' => $estados,
        ]);
    }
}
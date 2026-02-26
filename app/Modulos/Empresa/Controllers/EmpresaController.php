<?php

namespace App\Modulos\Empresa\Controllers;

use Illuminate\Http\JsonResponse;
use App\Modulos\Empresa\Services\EmpresaService;

class EmpresaController
{
    protected EmpresaService $empresaService;

    public function __construct(EmpresaService $empresaService)
    {
        $this->empresaService = $empresaService;
    }

    public function Listar(): JsonResponse
    {
        $empresas = $this->empresaService->Listar();

        return response()->json([
            'Ok' => true,
            'Mensaje' => 'Listado de empresas',
            'Datos' => $empresas,
        ]);
    }
}
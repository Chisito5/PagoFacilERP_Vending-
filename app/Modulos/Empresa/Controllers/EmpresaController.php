<?php

namespace App\Modulos\Empresa\Controllers;

use Illuminate\Http\JsonResponse;
use App\Modulos\Empresa\Services\EmpresaService;
use App\Modulos\Empresa\Requests\CrearEmpresaRequest;

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
    public function Crear(CrearEmpresaRequest $request): JsonResponse
    {
        $empresa = $this->empresaService->Crear($request->validated());

        return response()->json([
            'Ok' => true,
            'Mensaje' => 'Empresa creada correctamente',
            'Datos' => $empresa,
        ]);
    }
}

<?php

namespace App\Modulos\TipoEmpresa\Controllers;

use Illuminate\Http\JsonResponse;
use App\Modulos\TipoEmpresa\Services\TipoEmpresaService;

class TipoEmpresaController
{
    protected TipoEmpresaService $tipoEmpresaService;

    public function __construct(TipoEmpresaService $tipoEmpresaService)
    {
        $this->tipoEmpresaService = $tipoEmpresaService;
    }

    public function Listar(): JsonResponse
    {
        $tipos = $this->tipoEmpresaService->Listar();

        return response()->json([
            'Ok' => true,
            'Mensaje' => 'Listado de tipos de empresa',
            'Datos' => $tipos,
        ]);
    }
}
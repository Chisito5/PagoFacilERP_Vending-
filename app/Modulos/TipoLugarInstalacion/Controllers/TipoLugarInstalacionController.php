<?php

namespace App\Modulos\TipoLugarInstalacion\Controllers;

use App\Http\Controllers\Controller;
use App\Modulos\TipoLugarInstalacion\Services\TipoLugarInstalacionService;
use App\Soporte\RespuestaApi;
use Illuminate\Http\JsonResponse;

class TipoLugarInstalacionController extends Controller
{
    public function __construct(private TipoLugarInstalacionService $toService)
    {
    }

    public function Listar(): JsonResponse
    {
        return RespuestaApi::exito('Listado de tipos de lugar de instalacion', $this->toService->listar());
    }
}

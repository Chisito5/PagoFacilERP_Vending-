<?php

namespace App\Modulos\TipoInternet\Controllers;

use App\Http\Controllers\Controller;
use App\Modulos\TipoInternet\Services\TipoInternetService;
use App\Soporte\RespuestaApi;
use Illuminate\Http\JsonResponse;

class TipoInternetController extends Controller
{
    public function __construct(private TipoInternetService $toService)
    {
    }

    public function Listar(): JsonResponse
    {
        return RespuestaApi::exito('Listado de tipos de internet', $this->toService->listar());
    }
}

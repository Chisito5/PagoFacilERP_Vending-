<?php

namespace App\Modulos\Auditoria\Controllers;

use App\Http\Controllers\Controller;
use App\Modulos\Auditoria\Services\AuditoriaConsultaService;
use App\Soporte\RespuestaApi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditoriaController extends Controller
{
    public function __construct(private AuditoriaConsultaService $toService)
    {
    }

    public function Historial(Request $toRequest, string $tcEntidad, string $tcEntidadId): JsonResponse
    {
        $tnPagina = (int)$toRequest->query('Pagina', 1);
        $tnTamanoPagina = (int)$toRequest->query('TamanoPagina', 20);

        $toPaginador = $this->toService->Historial($tcEntidad, $tcEntidadId, $tnPagina, $tnTamanoPagina);

        return RespuestaApi::paginado('Historial de auditoria', $toPaginador);
    }
}

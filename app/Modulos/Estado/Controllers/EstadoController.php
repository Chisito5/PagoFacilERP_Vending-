<?php

namespace App\Modulos\Estado\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Modulos\Estado\Services\EstadoService;

/**
 *
 * Controlador que gestiona la consulta de Estados.
 *
 * @category     PagoFacil
 * @package      Estado
 * @author       Equipo PagoFacil
 * @fecha        26-02-2026
 */
class EstadoController extends Controller
{
    protected EstadoService $poEstadoService;

    public function __construct(EstadoService $toEstadoService)
    {
        $this->poEstadoService = $toEstadoService;
    }

    /**
     * Lista estados (opcionalmente filtrando por Entidad).
     *
     * @method      Listar()
     * @author      Equipo PagoFacil
     * @fecha       26-02-2026
     * @param       Request $toRequest
     * @return      JsonResponse
     */
    public function Listar(Request $toRequest): JsonResponse
    {
        $tcEntidad = $toRequest->query('Entidad');
        $tcEntidad = is_null($tcEntidad) ? null : (string)$tcEntidad;

        $loEstados = $this->poEstadoService->Listar($tcEntidad);

        return response()->json([
            'Ok' => true,
            'Mensaje' => 'Listado de estados',
            'Datos' => $loEstados,
        ]);
    }
}

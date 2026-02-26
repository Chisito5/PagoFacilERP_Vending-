<?php

namespace App\Modulos\TipoEmpresa\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use App\Modulos\TipoEmpresa\Services\TipoEmpresaService;

/**
 *
 * Controlador que gestiona la consulta de Tipos de Empresa.
 *
 * @category     PagoFacil
 * @package      TipoEmpresa
 * @author       Equipo PagoFacil
 * @fecha        26-02-2026
 */
class TipoEmpresaController extends Controller
{
    protected TipoEmpresaService $poTipoEmpresaService;

    public function __construct(TipoEmpresaService $toTipoEmpresaService)
    {
        $this->poTipoEmpresaService = $toTipoEmpresaService;
    }

    /**
     * Lista tipos de empresa.
     *
     * @method      Listar()
     * @author      Equipo PagoFacil
     * @fecha       26-02-2026
     * @return      JsonResponse
     */
    public function Listar(): JsonResponse
    {
        $loTipos = $this->poTipoEmpresaService->Listar();

        return response()->json([
            'Ok' => true,
            'Mensaje' => 'Listado de tipos de empresa',
            'Datos' => $loTipos,
        ]);
    }
}

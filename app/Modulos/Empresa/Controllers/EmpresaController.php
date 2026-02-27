<?php

namespace App\Modulos\Empresa\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use App\Modulos\Empresa\Services\EmpresaService;
use App\Modulos\Empresa\Requests\CrearEmpresaRequest;

/**
 *
 * Controlador que gestiona las operaciones de Empresa.
 *
 * @category     PagoFacil
 * @package      Empresa
 * @author       Equipo PagoFacil
 * @fecha        26-02-2026
 */
class EmpresaController extends Controller
{
    /** @var EmpresaService */
    protected EmpresaService $poEmpresaService;

    public function __construct(EmpresaService $toEmpresaService)
    {
        $this->poEmpresaService = $toEmpresaService;
    }

    /**
     * Lista empresas registradas.
     *
     * @method      Listar()
     * @author      Equipo PagoFacil
     * @fecha       26-02-2026
     * @return      JsonResponse
     */
    public function Listar(): JsonResponse
    {
        $loEmpresas = $this->poEmpresaService->Listar();

        return response()->json([
            'Ok' => true,
            'Mensaje' => 'Listado de empresas',
            'Datos' => $loEmpresas,
        ]);
    }

    /**
     * Crea una empresa.
     *
     * @method      Crear()
     * @author      Equipo PagoFacil
     * @fecha       26-02-2026
     * @param       CrearEmpresaRequest $toRequest
     * @return      JsonResponse
     */
    public function Crear(CrearEmpresaRequest $toRequest): JsonResponse
    {
        $laDatos = $toRequest->validated();
        $loEmpresa = $this->poEmpresaService->Crear($laDatos);

        return response()->json([
            'Ok' => true,
            'Mensaje' => 'Empresa creada correctamente',
            'Datos' => $loEmpresa,
        ]);
    }
}

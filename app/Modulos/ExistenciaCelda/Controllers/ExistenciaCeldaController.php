<?php

namespace App\Modulos\ExistenciaCelda\Controllers;

use App\Http\Controllers\Controller;
use App\Modulos\ExistenciaCelda\Services\ExistenciaCeldaService;

/**
 *
 * Controlador que gestiona la consulta de Existencias por Celda.
 *
 * @category     PagoFacil
 * @package      ExistenciaCelda
 * @author       Equipo PagoFacil
 * @fecha        26-02-2026
 */
class ExistenciaCeldaController extends Controller
{
    protected ExistenciaCeldaService $poExistenciaCeldaService;

    public function __construct(ExistenciaCeldaService $toExistenciaCeldaService)
    {
        $this->poExistenciaCeldaService = $toExistenciaCeldaService;
    }

    /**
     * Lista existencias.
     *
     * @method      Listar()
     * @author      Equipo PagoFacil
     * @fecha       26-02-2026
     */
    public function Listar()
    {
        $loDatos = $this->poExistenciaCeldaService->Listar();

        return response()->json([
            'Ok' => true,
            'Mensaje' => 'Listado de existencias por celda',
            'Datos' => $loDatos
        ]);
    }

    /**
     * Lista existencias filtrando por Celda.
     *
     * @method      ListarPorCelda()
     * @author      Equipo PagoFacil
     * @fecha       26-02-2026
     * @param       int $tnCelda
     */
    public function ListarPorCelda(int $tnCelda)
    {
        $loDatos = $this->poExistenciaCeldaService->ListarPorCelda($tnCelda);

        return response()->json([
            'Ok' => true,
            'Mensaje' => 'Listado de existencias por celda (filtrado)',
            'Datos' => $loDatos
        ]);
    }
}

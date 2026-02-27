<?php

namespace App\Modulos\Maquina\Controllers;

use App\Http\Controllers\Controller;
use App\Modulos\Maquina\Services\MaquinaService;

/**
 *
 * Controlador que gestiona la consulta de Máquinas y sus Celdas.
 *
 * @category     PagoFacil
 * @package      Maquina
 * @author       Equipo PagoFacil
 * @fecha        26-02-2026
 */
class MaquinaController extends Controller
{
    protected MaquinaService $poMaquinaService;

    public function __construct(MaquinaService $toMaquinaService)
    {
        $this->poMaquinaService = $toMaquinaService;
    }

    /**
     * Lista máquinas.
     *
     * @method      Listar()
     * @author      Equipo PagoFacil
     * @fecha       26-02-2026
     */
    public function Listar()
    {
        $loDatos = $this->poMaquinaService->Listar();

        return response()->json([
            'Ok' => true,
            'Mensaje' => 'Listado de máquinas',
            'Datos' => $loDatos
        ]);
    }

    /**
     * Lista celdas por máquina.
     *
     * @method      ListarCeldas()
     * @author      Equipo PagoFacil
     * @fecha       26-02-2026
     * @param       int $tnMaquina
     */
    public function ListarCeldas(int $tnMaquina)
    {
        $loDatos = $this->poMaquinaService->ListarCeldas($tnMaquina);

        return response()->json([
            'Ok' => true,
            'Mensaje' => 'Listado de celdas por máquina',
            'Datos' => $loDatos
        ]);
    }
}

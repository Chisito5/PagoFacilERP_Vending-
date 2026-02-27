<?php

namespace App\Modulos\ExistenciaCelda\Services;

use Illuminate\Support\Facades\DB;

/**
 *
 * Servicio que gestiona la consulta de Existencias por Celda.
 *
 * @category     PagoFacil
 * @package      ExistenciaCelda
 * @author       Equipo PagoFacil
 * @fecha        26-02-2026
 */
class ExistenciaCeldaService
{
    private string $pcConexion = 'mysqlNegocio';

    /**
     * Lista existencias.
     *
     * @method      Listar()
     * @author      Equipo PagoFacil
     * @fecha       26-02-2026
     */
    public function Listar()
    {
        return DB::connection($this->pcConexion)
            ->table('EXISTENCIACELDA')
            ->orderBy('ExistenciaCelda', 'desc')
            ->get();
    }

    /**
     * Lista existencias por Celda.
     *
     * @method      ListarPorCelda()
     * @author      Equipo PagoFacil
     * @fecha       26-02-2026
     * @param       int $tnCelda
     */
    public function ListarPorCelda(int $tnCelda)
    {
        return DB::connection($this->pcConexion)
            ->table('EXISTENCIACELDA')
            ->where('Celda', $tnCelda)
            ->orderBy('ExistenciaCelda', 'desc')
            ->get();
    }
}

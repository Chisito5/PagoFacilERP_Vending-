<?php

namespace App\Modulos\PlanogramaCelda\Services;

use Illuminate\Support\Facades\DB;

/**
 *
 * Servicio que gestiona la consulta de Planograma por Celda.
 *
 * @category     PagoFacil
 * @package      PlanogramaCelda
 * @author       Equipo PagoFacil
 * @fecha        26-02-2026
 */
class PlanogramaCeldaService
{
    private string $pcConexion = 'mysqlNegocio';

    /**
     * Lista planograma celda.
     *
     * @method      Listar()
     * @author      Equipo PagoFacil
     * @fecha       26-02-2026
     */
    public function Listar()
    {
        return DB::connection($this->pcConexion)
            ->table('PLANOGRAMACELDA')
            ->orderBy('PlanogramaCelda', 'desc')
            ->get();
    }

    /**
     * Lista planograma celda por Celda.
     *
     * @method      ListarPorCelda()
     * @author      Equipo PagoFacil
     * @fecha       26-02-2026
     * @param       int $tnCelda
     */
    public function ListarPorCelda(int $tnCelda)
    {
        return DB::connection($this->pcConexion)
            ->table('PLANOGRAMACELDA')
            ->where('Celda', $tnCelda)
            ->orderBy('PlanogramaCelda', 'desc')
            ->get();
    }

    /**
     * Lista planograma celda por Planograma.
     *
     * @method      ListarPorPlanograma()
     * @author      Equipo PagoFacil
     * @fecha       26-02-2026
     * @param       int $tnPlanograma
     */
    public function ListarPorPlanograma(int $tnPlanograma)
    {
        return DB::connection($this->pcConexion)
            ->table('PLANOGRAMACELDA')
            ->where('Planograma', $tnPlanograma)
            ->orderBy('PlanogramaCelda', 'desc')
            ->get();
    }
}

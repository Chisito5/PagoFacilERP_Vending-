<?php

namespace App\Modulos\Maquina\Services;

use Illuminate\Support\Facades\DB;

/**
 *
 * Servicio que gestiona la consulta de Máquinas y Celdas.
 *
 * @category     PagoFacil
 * @package      Maquina
 * @author       Equipo PagoFacil
 * @fecha        26-02-2026
 */
class MaquinaService
{
    private string $pcConexion = 'mysqlNegocio';

    /**
     * Lista máquinas.
     *
     * @method      Listar()
     * @author      Equipo PagoFacil
     * @fecha       26-02-2026
     */
    public function Listar()
    {
        return DB::connection($this->pcConexion)
            ->table('MAQUINA')
            ->orderBy('Maquina', 'desc')
            ->get();
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
        return DB::connection($this->pcConexion)
            ->table('CELDA')
            ->where('Maquina', $tnMaquina)
            ->orderBy('Celda', 'asc')
            ->get();
    }
}

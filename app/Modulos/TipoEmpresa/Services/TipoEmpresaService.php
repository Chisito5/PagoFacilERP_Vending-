<?php

namespace App\Modulos\TipoEmpresa\Services;

use Illuminate\Support\Facades\DB;

/**
 *
 * Servicio que gestiona la consulta de Tipos de Empresa.
 *
 * @category     PagoFacil
 * @package      TipoEmpresa
 * @author       Equipo PagoFacil
 * @fecha        26-02-2026
 */
class TipoEmpresaService
{
    /**
     * Lista tipos de empresa.
     *
     * @method      Listar()
     * @author      Equipo PagoFacil
     * @fecha       26-02-2026
     * @return      mixed
     */
    public function Listar()
    {
        return DB::connection('mysqlNegocio')
            ->table('TIPOEMPRESA')
            ->select([
                'TipoEmpresa',
                'NombreTipoEmpresa',
                'Descripcion',
                'Estado',
                'Usr',
                'UsrFecha',
                'UsrHora',
            ])
            ->orderBy('TipoEmpresa', 'asc')
            ->get();
    }
}

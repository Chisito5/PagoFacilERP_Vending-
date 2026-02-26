<?php

namespace App\Modulos\Estado\Services;

use Illuminate\Support\Facades\DB;

/**
 *
 * Servicio que gestiona la consulta de Estados.
 *
 * @category     PagoFacil
 * @package      Estado
 * @author       Equipo PagoFacil
 * @fecha        26-02-2026
 */
class EstadoService
{
    /**
     * Lista estados (opcionalmente filtrando por Entidad).
     *
     * @method      Listar()
     * @author      Equipo PagoFacil
     * @fecha       26-02-2026
     * @param       string|null $tcEntidad
     * @return      mixed
     */
    public function Listar(?string $tcEntidad = null)
    {
        $loConsulta = DB::connection('mysqlNegocio')
            ->table('ESTADO')
            ->select([
                'Estado',
                'Entidad',
                'CodigoEstado',
                'NombreEstado',
                'Descripcion',
                'Orden',
                'Usr',
                'UsrFecha',
                'UsrHora',
            ])
            ->orderBy('Orden', 'asc')
            ->orderBy('Estado', 'asc');

        if ($tcEntidad !== null && $tcEntidad !== '') {
            $loConsulta->where('Entidad', $tcEntidad);
        }

        return $loConsulta->get();
    }
}

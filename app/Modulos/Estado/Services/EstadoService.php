<?php

namespace App\Modulos\Estado\Services;

use Illuminate\Support\Facades\DB;

class EstadoService
{
    public function Listar(?string $entidad = null)
    {
        $query = DB::connection('mysqlNegocio')
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

        if ($entidad !== null && $entidad !== '') {
            $query->where('Entidad', $entidad);
        }

        return $query->get();
    }
}

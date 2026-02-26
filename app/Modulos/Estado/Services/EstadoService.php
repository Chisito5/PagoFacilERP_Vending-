<?php

namespace App\Modulos\Estado\Services;

use Illuminate\Support\Facades\DB;

class EstadoService
{
    public function Listar(?string $entidad = null)
    {
        $query = DB::connection('mysqlNegocio')
            ->table('estado')
            ->select([
                'IdEstado',
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
            ->orderBy('IdEstado', 'asc');

        if ($entidad !== null && $entidad !== '') {
            $query->where('Entidad', $entidad);
        }

        return $query->get();
    }
}
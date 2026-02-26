<?php

namespace App\Modulos\TipoEmpresa\Services;

use Illuminate\Support\Facades\DB;

class TipoEmpresaService
{
    public function Listar()
    {
        return DB::connection('mysqlNegocio')
            ->table('tipoempresa')
            ->select([
                'IdTipoEmpresa',
                'NombreTipoEmpresa',
                'Descripcion',
                'IdEstado',
                'Usr',
                'UsrFecha',
                'UsrHora',
            ])
            ->orderBy('IdTipoEmpresa', 'asc')
            ->get();
    }
}
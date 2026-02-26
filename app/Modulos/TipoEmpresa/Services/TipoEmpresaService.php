<?php

namespace App\Modulos\TipoEmpresa\Services;

use Illuminate\Support\Facades\DB;

class TipoEmpresaService
{
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

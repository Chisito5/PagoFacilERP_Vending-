<?php

namespace App\Modulos\Empresa\Services;

use Illuminate\Support\Facades\DB;

class EmpresaService
{
    public function Listar()
    {
        return DB::connection('mysqlNegocio')
            ->table('empresa')
            ->select([
                'IdEmpresa',
                'CodigoEmpresa',
                'RazonSocial',
                'NombreComercial',
                'Nit',
                'Telefono',
                'Correo',
                'DireccionFiscal',
                'IdTipoEmpresa',
                'IdEstado',
                'IdPlantillaVisualPredeterminada',
                'Usr',
                'UsrFecha',
                'UsrHora',
            ])
            ->orderBy('IdEmpresa', 'desc')
            ->limit(50)
            ->get();
    }
}
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

    public function Crear(array $datos)
    {
        $insert = [
            'CodigoEmpresa' => $datos['CodigoEmpresa'],
            'RazonSocial' => $datos['RazonSocial'],
            'NombreComercial' => $datos['NombreComercial'] ?? null,
            'Nit' => $datos['Nit'] ?? null,
            'Telefono' => $datos['Telefono'] ?? null,
            'Correo' => $datos['Correo'] ?? null,
            'DireccionFiscal' => $datos['DireccionFiscal'] ?? null,
            'IdTipoEmpresa' => (int)$datos['IdTipoEmpresa'],
            'IdEstado' => (int)$datos['IdEstado'],

            // Si existe en tu tabla, lo dejamos con null si no viene
            'IdPlantillaVisualPredeterminada' => $datos['IdPlantillaVisualPredeterminada'] ?? null,

            // Auditoría
            'Usr' => $datos['Usr'],
            'UsrFecha' => date('Y-m-d'),
            'UsrHora' => date('H:i:s'),
        ];

        $id = DB::connection('mysqlNegocio')
            ->table('empresa')
            ->insertGetId($insert);

        return DB::connection('mysqlNegocio')
            ->table('empresa')
            ->where('IdEmpresa', $id)
            ->first();
    }
}

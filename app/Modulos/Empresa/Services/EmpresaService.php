<?php

namespace App\Modulos\Empresa\Services;

use Illuminate\Support\Facades\DB;

class EmpresaService
{
    public function Listar()
    {
        return DB::connection('mysqlNegocio')
            ->table('EMPRESA')
            ->select([
                'Empresa',
                'CodigoEmpresa',
                'RazonSocial',
                'NombreComercial',
                'Nit',
                'Telefono',
                'Correo',
                'DireccionFiscal',
                'TipoEmpresa',
                'Estado',
                'PlantillaVisualPredeterminada',
                'Usr',
                'UsrFecha',
                'UsrHora',
            ])
            ->orderBy('Empresa', 'desc')
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

            'TipoEmpresa' => (int)$datos['TipoEmpresa'],
            'Estado' => (int)$datos['Estado'],
            'PlantillaVisualPredeterminada' => $datos['PlantillaVisualPredeterminada'] ?? null,

            // Auditoría
            'Usr' => (int)($datos['Usr'] ?? 0),
            'UsrFecha' => date('Y-m-d'),
            'UsrHora' => date('H:i:s'),
        ];

        $empresa = DB::connection('mysqlNegocio')
            ->table('EMPRESA')
            ->insertGetId($insert);

        return DB::connection('mysqlNegocio')
            ->table('EMPRESA')
            ->where('Empresa', $empresa)
            ->first();
    }
}

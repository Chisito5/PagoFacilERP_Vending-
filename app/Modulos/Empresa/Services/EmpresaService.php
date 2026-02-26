<?php

namespace App\Modulos\Empresa\Services;

use Illuminate\Support\Facades\DB;

/**
 *
 * Servicio que gestiona la lógica de negocio de Empresa.
 *
 * @category     PagoFacil
 * @package      Empresa
 * @author       Equipo PagoFacil
 * @fecha        26-02-2026
 */
class EmpresaService
{
    /**
     * Lista empresas.
     *
     * @method      Listar()
     * @author      Equipo PagoFacil
     * @fecha       26-02-2026
     * @return      mixed
     */
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

    /**
     * Crea una empresa.
     *
     * @method      Crear()
     * @author      Equipo PagoFacil
     * @fecha       26-02-2026
     * @param       array $taDatos
     * @return      mixed
     */
    public function Crear(array $taDatos)
    {
        $laInsert = [
            'CodigoEmpresa' => $taDatos['CodigoEmpresa'],
            'RazonSocial' => $taDatos['RazonSocial'],
            'NombreComercial' => $taDatos['NombreComercial'] ?? null,
            'Nit' => $taDatos['Nit'] ?? null,
            'Telefono' => $taDatos['Telefono'] ?? null,
            'Correo' => $taDatos['Correo'] ?? null,
            'DireccionFiscal' => $taDatos['DireccionFiscal'] ?? null,

            'TipoEmpresa' => (int)$taDatos['TipoEmpresa'],
            'Estado' => (int)$taDatos['Estado'],
            'PlantillaVisualPredeterminada' => $taDatos['PlantillaVisualPredeterminada'] ?? null,

            // Auditoría
            'Usr' => (int)($taDatos['Usr'] ?? 0),
            'UsrFecha' => date('Y-m-d'),
            'UsrHora' => date('H:i:s'),
        ];

        $lnEmpresa = DB::connection('mysqlNegocio')
            ->table('EMPRESA')
            ->insertGetId($laInsert);

        return DB::connection('mysqlNegocio')
            ->table('EMPRESA')
            ->where('Empresa', $lnEmpresa)
            ->first();
    }
}

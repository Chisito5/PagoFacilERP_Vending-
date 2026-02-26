<?php

namespace App\Modulos\Producto\Services;

use Illuminate\Support\Facades\DB;

/**
 *
 * Servicio que gestiona la consulta de Productos.
 *
 * @category     PagoFacil
 * @package      Producto
 * @author       Equipo PagoFacil
 * @fecha        26-02-2026
 */
class ProductoService
{
    /**
     * Lista productos (opcionalmente filtrando por Empresa).
     *
     * @method      Listar()
     * @author      Equipo PagoFacil
     * @fecha       26-02-2026
     * @param       int|null $tnEmpresa
     * @return      mixed
     */
    public function Listar(?int $tnEmpresa = null)
    {
        $loConsulta = DB::connection('mysqlNegocio')
            ->table('PRODUCTO')
            ->select([
                'Producto',
                'Empresa',
                'CodigoSku',
                'CodigoBarra',
                'NombreProducto',
                'Descripcion',
                'Marca',
                'ContenidoCantidad',
                'UnidadMedidaContenido',
                'PesoGramos',
                'SubgrupoProducto',
                'Estado',
                'Usr',
                'UsrFecha',
                'UsrHora',
            ])
            ->orderBy('Producto', 'asc');

        if (!is_null($tnEmpresa)) {
            $loConsulta->where('Empresa', $tnEmpresa);
        }

        return $loConsulta->get();
    }
}

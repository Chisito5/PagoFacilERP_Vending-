<?php

namespace App\Modulos\Stock\Services;

use Illuminate\Support\Facades\DB;

/**
 *
 * Servicio que gestiona la consulta de Stock por Máquina.
 *
 * @category     PagoFacil
 * @package      Stock
 * @author       Equipo PagoFacil
 * @fecha        26-02-2026
 */
class StockService
{
    /**
     * Devuelve stock por máquina (celdas + producto + existencia).
     *
     * @method      StockPorMaquina()
     * @author      Equipo PagoFacil
     * @fecha       26-02-2026
     * @param       int $tnMaquina
     * @return      array
     */
    public function StockPorMaquina(int $tnMaquina): array
    {
        $loRows = DB::connection('mysqlNegocio')
            ->table('CELDA as c')
            ->leftJoin('EXISTENCIACELDA as ec', 'ec.Celda', '=', 'c.Celda')
            ->leftJoin('PRODUCTOEMPRESA as pe', 'pe.ProductoEmpresa', '=', 'ec.ProductoEmpresa')
            ->leftJoin('PRODUCTO as p', 'p.Producto', '=', 'pe.Producto')
            ->leftJoin('LOTE as l', 'l.Lote', '=', 'ec.Lote')
            ->select([
                'c.Celda',
                'c.Maquina',
                'c.CodigoSeleccion',
                'c.Fila',
                'c.Columna',
                'c.CapacidadMaxima',
                'c.Estado as EstadoCelda',

                'ec.ExistenciaCelda',
                'ec.CantidadDisponible',
                'ec.CantidadReservada',
                'ec.Estado as EstadoExistencia',

                'pe.ProductoEmpresa',
                'pe.Empresa as EmpresaProducto',
                'pe.Producto as ProductoId',

                'p.CodigoSku',
                'p.CodigoBarra',
                'p.NombreProducto',

                'l.Lote',
                'l.CodigoLote',
                'l.FechaVencimiento',
            ])
            ->where('c.Maquina', $tnMaquina)
            ->orderBy('c.Fila')
            ->orderBy('c.Columna')
            ->get();

        return $loRows->map(fn($roFila) => (array)$roFila)->all();
    }
}

<?php

namespace App\Modulos\Stock\Services;

use Illuminate\Support\Facades\DB;

class StockService
{
    public function StockPorMaquina(int $IdMaquina): array
    {
        $rows = DB::connection('mysqlNegocio')
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
            ->where('c.Maquina', $IdMaquina)
            ->orderBy('c.Fila')
            ->orderBy('c.Columna')
            ->get();

        return $rows->map(fn($r) => (array)$r)->all();
    }
}

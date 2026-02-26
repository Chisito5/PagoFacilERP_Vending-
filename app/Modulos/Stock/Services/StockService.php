<?php

namespace App\Modulos\Stock\Services;

use Illuminate\Support\Facades\DB;

class StockService
{
    public function StockPorMaquina(int $IdMaquina): array
    {
        // Nota: todo en minúscula en MySQL (tablas), pero columnas están como en tu BD.
        // Usamos LEFT JOIN para que te liste celdas incluso si aún no tienen existencia.
        $rows = DB::connection('mysqlNegocio')
            ->table('celda as c')
            ->leftJoin('existenciacelda as ec', 'ec.IdCelda', '=', 'c.IdCelda')
            ->leftJoin('productoempresa as pe', 'pe.IdProductoEmpresa', '=', 'ec.IdProductoEmpresa')
            ->leftJoin('producto as p', 'p.IdProducto', '=', 'pe.IdProducto')
            ->leftJoin('lote as l', 'l.IdLote', '=', 'ec.IdLote')
            ->select([
                'c.IdCelda',
                'c.IdMaquina',
                'c.CodigoSeleccion',
                'c.Fila',
                'c.Columna',
                'c.CapacidadMaxima',

                'ec.IdExistenciaCelda',
                'ec.CantidadDisponible',
                'ec.CantidadReservada',
                'ec.IdEstado as IdEstadoExistencia',

                'pe.IdProductoEmpresa',
                'pe.IdEmpresa',
                'pe.IdProducto',

                'p.CodigoSku',
                'p.CodigoBarra',
                'p.NombreProducto',

                'l.IdLote',
                'l.CodigoLote',
                'l.FechaVencimiento',
            ])
            ->where('c.IdMaquina', $IdMaquina)
            ->orderBy('c.Fila')
            ->orderBy('c.Columna')
            ->get();

        return $rows->map(fn($r) => (array)$r)->all();
    }
}
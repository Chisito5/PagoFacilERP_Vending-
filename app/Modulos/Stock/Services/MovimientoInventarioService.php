<?php

namespace App\Modulos\Stock\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class MovimientoInventarioService
{
    /**
     * @param array<string,mixed> $laFiltros
     */
    public function Listar(array $laFiltros, int $tnPagina, int $tnTamanoPagina): LengthAwarePaginator
    {
        $tnTamanoPagina = max(1, min($tnTamanoPagina, 200));

        $loConsulta = DB::connection('mysqlNegocio')
            ->table('MOVIMIENTOINVENTARIO as mi')
            ->join('TIPOMOVIMIENTOINVENTARIO as tmi', 'tmi.TipoMovimientoInventario', '=', 'mi.TipoMovimientoInventario')
            ->leftJoin('CELDA as c', 'c.Celda', '=', 'mi.Celda')
            ->select([
                'mi.MovimientoInventario as IdMovimiento',
                'tmi.NombreTipoMovimientoInventario as Tipo',
                'mi.FechaHora as Fecha',
                'mi.Usr as Usuario',
                'mi.Maquina',
                'mi.Celda',
                'c.CodigoSeleccion',
                'mi.Lote',
                DB::raw('NULL as CantidadAntes'),
                DB::raw('(mi.Cantidad * COALESCE(tmi.Factor, 1)) as CantidadDelta'),
                DB::raw('NULL as CantidadDespues'),
                DB::raw("
                    CASE
                        WHEN mi.Reposicion IS NOT NULL THEN CONCAT('REPOSICION:', mi.Reposicion)
                        WHEN mi.Transaccion IS NOT NULL THEN CONCAT('TRANSACCION:', mi.Transaccion)
                        WHEN mi.Merma IS NOT NULL THEN CONCAT('MERMA:', mi.Merma)
                        ELSE COALESCE(mi.Observacion, '')
                    END as Referencia
                "),
            ])
            ->orderByDesc('mi.MovimientoInventario');

        if (!empty($laFiltros['Maquina'])) {
            $loConsulta->where('mi.Maquina', (int)$laFiltros['Maquina']);
        }
        if (!empty($laFiltros['Celda'])) {
            $loConsulta->where('mi.Celda', (int)$laFiltros['Celda']);
        }
        if (!empty($laFiltros['Lote'])) {
            $loConsulta->where('mi.Lote', (int)$laFiltros['Lote']);
        }
        if (!empty($laFiltros['TipoMovimiento'])) {
            $loConsulta->where('mi.TipoMovimientoInventario', (int)$laFiltros['TipoMovimiento']);
        }
        if (!empty($laFiltros['FechaDesde'])) {
            $loConsulta->whereDate('mi.FechaHora', '>=', (string)$laFiltros['FechaDesde']);
        }
        if (!empty($laFiltros['FechaHasta'])) {
            $loConsulta->whereDate('mi.FechaHora', '<=', (string)$laFiltros['FechaHasta']);
        }

        return $loConsulta->paginate($tnTamanoPagina, ['*'], 'Pagina', max(1, $tnPagina));
    }

    /**
     * @return array<int,mixed>
     */
    public function ListarUltimosPorCelda(int $tnMaquina, int $tnCelda, int $tnLimite = 20): array
    {
        $tnLimite = max(1, min($tnLimite, 100));

        return DB::connection('mysqlNegocio')
            ->table('MOVIMIENTOINVENTARIO as mi')
            ->join('TIPOMOVIMIENTOINVENTARIO as tmi', 'tmi.TipoMovimientoInventario', '=', 'mi.TipoMovimientoInventario')
            ->select([
                'mi.MovimientoInventario as IdMovimiento',
                'tmi.NombreTipoMovimientoInventario as Tipo',
                'mi.FechaHora as Fecha',
                'mi.Usr as Usuario',
                'mi.Maquina',
                'mi.Celda',
                'mi.Lote',
                DB::raw('(mi.Cantidad * COALESCE(tmi.Factor, 1)) as CantidadDelta'),
                DB::raw("
                    CASE
                        WHEN mi.Reposicion IS NOT NULL THEN CONCAT('REPOSICION:', mi.Reposicion)
                        WHEN mi.Transaccion IS NOT NULL THEN CONCAT('TRANSACCION:', mi.Transaccion)
                        WHEN mi.Merma IS NOT NULL THEN CONCAT('MERMA:', mi.Merma)
                        ELSE COALESCE(mi.Observacion, '')
                    END as Referencia
                "),
            ])
            ->where('mi.Maquina', $tnMaquina)
            ->where('mi.Celda', $tnCelda)
            ->orderByDesc('mi.MovimientoInventario')
            ->limit($tnLimite)
            ->get()
            ->all();
    }
}

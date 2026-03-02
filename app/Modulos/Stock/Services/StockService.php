<?php

namespace App\Modulos\Stock\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class StockService
{
    public function __construct(private MovimientoInventarioService $toMovimientoService)
    {
    }

    /**
     * @return array<string,mixed>|null
     */
    public function StockPorSeleccion(int $tnMaquina, string $tcCodigoSeleccion): ?array
    {
        $loFila = DB::connection('mysqlNegocio')
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
            ->where('c.CodigoSeleccion', $tcCodigoSeleccion)
            ->first();

        if (!$loFila) {
            return null;
        }

        $laStock = (array)$loFila;

        $laMovimientos = $this->toMovimientoService->ListarUltimosPorCelda(
            $tnMaquina,
            (int)$loFila->Celda,
            20
        );

        return [
            'Stock' => $laStock,
            'UltimosMovimientos' => $laMovimientos,
        ];
    }

    public function StockPorMaquina(
        int $tnMaquina,
        ?int $tnCelda,
        ?string $tcCodigoSeleccion,
        ?int $tnLote,
        int $tnPagina,
        int $tnTamanoPagina
    ): LengthAwarePaginator {
        $tnTamanoPagina = max(1, min($tnTamanoPagina, 200));

        $loConsulta = DB::connection('mysqlNegocio')
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
            ->orderBy('c.Columna');

        if ($tnCelda !== null && $tnCelda > 0) {
            $loConsulta->where('c.Celda', $tnCelda);
        }
        if ($tcCodigoSeleccion !== null && trim($tcCodigoSeleccion) !== '') {
            $loConsulta->where('c.CodigoSeleccion', trim($tcCodigoSeleccion));
        }
        if ($tnLote !== null && $tnLote > 0) {
            $loConsulta->where('ec.Lote', $tnLote);
        }

        return $loConsulta->paginate($tnTamanoPagina, ['*'], 'Pagina', max(1, $tnPagina));
    }

    /**
     * @param array<string,mixed> $laFiltros
     */
    public function Movimientos(array $laFiltros, int $tnPagina, int $tnTamanoPagina): LengthAwarePaginator
    {
        return $this->toMovimientoService->Listar($laFiltros, $tnPagina, $tnTamanoPagina);
    }
}

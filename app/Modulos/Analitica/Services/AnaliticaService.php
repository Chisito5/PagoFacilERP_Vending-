<?php

namespace App\Modulos\Analitica\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class AnaliticaService
{
    private string $pcConexion = 'mysqlNegocio';

    /** @param array<string,mixed> $taFiltros */
    public function ventas(array $taFiltros, int $tnPagina = 1, int $tnTamanoPagina = 20): LengthAwarePaginator
    {
        $tnTamanoPagina = max(1, min($tnTamanoPagina, 200));

        $to = DB::connection($this->pcConexion)
            ->table('VENTA as v')
            ->join('MAQUINA as m', 'm.Maquina', '=', 'v.Maquina')
            ->leftJoin('UBICACION as u', 'u.Ubicacion', '=', 'm.UbicacionActual')
            ->join('PRODUCTOEMPRESA as pe', 'pe.ProductoEmpresa', '=', 'v.ProductoEmpresa')
            ->join('PRODUCTO as p', 'p.Producto', '=', 'pe.Producto')
            ->select([
                'v.Venta', 'v.FechaVenta', 'v.Maquina', 'v.Celda', 'v.ProductoEmpresa',
                'p.NombreProducto', 'v.Cantidad', 'v.PrecioUnitario',
                DB::raw('(v.Cantidad * v.PrecioUnitario) as TotalLinea'),
                'u.Empresa'
            ])
            ->orderByDesc('v.Venta');

        $this->aplicarFiltrosVenta($to, $taFiltros);

        return $to->paginate($tnTamanoPagina, ['*'], 'Pagina', max(1, $tnPagina));
    }

    /** @param array<string,mixed> $taFiltros */
    public function rotacion(array $taFiltros, int $tnPagina = 1, int $tnTamanoPagina = 20): LengthAwarePaginator
    {
        $tnTamanoPagina = max(1, min($tnTamanoPagina, 200));

        $to = DB::connection($this->pcConexion)
            ->table('VENTA as v')
            ->join('PRODUCTOEMPRESA as pe', 'pe.ProductoEmpresa', '=', 'v.ProductoEmpresa')
            ->join('PRODUCTO as p', 'p.Producto', '=', 'pe.Producto')
            ->join('MAQUINA as m', 'm.Maquina', '=', 'v.Maquina')
            ->leftJoin('UBICACION as u', 'u.Ubicacion', '=', 'm.UbicacionActual')
            ->select([
                'v.ProductoEmpresa',
                'p.NombreProducto',
                DB::raw('SUM(v.Cantidad) as UnidadesVendidas'),
                DB::raw('SUM(v.Cantidad * v.PrecioUnitario) as IngresoTotal'),
                'u.Empresa'
            ])
            ->groupBy('v.ProductoEmpresa', 'p.NombreProducto', 'u.Empresa')
            ->orderByDesc(DB::raw('SUM(v.Cantidad)'));

        $this->aplicarFiltrosVenta($to, $taFiltros);

        return $to->paginate($tnTamanoPagina, ['*'], 'Pagina', max(1, $tnPagina));
    }

    /** @param array<string,mixed> $taFiltros */
    public function stockout(array $taFiltros, int $tnPagina = 1, int $tnTamanoPagina = 20): LengthAwarePaginator
    {
        $tnTamanoPagina = max(1, min($tnTamanoPagina, 200));

        $to = DB::connection($this->pcConexion)
            ->table('EXISTENCIACELDA as ec')
            ->join('CELDA as c', 'c.Celda', '=', 'ec.Celda')
            ->join('MAQUINA as m', 'm.Maquina', '=', 'c.Maquina')
            ->leftJoin('UBICACION as u', 'u.Ubicacion', '=', 'm.UbicacionActual')
            ->leftJoin('PRODUCTOEMPRESA as pe', 'pe.ProductoEmpresa', '=', 'ec.ProductoEmpresa')
            ->leftJoin('PRODUCTO as p', 'p.Producto', '=', 'pe.Producto')
            ->select([
                'c.Maquina', 'c.Celda', 'c.CodigoSeleccion', 'ec.ProductoEmpresa',
                'p.NombreProducto', 'ec.CantidadDisponible', 'ec.CantidadReservada',
                'u.Empresa'
            ])
            ->where('ec.CantidadDisponible', '<=', 0)
            ->orderBy('c.Maquina')
            ->orderBy('c.CodigoSeleccion');

        $this->aplicarFiltrosStock($to, $taFiltros);

        return $to->paginate($tnTamanoPagina, ['*'], 'Pagina', max(1, $tnPagina));
    }

    /** @param array<string,mixed> $taFiltros */
    public function rentabilidad(array $taFiltros, int $tnPagina = 1, int $tnTamanoPagina = 20): LengthAwarePaginator
    {
        $tnTamanoPagina = max(1, min($tnTamanoPagina, 200));

        $to = DB::connection($this->pcConexion)
            ->table('VENTA as v')
            ->join('PRODUCTOEMPRESA as pe', 'pe.ProductoEmpresa', '=', 'v.ProductoEmpresa')
            ->join('PRODUCTO as p', 'p.Producto', '=', 'pe.Producto')
            ->join('MAQUINA as m', 'm.Maquina', '=', 'v.Maquina')
            ->leftJoin('UBICACION as u', 'u.Ubicacion', '=', 'm.UbicacionActual')
            ->leftJoin('MOVIMIENTOINVENTARIO as mi', function ($join) {
                $join->on('mi.ProductoEmpresa', '=', 'v.ProductoEmpresa');
                $join->on('mi.Lote', '=', 'v.Lote');
            })
            ->select([
                'v.ProductoEmpresa',
                'p.NombreProducto',
                'u.Empresa',
                DB::raw('SUM(v.Cantidad * v.PrecioUnitario) as Ingreso'),
                DB::raw('SUM(v.Cantidad * COALESCE(mi.CostoUnitario,0)) as CostoAproximado'),
                DB::raw('SUM(v.Cantidad * v.PrecioUnitario) - SUM(v.Cantidad * COALESCE(mi.CostoUnitario,0)) as MargenAproximado')
            ])
            ->groupBy('v.ProductoEmpresa', 'p.NombreProducto', 'u.Empresa')
            ->orderByDesc(DB::raw('SUM(v.Cantidad * v.PrecioUnitario) - SUM(v.Cantidad * COALESCE(mi.CostoUnitario,0))'));

        $this->aplicarFiltrosVenta($to, $taFiltros);

        return $to->paginate($tnTamanoPagina, ['*'], 'Pagina', max(1, $tnPagina));
    }

    /** @param array<string,mixed> $taFiltros */
    public function mermas(array $taFiltros, int $tnPagina = 1, int $tnTamanoPagina = 20): LengthAwarePaginator
    {
        $tnTamanoPagina = max(1, min($tnTamanoPagina, 200));

        $to = DB::connection($this->pcConexion)
            ->table('MERMA as m')
            ->join('MERMADETALLE as md', 'md.Merma', '=', 'm.Merma')
            ->join('PRODUCTOEMPRESA as pe', 'pe.ProductoEmpresa', '=', 'md.ProductoEmpresa')
            ->join('PRODUCTO as p', 'p.Producto', '=', 'pe.Producto')
            ->join('MAQUINA as maq', 'maq.Maquina', '=', 'm.Maquina')
            ->leftJoin('UBICACION as u', 'u.Ubicacion', '=', 'maq.UbicacionActual')
            ->select([
                'm.Merma', 'm.Maquina', 'm.FechaHora', 'm.Estado',
                'md.ProductoEmpresa', 'p.NombreProducto',
                DB::raw('SUM(md.CantidadRetirada) as CantidadRetiradaTotal'),
                'u.Empresa'
            ])
            ->groupBy('m.Merma', 'm.Maquina', 'm.FechaHora', 'm.Estado', 'md.ProductoEmpresa', 'p.NombreProducto', 'u.Empresa')
            ->orderByDesc('m.Merma');

        $this->aplicarFiltrosMerma($to, $taFiltros);

        return $to->paginate($tnTamanoPagina, ['*'], 'Pagina', max(1, $tnPagina));
    }

    /** @param array<string,mixed> $taFiltros */
    public function resumen(array $taFiltros): array
    {
        $laVentas = $this->ventasTotales($taFiltros);
        $laMermas = $this->mermasTotales($taFiltros);
        $laStockout = $this->stockoutTotales($taFiltros);

        return [
            'VentasCantidad' => (int)$laVentas['VentasCantidad'],
            'VentasMonto' => (float)$laVentas['VentasMonto'],
            'MermasCantidad' => (int)$laMermas['MermasCantidad'],
            'MermasUnidades' => (int)$laMermas['MermasUnidades'],
            'StockoutCeldas' => (int)$laStockout['StockoutCeldas'],
            'RentabilidadAproximada' => (float)$laVentas['VentasMonto'] - (float)$laMermas['MermasCostoAproximado'],
        ];
    }

    /** @param array<string,mixed> $taFiltros */
    private function ventasTotales(array $taFiltros): array
    {
        $to = DB::connection($this->pcConexion)
            ->table('VENTA as v')
            ->join('MAQUINA as m', 'm.Maquina', '=', 'v.Maquina')
            ->leftJoin('UBICACION as u', 'u.Ubicacion', '=', 'm.UbicacionActual');

        $this->aplicarFiltrosVenta($to, $taFiltros);

        $lo = $to->selectRaw('COUNT(*) as VentasCantidad, COALESCE(SUM(v.Cantidad * v.PrecioUnitario),0) as VentasMonto')->first();

        return [
            'VentasCantidad' => (int)($lo->VentasCantidad ?? 0),
            'VentasMonto' => (float)($lo->VentasMonto ?? 0),
        ];
    }

    /** @param array<string,mixed> $taFiltros */
    private function mermasTotales(array $taFiltros): array
    {
        $to = DB::connection($this->pcConexion)
            ->table('MERMA as m')
            ->join('MERMADETALLE as md', 'md.Merma', '=', 'm.Merma')
            ->join('MAQUINA as maq', 'maq.Maquina', '=', 'm.Maquina')
            ->leftJoin('UBICACION as u', 'u.Ubicacion', '=', 'maq.UbicacionActual')
            ->leftJoin('MOVIMIENTOINVENTARIO as mi', function ($join) {
                $join->on('mi.Merma', '=', 'm.Merma');
                $join->on('mi.ProductoEmpresa', '=', 'md.ProductoEmpresa');
            });

        $this->aplicarFiltrosMerma($to, $taFiltros);

        $lo = $to->selectRaw('COUNT(DISTINCT m.Merma) as MermasCantidad, COALESCE(SUM(md.CantidadRetirada),0) as MermasUnidades, COALESCE(SUM(md.CantidadRetirada * COALESCE(mi.CostoUnitario,0)),0) as MermasCostoAproximado')->first();

        return [
            'MermasCantidad' => (int)($lo->MermasCantidad ?? 0),
            'MermasUnidades' => (int)($lo->MermasUnidades ?? 0),
            'MermasCostoAproximado' => (float)($lo->MermasCostoAproximado ?? 0),
        ];
    }

    /** @param array<string,mixed> $taFiltros */
    private function stockoutTotales(array $taFiltros): array
    {
        $to = DB::connection($this->pcConexion)
            ->table('EXISTENCIACELDA as ec')
            ->join('CELDA as c', 'c.Celda', '=', 'ec.Celda')
            ->join('MAQUINA as m', 'm.Maquina', '=', 'c.Maquina')
            ->leftJoin('UBICACION as u', 'u.Ubicacion', '=', 'm.UbicacionActual')
            ->where('ec.CantidadDisponible', '<=', 0);

        $this->aplicarFiltrosStock($to, $taFiltros);

        $lo = $to->selectRaw('COUNT(*) as StockoutCeldas')->first();
        return ['StockoutCeldas' => (int)($lo->StockoutCeldas ?? 0)];
    }

    /** @param array<string,mixed> $taFiltros */
    private function aplicarFiltrosVenta($toConsulta, array $taFiltros): void
    {
        if (!empty($taFiltros['Empresa'])) {
            $toConsulta->where('u.Empresa', (int)$taFiltros['Empresa']);
        }
        if (!empty($taFiltros['Maquina'])) {
            $toConsulta->where('v.Maquina', (int)$taFiltros['Maquina']);
        }
        if (!empty($taFiltros['Producto'])) {
            $toConsulta->where('v.ProductoEmpresa', (int)$taFiltros['Producto']);
        }
        if (!empty($taFiltros['FechaDesde'])) {
            $toConsulta->whereDate('v.FechaVenta', '>=', (string)$taFiltros['FechaDesde']);
        }
        if (!empty($taFiltros['FechaHasta'])) {
            $toConsulta->whereDate('v.FechaVenta', '<=', (string)$taFiltros['FechaHasta']);
        }
    }

    /** @param array<string,mixed> $taFiltros */
    private function aplicarFiltrosStock($toConsulta, array $taFiltros): void
    {
        if (!empty($taFiltros['Empresa'])) {
            $toConsulta->where('u.Empresa', (int)$taFiltros['Empresa']);
        }
        if (!empty($taFiltros['Maquina'])) {
            $toConsulta->where('c.Maquina', (int)$taFiltros['Maquina']);
        }
        if (!empty($taFiltros['Producto'])) {
            $toConsulta->where('ec.ProductoEmpresa', (int)$taFiltros['Producto']);
        }
    }

    /** @param array<string,mixed> $taFiltros */
    private function aplicarFiltrosMerma($toConsulta, array $taFiltros): void
    {
        if (!empty($taFiltros['Empresa'])) {
            $toConsulta->where('u.Empresa', (int)$taFiltros['Empresa']);
        }
        if (!empty($taFiltros['Maquina'])) {
            $toConsulta->where('m.Maquina', (int)$taFiltros['Maquina']);
        }
        if (!empty($taFiltros['Producto'])) {
            $toConsulta->where('md.ProductoEmpresa', (int)$taFiltros['Producto']);
        }
        if (!empty($taFiltros['FechaDesde'])) {
            $toConsulta->whereDate('m.FechaHora', '>=', (string)$taFiltros['FechaDesde']);
        }
        if (!empty($taFiltros['FechaHasta'])) {
            $toConsulta->whereDate('m.FechaHora', '<=', (string)$taFiltros['FechaHasta']);
        }
    }
}

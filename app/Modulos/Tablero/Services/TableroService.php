<?php

namespace App\Modulos\Tablero\Services;

use App\Modulos\Autenticacion\Services\PermisoService;
use App\Soporte\ControlVersionService;
use App\Support\EstadoCatalogo;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class TableroService
{
    private string $pcConexion = 'mysqlNegocio';

    public function __construct(
        private EstadoCatalogo $toEstadoCatalogo,
        private PermisoService $toPermisoService,
        private ControlVersionService $toControlVersionService
    ) {
    }

    /**
     * @return array{MaquinasActivas:int,AlertasStockBajo:int,ReservasActivas:int,VentasDia:int,ReposicionesDia:int}
     */
    public function Resumen(): array
    {
        $tnEstadoGeneralActivo = $this->obtenerEstadoSeguro('GENERAL', 1, 1);
        $tnEstadoReservaCreada = $this->obtenerEstadoSeguro('RESERVA', 1, 1);
        $tnEstadoVentaActiva = $this->obtenerEstadoSeguro('VENTA', 1, 1);
        $tnEstadoReposicionRegistrada = $this->obtenerEstadoSeguro('REPOSICION', 1, 1);
        $tnEstadoReposicionCerrada = $this->obtenerEstadoSeguro('REPOSICION', 2, 1);

        $tnMaquinasActivas = DB::connection($this->pcConexion)->table('MAQUINA')->where('Estado', $tnEstadoGeneralActivo)->count();

        $loUltimoPlanograma = DB::connection($this->pcConexion)
            ->table('PLANOGRAMA')
            ->selectRaw('Maquina, MAX(VersionPlanograma) as VersionPlanograma')
            ->where('Estado', $tnEstadoGeneralActivo)
            ->groupBy('Maquina');

        $tnAlertasStockBajo = DB::connection($this->pcConexion)
            ->table('PLANOGRAMA as p')
            ->joinSub($loUltimoPlanograma, 'up', function ($toJoin): void {
                $toJoin->on('up.Maquina', '=', 'p.Maquina')->on('up.VersionPlanograma', '=', 'p.VersionPlanograma');
            })
            ->join('PLANOGRAMACELDA as pc', 'pc.Planograma', '=', 'p.Planograma')
            ->join('CELDA as c', 'c.Celda', '=', 'pc.Celda')
            ->leftJoin('EXISTENCIACELDA as ec', function ($toJoin) use ($tnEstadoGeneralActivo): void {
                $toJoin->on('ec.Celda', '=', 'c.Celda')->where('ec.Estado', '=', $tnEstadoGeneralActivo);
            })
            ->where('p.Estado', $tnEstadoGeneralActivo)
            ->where('pc.Estado', $tnEstadoGeneralActivo)
            ->where('c.Estado', $tnEstadoGeneralActivo)
            ->where('pc.StockMinimo', '>', 0)
            ->whereRaw('COALESCE(ec.CantidadDisponible, 0) <= pc.StockMinimo')
            ->distinct('c.Celda')
            ->count('c.Celda');

        $tnReservasActivas = DB::connection($this->pcConexion)->table('RESERVA')->where('Estado', $tnEstadoReservaCreada)->where('ExpiraEn', '>', now())->count();
        $tnVentasDia = DB::connection($this->pcConexion)->table('VENTA')->where('Estado', $tnEstadoVentaActiva)->whereDate('FechaVenta', now()->toDateString())->count();
        $tnReposicionesDia = DB::connection($this->pcConexion)
            ->table('REPOSICION')
            ->whereIn('Estado', [$tnEstadoReposicionRegistrada, $tnEstadoReposicionCerrada])
            ->whereDate('FechaHoraInicio', now()->toDateString())
            ->count();

        return [
            'MaquinasActivas' => (int)$tnMaquinasActivas,
            'AlertasStockBajo' => (int)$tnAlertasStockBajo,
            'ReservasActivas' => (int)$tnReservasActivas,
            'VentasDia' => (int)$tnVentasDia,
            'ReposicionesDia' => (int)$tnReposicionesDia,
        ];
    }

    public function ejecutivoResumen(int $tnUsuarioSesion, ?int $tnEmpresa, ?string $tcFechaDesde, ?string $tcFechaHasta): array
    {
        [$tdDesde, $tdHasta] = $this->resolverRangoFechas($tcFechaDesde, $tcFechaHasta);
        $tnEstadoGeneralActivo = $this->obtenerEstadoSeguro('GENERAL', 1, 1);
        $tnEstadoVentaActiva = $this->obtenerEstadoSeguro('VENTA', 1, 1);
        $tnEstadoAlertaAbierta = $this->obtenerEstadoSeguro('ALERTA', 1, 1);

        $laMaquinasIds = $this->consultaBaseMaquinas($tnUsuarioSesion, $tnEmpresa, null, null)
            ->select('m.Maquina')
            ->distinct()
            ->pluck('m.Maquina')
            ->map(fn ($tnMaquina) => (int)$tnMaquina)
            ->all();

        if (count($laMaquinasIds) === 0) {
            return [
                'TotalMaquinas' => 0,
                'MaquinasActivas' => 0,
                'MaquinasConAlerta' => 0,
                'VentasTotal' => 0,
                'IngresosTotal' => 0.0,
                'MaquinaTopVentas' => null,
                'ProductoTopGlobal' => null,
            ];
        }

        $tnTotalMaquinas = count($laMaquinasIds);

        $tnMaquinasActivas = DB::connection($this->pcConexion)
            ->table('MAQUINA')
            ->whereIn('Maquina', $laMaquinasIds)
            ->where('Estado', $tnEstadoGeneralActivo)
            ->count();

        $tnMaquinasConAlerta = DB::connection($this->pcConexion)
            ->table('ALERTA')
            ->whereIn('Maquina', $laMaquinasIds)
            ->where('Estado', $tnEstadoAlertaAbierta)
            ->distinct()
            ->count('Maquina');

        $loVentasTotales = DB::connection($this->pcConexion)
            ->table('VENTA')
            ->whereIn('Maquina', $laMaquinasIds)
            ->where('Estado', $tnEstadoVentaActiva)
            ->whereBetween('FechaVenta', [$tdDesde->copy()->startOfDay(), $tdHasta->copy()->endOfDay()])
            ->selectRaw('COUNT(*) as VentasTotal, COALESCE(SUM(Cantidad * PrecioUnitario), 0) as IngresosTotal')
            ->first();

        $loMaquinaTop = DB::connection($this->pcConexion)
            ->table('VENTA as v')
            ->join('MAQUINA as m', 'm.Maquina', '=', 'v.Maquina')
            ->whereIn('v.Maquina', $laMaquinasIds)
            ->where('v.Estado', $tnEstadoVentaActiva)
            ->whereBetween('v.FechaVenta', [$tdDesde->copy()->startOfDay(), $tdHasta->copy()->endOfDay()])
            ->groupBy('v.Maquina', 'm.CodigoMaquina')
            ->orderByDesc(DB::raw('COUNT(*)'))
            ->orderByDesc(DB::raw('COALESCE(SUM(v.Cantidad * v.PrecioUnitario), 0)'))
            ->selectRaw('v.Maquina as IdMaquina, m.CodigoMaquina, COUNT(*) as Ventas, COALESCE(SUM(v.Cantidad * v.PrecioUnitario), 0) as Ingresos')
            ->first();

        $loProductoTop = DB::connection($this->pcConexion)
            ->table('VENTA as v')
            ->join('PRODUCTOEMPRESA as pe', 'pe.ProductoEmpresa', '=', 'v.ProductoEmpresa')
            ->join('PRODUCTO as p', 'p.Producto', '=', 'pe.Producto')
            ->whereIn('v.Maquina', $laMaquinasIds)
            ->where('v.Estado', $tnEstadoVentaActiva)
            ->whereBetween('v.FechaVenta', [$tdDesde->copy()->startOfDay(), $tdHasta->copy()->endOfDay()])
            ->groupBy('pe.Producto', 'p.NombreProducto')
            ->orderByDesc(DB::raw('SUM(v.Cantidad)'))
            ->orderByDesc(DB::raw('COALESCE(SUM(v.Cantidad * v.PrecioUnitario), 0)'))
            ->selectRaw('pe.Producto as IdProducto, p.NombreProducto, SUM(v.Cantidad) as Unidades, COALESCE(SUM(v.Cantidad * v.PrecioUnitario), 0) as Ingresos')
            ->first();

        return [
            'TotalMaquinas' => $tnTotalMaquinas,
            'MaquinasActivas' => (int)$tnMaquinasActivas,
            'MaquinasConAlerta' => (int)$tnMaquinasConAlerta,
            'VentasTotal' => (int)($loVentasTotales->VentasTotal ?? 0),
            'IngresosTotal' => (float)($loVentasTotales->IngresosTotal ?? 0),
            'MaquinaTopVentas' => $loMaquinaTop ? [
                'IdMaquina' => (int)$loMaquinaTop->IdMaquina,
                'CodigoMaquina' => (string)$loMaquinaTop->CodigoMaquina,
                'Ventas' => (int)$loMaquinaTop->Ventas,
                'Ingresos' => (float)$loMaquinaTop->Ingresos,
            ] : null,
            'ProductoTopGlobal' => $loProductoTop ? [
                'IdProducto' => (int)$loProductoTop->IdProducto,
                'NombreProducto' => (string)$loProductoTop->NombreProducto,
                'Unidades' => (int)$loProductoTop->Unidades,
                'Ingresos' => (float)$loProductoTop->Ingresos,
            ] : null,
        ];
    }

    public function ejecutivoMaquinas(
        int $tnUsuarioSesion,
        ?int $tnEmpresa,
        ?int $tnEstado,
        ?string $tcBusqueda,
        int $tnPagina,
        int $tnTamanoPagina,
        string $tcOrden,
        ?string $tcFechaDesde,
        ?string $tcFechaHasta
    ): array {
        [$tdDesde, $tdHasta] = $this->resolverRangoFechas($tcFechaDesde, $tcFechaHasta);
        $tnEstadoVentaActiva = $this->obtenerEstadoSeguro('VENTA', 1, 1);
        $tnEstadoAlertaAbierta = $this->obtenerEstadoSeguro('ALERTA', 1, 1);

        $tnPagina = max(1, $tnPagina);
        $tnTamanoPagina = max(1, min($tnTamanoPagina, 200));
        $tcOrden = strtolower(trim($tcOrden));
        if (!in_array($tcOrden, ['ventas', 'ingresos', 'alertas'], true)) {
            $tcOrden = 'ingresos';
        }

        $toConsulta = $this->consultaBaseMaquinas($tnUsuarioSesion, $tnEmpresa, $tnEstado, $tcBusqueda)
            ->leftJoin('MAQUINAESTADOOPERATIVO as meo', 'meo.Maquina', '=', 'm.Maquina')
            ->leftJoinSub($this->subconsultaVentasMaquina($tdDesde, $tdHasta, $tnEstadoVentaActiva), 've', function ($toJoin): void {
                $toJoin->on('ve.Maquina', '=', 'm.Maquina');
            })
            ->leftJoinSub($this->subconsultaAlertasMaquina($tnEstadoAlertaAbierta), 'al', function ($toJoin): void {
                $toJoin->on('al.Maquina', '=', 'm.Maquina');
            })
            ->selectRaw('m.Maquina, m.CodigoMaquina, m.Marca, m.Modelo, m.Estado as EstadoMaquina, m.UsrFecha, m.UsrHora, u.Latitud, u.Longitud, u.Direccion, COALESCE(meo.EstadoOperativo, "NO_DEFINIDO") as EstadoOperativo, COALESCE(ve.Transacciones, 0) as OrdenTransacciones, COALESCE(ve.Ingresos, 0) as OrdenIngresos, COALESCE(al.Abiertas, 0) as OrdenAlertas')
            ->groupBy('m.Maquina', 'm.CodigoMaquina', 'm.Marca', 'm.Modelo', 'm.Estado', 'm.UsrFecha', 'm.UsrHora', 'u.Latitud', 'u.Longitud', 'u.Direccion', 'meo.EstadoOperativo', 've.Transacciones', 've.Ingresos', 'al.Abiertas');

        match ($tcOrden) {
            'ventas' => $toConsulta->orderByDesc('OrdenTransacciones'),
            'alertas' => $toConsulta->orderByDesc('OrdenAlertas'),
            default => $toConsulta->orderByDesc('OrdenIngresos'),
        };
        $toConsulta->orderBy('m.Maquina');

        $toPaginador = $toConsulta->paginate($tnTamanoPagina, ['*'], 'Pagina', $tnPagina);
        $laItemsBase = $toPaginador->items();
        $laMaquinasIds = array_map(fn ($toItem) => (int)$toItem->Maquina, $laItemsBase);

        $taVentas = $this->obtenerVentasPorMaquina($laMaquinasIds, $tdDesde, $tdHasta, $tnEstadoVentaActiva);
        $taAlertas = $this->obtenerAlertasPorMaquina($laMaquinasIds);
        $taProductoEstrella = $this->obtenerProductoEstrellaPorMaquina($laMaquinasIds, $tdDesde, $tdHasta, $tnEstadoVentaActiva);
        $taResponsables = $this->obtenerResponsablesPorMaquina($laMaquinasIds);

        $laDatos = [];
        foreach ($laItemsBase as $loItem) {
            $tnMaquina = (int)$loItem->Maquina;
            $laDatos[] = [
                'IdMaquina' => $tnMaquina,
                'CodigoMaquina' => (string)$loItem->CodigoMaquina,
                'NombreMaquina' => $this->derivarNombreMaquina((string)$loItem->CodigoMaquina, $tnMaquina),
                'TipoMaquina' => $this->derivarTipoMaquina((string)($loItem->Marca ?? ''), (string)($loItem->Modelo ?? '')),
                'EstadoMaquina' => (int)$loItem->EstadoMaquina,
                'EstadoOperativo' => (string)$loItem->EstadoOperativo,
                'Ubicacion' => [
                    'Latitud' => $loItem->Latitud !== null ? (float)$loItem->Latitud : null,
                    'Longitud' => $loItem->Longitud !== null ? (float)$loItem->Longitud : null,
                    'Direccion' => $loItem->Direccion !== null ? (string)$loItem->Direccion : null,
                ],
                'Responsables' => $taResponsables[$tnMaquina] ?? [],
                'VentasPeriodo' => $taVentas[$tnMaquina] ?? ['Transacciones' => 0, 'Unidades' => 0, 'Ingresos' => 0.0, 'TicketPromedio' => 0.0],
                'ProductoEstrella' => $taProductoEstrella[$tnMaquina] ?? null,
                'Alertas' => $taAlertas[$tnMaquina] ?? ['Abiertas' => 0, 'Criticas' => 0, 'StockBajo' => 0, 'Fallas' => 0],
                'Version' => $this->toControlVersionService->versionDesdeFila($loItem),
                'UsrFecha' => (string)$loItem->UsrFecha,
                'UsrHora' => (string)$loItem->UsrHora,
            ];
        }

        return [
            'Datos' => $laDatos,
            'Meta' => [
                'PaginaActual' => $toPaginador->currentPage(),
                'TamanoPagina' => $toPaginador->perPage(),
                'TotalRegistros' => $toPaginador->total(),
                'TotalPaginas' => $toPaginador->lastPage(),
            ],
        ];
    }

    public function ejecutivoMapa(int $tnUsuarioSesion, ?int $tnEmpresa, ?int $tnEstado, int $tnSoloConCoordenadas, ?string $tcFechaDesde, ?string $tcFechaHasta): array
    {
        [$tdDesde, $tdHasta] = $this->resolverRangoFechas($tcFechaDesde, $tcFechaHasta);
        $tnEstadoVentaActiva = $this->obtenerEstadoSeguro('VENTA', 1, 1);

        $toConsulta = $this->consultaBaseMaquinas($tnUsuarioSesion, $tnEmpresa, $tnEstado, null)
            ->leftJoin('MAQUINAESTADOOPERATIVO as meo', 'meo.Maquina', '=', 'm.Maquina')
            ->selectRaw('m.Maquina, m.CodigoMaquina, u.Latitud, u.Longitud, COALESCE(meo.EstadoOperativo, "NO_DEFINIDO") as EstadoOperativo')
            ->groupBy('m.Maquina', 'm.CodigoMaquina', 'u.Latitud', 'u.Longitud', 'meo.EstadoOperativo');

        if ($tnSoloConCoordenadas === 1) {
            $toConsulta->whereNotNull('u.Latitud')->whereNotNull('u.Longitud');
        }

        $laBase = $toConsulta->get();
        $laMaquinasIds = $laBase->pluck('Maquina')->map(fn ($tnMaquina) => (int)$tnMaquina)->all();
        $taVentas = $this->obtenerVentasPorMaquina($laMaquinasIds, $tdDesde, $tdHasta, $tnEstadoVentaActiva);
        $taAlertas = $this->obtenerAlertasPorMaquina($laMaquinasIds);

        $laDatos = [];
        foreach ($laBase as $loFila) {
            $tnMaquina = (int)$loFila->Maquina;
            $laVentas = $taVentas[$tnMaquina] ?? ['Transacciones' => 0, 'Ingresos' => 0.0];
            $laAlertas = $taAlertas[$tnMaquina] ?? ['Abiertas' => 0, 'Criticas' => 0];

            $laDatos[] = [
                'IdMaquina' => $tnMaquina,
                'CodigoMaquina' => (string)$loFila->CodigoMaquina,
                'Latitud' => $loFila->Latitud !== null ? (float)$loFila->Latitud : null,
                'Longitud' => $loFila->Longitud !== null ? (float)$loFila->Longitud : null,
                'EstadoOperativo' => (string)$loFila->EstadoOperativo,
                'NivelAlerta' => $this->nivelAlerta((int)($laAlertas['Abiertas'] ?? 0), (int)($laAlertas['Criticas'] ?? 0)),
                'IngresosPeriodo' => (float)($laVentas['Ingresos'] ?? 0),
                'VentasPeriodo' => (int)($laVentas['Transacciones'] ?? 0),
            ];
        }

        return $laDatos;
    }

    public function ejecutivoRanking(int $tnUsuarioSesion, ?int $tnEmpresa, ?string $tcFechaDesde, ?string $tcFechaHasta, int $tnTop, string $tcPor): array
    {
        [$tdDesde, $tdHasta] = $this->resolverRangoFechas($tcFechaDesde, $tcFechaHasta);
        $tnTop = max(1, min($tnTop, 100));

        $tcPor = strtolower(trim($tcPor));
        if (!in_array($tcPor, ['ventas', 'ingresos', 'alertas', 'margen'], true)) {
            $tcPor = 'ingresos';
        }

        $laMaquinas = $this->consultaBaseMaquinas($tnUsuarioSesion, $tnEmpresa, null, null)
            ->selectRaw('m.Maquina, m.CodigoMaquina')
            ->groupBy('m.Maquina', 'm.CodigoMaquina')
            ->get();

        $laMaquinasIds = [];
        $taCodigo = [];
        foreach ($laMaquinas as $loMaquina) {
            $tnMaquina = (int)$loMaquina->Maquina;
            $laMaquinasIds[] = $tnMaquina;
            $taCodigo[$tnMaquina] = (string)$loMaquina->CodigoMaquina;
        }

        if (count($laMaquinasIds) === 0) {
            return [];
        }

        $laRanking = match ($tcPor) {
            'ventas' => $this->rankingPorVentas($laMaquinasIds, $tdDesde, $tdHasta),
            'alertas' => $this->rankingPorAlertas($laMaquinasIds),
            'margen' => $this->rankingPorMargen($laMaquinasIds, $tdDesde, $tdHasta),
            default => $this->rankingPorIngresos($laMaquinasIds, $tdDesde, $tdHasta),
        };

        $laResultado = [];
        foreach (array_slice($laRanking, 0, $tnTop) as $laFila) {
            $tnMaquina = (int)$laFila['IdMaquina'];
            $tcCodigo = $taCodigo[$tnMaquina] ?? ('MAQ-' . $tnMaquina);
            $laResultado[] = [
                'IdMaquina' => $tnMaquina,
                'CodigoMaquina' => $tcCodigo,
                'NombreMaquina' => $this->derivarNombreMaquina($tcCodigo, $tnMaquina),
                'Valor' => $laFila['Valor'],
            ];
        }

        return $laResultado;
    }

    public function ejecutivoDetalleMaquina(int $tnUsuarioSesion, int $tnMaquina, ?string $tcFechaDesde, ?string $tcFechaHasta): array
    {
        [$tdDesde, $tdHasta] = $this->resolverRangoFechas($tcFechaDesde, $tcFechaHasta);
        $tnEstadoVentaActiva = $this->obtenerEstadoSeguro('VENTA', 1, 1);
        $tnEstadoAlertaAbierta = $this->obtenerEstadoSeguro('ALERTA', 1, 1);

        $loBase = $this->consultaBaseMaquinas($tnUsuarioSesion, null, null, null)
            ->leftJoin('MAQUINAESTADOOPERATIVO as meo', 'meo.Maquina', '=', 'm.Maquina')
            ->where('m.Maquina', $tnMaquina)
            ->selectRaw('m.Maquina, m.CodigoMaquina, m.Marca, m.Modelo, m.Estado as EstadoMaquina, m.UsrFecha, m.UsrHora, u.Latitud, u.Longitud, u.Direccion, COALESCE(meo.EstadoOperativo, "NO_DEFINIDO") as EstadoOperativo')
            ->first();

        if (!$loBase) {
            $lbExiste = DB::connection($this->pcConexion)->table('MAQUINA')->where('Maquina', $tnMaquina)->exists();
            return ['Estado' => $lbExiste ? 'NO_AUTORIZADO' : 'NO_ENCONTRADO'];
        }

        $taVentas = $this->obtenerVentasPorMaquina([$tnMaquina], $tdDesde, $tdHasta, $tnEstadoVentaActiva);
        $taProductoEstrella = $this->obtenerProductoEstrellaPorMaquina([$tnMaquina], $tdDesde, $tdHasta, $tnEstadoVentaActiva);
        $taResponsables = $this->obtenerResponsablesPorMaquina([$tnMaquina]);
        $taAlertas = $this->obtenerAlertasPorMaquina([$tnMaquina]);

        $laTopProductos = DB::connection($this->pcConexion)
            ->table('VENTA as v')
            ->join('PRODUCTOEMPRESA as pe', 'pe.ProductoEmpresa', '=', 'v.ProductoEmpresa')
            ->join('PRODUCTO as p', 'p.Producto', '=', 'pe.Producto')
            ->where('v.Maquina', $tnMaquina)
            ->where('v.Estado', $tnEstadoVentaActiva)
            ->whereBetween('v.FechaVenta', [$tdDesde->copy()->startOfDay(), $tdHasta->copy()->endOfDay()])
            ->groupBy('pe.Producto', 'p.NombreProducto')
            ->orderByDesc(DB::raw('SUM(v.Cantidad)'))
            ->orderByDesc(DB::raw('COALESCE(SUM(v.Cantidad * v.PrecioUnitario), 0)'))
            ->limit(10)
            ->selectRaw('pe.Producto as IdProducto, p.NombreProducto, SUM(v.Cantidad) as Unidades, COALESCE(SUM(v.Cantidad * v.PrecioUnitario), 0) as Ingresos')
            ->get()
            ->map(fn ($toFila) => [
                'IdProducto' => (int)$toFila->IdProducto,
                'NombreProducto' => (string)$toFila->NombreProducto,
                'Unidades' => (int)$toFila->Unidades,
                'Ingresos' => (float)$toFila->Ingresos,
            ])
            ->all();

        $laAlertasActivas = DB::connection($this->pcConexion)
            ->table('ALERTA as a')
            ->leftJoin('TIPOALERTA as t', 't.TipoAlerta', '=', 'a.TipoAlerta')
            ->where('a.Maquina', $tnMaquina)
            ->where('a.Estado', $tnEstadoAlertaAbierta)
            ->orderByDesc('a.Prioridad')
            ->orderByDesc('a.FechaHoraGeneracion')
            ->limit(20)
            ->selectRaw('a.Alerta, a.Celda, a.ProductoEmpresa, a.Mensaje, a.Prioridad, a.FechaHoraGeneracion, COALESCE(t.NombreTipoAlerta, "") as NombreTipoAlerta')
            ->get()
            ->map(fn ($toFila) => [
                'Alerta' => (int)$toFila->Alerta,
                'Celda' => $toFila->Celda !== null ? (int)$toFila->Celda : null,
                'ProductoEmpresa' => $toFila->ProductoEmpresa !== null ? (int)$toFila->ProductoEmpresa : null,
                'Mensaje' => (string)$toFila->Mensaje,
                'Prioridad' => (int)$toFila->Prioridad,
                'FechaHoraGeneracion' => (string)$toFila->FechaHoraGeneracion,
                'NombreTipoAlerta' => (string)$toFila->NombreTipoAlerta,
            ])
            ->all();

        $laVentasPorDia = DB::connection($this->pcConexion)
            ->table('VENTA')
            ->where('Maquina', $tnMaquina)
            ->where('Estado', $tnEstadoVentaActiva)
            ->whereBetween('FechaVenta', [$tdDesde->copy()->startOfDay(), $tdHasta->copy()->endOfDay()])
            ->groupBy(DB::raw('DATE(FechaVenta)'))
            ->orderBy(DB::raw('DATE(FechaVenta)'))
            ->selectRaw('DATE(FechaVenta) as Fecha, COUNT(*) as Transacciones, SUM(Cantidad) as Unidades, COALESCE(SUM(Cantidad * PrecioUnitario),0) as Ingresos')
            ->get()
            ->map(fn ($toFila) => [
                'Fecha' => (string)$toFila->Fecha,
                'Transacciones' => (int)$toFila->Transacciones,
                'Unidades' => (int)$toFila->Unidades,
                'Ingresos' => (float)$toFila->Ingresos,
            ])
            ->all();

        $tnEstadoMermaAprobada = $this->obtenerEstadoSeguro('MERMA', 2, 1);
        $loMermas = DB::connection($this->pcConexion)
            ->table('MERMA as m')
            ->join('MERMADETALLE as md', 'md.Merma', '=', 'm.Merma')
            ->leftJoin('MOVIMIENTOINVENTARIO as mi', function ($toJoin): void {
                $toJoin->on('mi.Merma', '=', 'm.Merma')->on('mi.ProductoEmpresa', '=', 'md.ProductoEmpresa');
            })
            ->where('m.Maquina', $tnMaquina)
            ->where('m.Estado', $tnEstadoMermaAprobada)
            ->whereBetween('m.FechaHora', [$tdDesde->copy()->startOfDay(), $tdHasta->copy()->endOfDay()])
            ->selectRaw('COUNT(DISTINCT m.Merma) as TotalMermas, COALESCE(SUM(md.CantidadRetirada),0) as UnidadesRetiradas, COALESCE(SUM(md.CantidadRetirada * COALESCE(mi.CostoUnitario,0)),0) as CostoAprox')
            ->first();

        $laUltimosMovimientos = DB::connection($this->pcConexion)
            ->table('MOVIMIENTOINVENTARIO as mi')
            ->leftJoin('TIPOMOVIMIENTOINVENTARIO as tmi', 'tmi.TipoMovimientoInventario', '=', 'mi.TipoMovimientoInventario')
            ->leftJoin('CELDA as c', 'c.Celda', '=', 'mi.Celda')
            ->where('mi.Maquina', $tnMaquina)
            ->whereBetween('mi.FechaHora', [$tdDesde->copy()->startOfDay(), $tdHasta->copy()->endOfDay()])
            ->orderByDesc('mi.FechaHora')
            ->orderByDesc('mi.MovimientoInventario')
            ->limit(20)
            ->selectRaw('mi.MovimientoInventario as IdMovimiento, COALESCE(tmi.NombreTipoMovimientoInventario, "") as Tipo, mi.FechaHora as Fecha, mi.Usr as Usuario, mi.Celda, c.CodigoSeleccion, mi.Lote, mi.Cantidad, mi.CostoUnitario, mi.Observacion')
            ->get()
            ->map(fn ($toFila) => [
                'IdMovimiento' => (int)$toFila->IdMovimiento,
                'Tipo' => (string)$toFila->Tipo,
                'Fecha' => (string)$toFila->Fecha,
                'Usuario' => (int)$toFila->Usuario,
                'Celda' => $toFila->Celda !== null ? (int)$toFila->Celda : null,
                'CodigoSeleccion' => $toFila->CodigoSeleccion !== null ? (string)$toFila->CodigoSeleccion : null,
                'Lote' => $toFila->Lote !== null ? (int)$toFila->Lote : null,
                'Cantidad' => (int)$toFila->Cantidad,
                'CostoUnitario' => $toFila->CostoUnitario !== null ? (float)$toFila->CostoUnitario : null,
                'Observacion' => $toFila->Observacion !== null ? (string)$toFila->Observacion : null,
            ])
            ->all();

        return [
            'Estado' => 'OK',
            'Datos' => [
                'Maquina' => [
                    'IdMaquina' => (int)$loBase->Maquina,
                    'CodigoMaquina' => (string)$loBase->CodigoMaquina,
                    'NombreMaquina' => $this->derivarNombreMaquina((string)$loBase->CodigoMaquina, (int)$loBase->Maquina),
                    'TipoMaquina' => $this->derivarTipoMaquina((string)($loBase->Marca ?? ''), (string)($loBase->Modelo ?? '')),
                    'EstadoMaquina' => (int)$loBase->EstadoMaquina,
                    'EstadoOperativo' => (string)$loBase->EstadoOperativo,
                    'Ubicacion' => [
                        'Latitud' => $loBase->Latitud !== null ? (float)$loBase->Latitud : null,
                        'Longitud' => $loBase->Longitud !== null ? (float)$loBase->Longitud : null,
                        'Direccion' => $loBase->Direccion !== null ? (string)$loBase->Direccion : null,
                    ],
                    'Version' => $this->toControlVersionService->versionDesdeFila($loBase),
                    'UsrFecha' => (string)$loBase->UsrFecha,
                    'UsrHora' => (string)$loBase->UsrHora,
                ],
                'Responsables' => $taResponsables[$tnMaquina] ?? [],
                'VentasResumen' => $taVentas[$tnMaquina] ?? ['Transacciones' => 0, 'Unidades' => 0, 'Ingresos' => 0.0, 'TicketPromedio' => 0.0],
                'ProductoEstrella' => $taProductoEstrella[$tnMaquina] ?? null,
                'TopProductos' => $laTopProductos,
                'AlertasActivas' => $laAlertasActivas,
                'VentasPorDia' => $laVentasPorDia,
                'MermasResumen' => [
                    'TotalMermas' => (int)($loMermas->TotalMermas ?? 0),
                    'UnidadesRetiradas' => (int)($loMermas->UnidadesRetiradas ?? 0),
                    'CostoAprox' => (float)($loMermas->CostoAprox ?? 0),
                ],
                'UltimosMovimientosStock' => $laUltimosMovimientos,
                'Alertas' => $taAlertas[$tnMaquina] ?? ['Abiertas' => 0, 'Criticas' => 0, 'StockBajo' => 0, 'Fallas' => 0],
            ],
        ];
    }

    private function consultaBaseMaquinas(int $tnUsuarioSesion, ?int $tnEmpresa, ?int $tnEstado, ?string $tcBusqueda): Builder
    {
        $toConsulta = DB::connection($this->pcConexion)
            ->table('MAQUINA as m')
            ->leftJoin('UBICACION as u', 'u.Ubicacion', '=', 'm.UbicacionActual');

        if ($tnEstado !== null && $tnEstado > 0) {
            $toConsulta->where('m.Estado', $tnEstado);
        }

        if ($tcBusqueda !== null && trim($tcBusqueda) !== '') {
            $tcBusqueda = trim($tcBusqueda);
            $toConsulta->where(function ($toWhere) use ($tcBusqueda): void {
                $toWhere->where('m.CodigoMaquina', 'like', '%' . $tcBusqueda . '%')
                    ->orWhere('m.Marca', 'like', '%' . $tcBusqueda . '%')
                    ->orWhere('m.Modelo', 'like', '%' . $tcBusqueda . '%')
                    ->orWhere('u.Direccion', 'like', '%' . $tcBusqueda . '%');
            });
        }

        $lbAccesoTotal = $this->toPermisoService->usuarioTieneAccesoTotal($tnUsuarioSesion);
        $laRoles = array_map(fn ($tcRol) => $this->normalizarRol((string)$tcRol), $this->toPermisoService->obtenerRolesUsuario($tnUsuarioSesion));
        $lbOperador = in_array('OPERADOR', $laRoles, true);
        $lbAdmin = in_array('ADMIN', $laRoles, true);
        $lbDueno = in_array('DUENO', $laRoles, true);

        if (!$lbAccesoTotal && !$lbDueno) {
            if ($lbOperador && !$lbAdmin) {
                $tnEstadoGeneralActivo = $this->obtenerEstadoSeguro('GENERAL', 1, 1);
                $laMaquinasAsignadas = DB::connection($this->pcConexion)
                    ->table('USUARIOMAQUINA')
                    ->where('Usuario', $tnUsuarioSesion)
                    ->where('Estado', $tnEstadoGeneralActivo)
                    ->pluck('Maquina')
                    ->map(fn ($tnMaquina) => (int)$tnMaquina)
                    ->all();

                if (count($laMaquinasAsignadas) === 0) {
                    $toConsulta->whereRaw('1 = 0');
                } else {
                    $toConsulta->whereIn('m.Maquina', $laMaquinasAsignadas);
                }
            } else {
                $tnEmpresaUsuario = (int)(DB::connection($this->pcConexion)
                    ->table('USUARIO')
                    ->where('Usuario', $tnUsuarioSesion)
                    ->value('Empresa') ?? 0);

                if ($tnEmpresaUsuario > 0) {
                    $toConsulta->where('u.Empresa', $tnEmpresaUsuario);
                } else {
                    $toConsulta->whereRaw('1 = 0');
                }
            }
        }

        if ($tnEmpresa !== null && $tnEmpresa > 0) {
            $toConsulta->where('u.Empresa', $tnEmpresa);
        }

        return $toConsulta;
    }

    private function subconsultaVentasMaquina(Carbon $tdDesde, Carbon $tdHasta, int $tnEstadoVentaActiva): Builder
    {
        return DB::connection($this->pcConexion)
            ->table('VENTA as v')
            ->where('v.Estado', $tnEstadoVentaActiva)
            ->whereBetween('v.FechaVenta', [$tdDesde->copy()->startOfDay(), $tdHasta->copy()->endOfDay()])
            ->groupBy('v.Maquina')
            ->selectRaw('v.Maquina, COUNT(*) as Transacciones, COALESCE(SUM(v.Cantidad * v.PrecioUnitario), 0) as Ingresos');
    }

    private function subconsultaAlertasMaquina(int $tnEstadoAlertaAbierta): Builder
    {
        return DB::connection($this->pcConexion)
            ->table('ALERTA as a')
            ->where('a.Estado', $tnEstadoAlertaAbierta)
            ->groupBy('a.Maquina')
            ->selectRaw('a.Maquina, COUNT(*) as Abiertas');
    }

    /**
     * @param array<int,int> $laMaquinasIds
     * @return array<int,array{Transacciones:int,Unidades:int,Ingresos:float,TicketPromedio:float}>
     */
    private function obtenerVentasPorMaquina(array $laMaquinasIds, Carbon $tdDesde, Carbon $tdHasta, int $tnEstadoVentaActiva): array
    {
        if (count($laMaquinasIds) === 0) {
            return [];
        }

        $laFilas = DB::connection($this->pcConexion)
            ->table('VENTA')
            ->whereIn('Maquina', $laMaquinasIds)
            ->where('Estado', $tnEstadoVentaActiva)
            ->whereBetween('FechaVenta', [$tdDesde->copy()->startOfDay(), $tdHasta->copy()->endOfDay()])
            ->groupBy('Maquina')
            ->selectRaw('Maquina, COUNT(*) as Transacciones, COALESCE(SUM(Cantidad), 0) as Unidades, COALESCE(SUM(Cantidad * PrecioUnitario), 0) as Ingresos')
            ->get();

        $taVentas = [];
        foreach ($laFilas as $loFila) {
            $tnTransacciones = (int)$loFila->Transacciones;
            $tnIngresos = (float)$loFila->Ingresos;
            $taVentas[(int)$loFila->Maquina] = [
                'Transacciones' => $tnTransacciones,
                'Unidades' => (int)$loFila->Unidades,
                'Ingresos' => $tnIngresos,
                'TicketPromedio' => $tnTransacciones > 0 ? round($tnIngresos / $tnTransacciones, 2) : 0.0,
            ];
        }

        return $taVentas;
    }

    /**
     * @param array<int,int> $laMaquinasIds
     * @return array<int,array{Abiertas:int,Criticas:int,StockBajo:int,Fallas:int}>
     */
    private function obtenerAlertasPorMaquina(array $laMaquinasIds): array
    {
        if (count($laMaquinasIds) === 0) {
            return [];
        }

        $tnEstadoAlertaAbierta = $this->obtenerEstadoSeguro('ALERTA', 1, 1);

        $laFilas = DB::connection($this->pcConexion)
            ->table('ALERTA as a')
            ->leftJoin('TIPOALERTA as t', 't.TipoAlerta', '=', 'a.TipoAlerta')
            ->whereIn('a.Maquina', $laMaquinasIds)
            ->where('a.Estado', $tnEstadoAlertaAbierta)
            ->groupBy('a.Maquina')
            ->selectRaw('a.Maquina, COUNT(*) as Abiertas, SUM(CASE WHEN COALESCE(a.Prioridad, 0) >= 3 THEN 1 ELSE 0 END) as Criticas, SUM(CASE WHEN LOWER(CONCAT(COALESCE(a.Mensaje, ""), " ", COALESCE(t.NombreTipoAlerta, ""))) LIKE "%stock%" THEN 1 ELSE 0 END) as StockBajo, SUM(CASE WHEN LOWER(CONCAT(COALESCE(a.Mensaje, ""), " ", COALESCE(t.NombreTipoAlerta, ""))) REGEXP "falla|error|offline|desconexion" THEN 1 ELSE 0 END) as Fallas')
            ->get();

        $taAlertas = [];
        foreach ($laFilas as $loFila) {
            $taAlertas[(int)$loFila->Maquina] = [
                'Abiertas' => (int)$loFila->Abiertas,
                'Criticas' => (int)$loFila->Criticas,
                'StockBajo' => (int)$loFila->StockBajo,
                'Fallas' => (int)$loFila->Fallas,
            ];
        }

        return $taAlertas;
    }

    /**
     * @param array<int,int> $laMaquinasIds
     * @return array<int,array{IdProducto:int,NombreProducto:string,Unidades:int,Ingresos:float}>
     */
    private function obtenerProductoEstrellaPorMaquina(array $laMaquinasIds, Carbon $tdDesde, Carbon $tdHasta, int $tnEstadoVentaActiva): array
    {
        if (count($laMaquinasIds) === 0) {
            return [];
        }

        $laFilas = DB::connection($this->pcConexion)
            ->table('VENTA as v')
            ->join('PRODUCTOEMPRESA as pe', 'pe.ProductoEmpresa', '=', 'v.ProductoEmpresa')
            ->join('PRODUCTO as p', 'p.Producto', '=', 'pe.Producto')
            ->whereIn('v.Maquina', $laMaquinasIds)
            ->where('v.Estado', $tnEstadoVentaActiva)
            ->whereBetween('v.FechaVenta', [$tdDesde->copy()->startOfDay(), $tdHasta->copy()->endOfDay()])
            ->groupBy('v.Maquina', 'pe.Producto', 'p.NombreProducto')
            ->orderBy('v.Maquina')
            ->orderByDesc(DB::raw('SUM(v.Cantidad)'))
            ->orderByDesc(DB::raw('COALESCE(SUM(v.Cantidad * v.PrecioUnitario), 0)'))
            ->selectRaw('v.Maquina, pe.Producto as IdProducto, p.NombreProducto, SUM(v.Cantidad) as Unidades, COALESCE(SUM(v.Cantidad * v.PrecioUnitario), 0) as Ingresos')
            ->get();

        $taResultado = [];
        foreach ($laFilas as $loFila) {
            $tnMaquina = (int)$loFila->Maquina;
            if (isset($taResultado[$tnMaquina])) {
                continue;
            }
            $taResultado[$tnMaquina] = [
                'IdProducto' => (int)$loFila->IdProducto,
                'NombreProducto' => (string)$loFila->NombreProducto,
                'Unidades' => (int)$loFila->Unidades,
                'Ingresos' => (float)$loFila->Ingresos,
            ];
        }

        return $taResultado;
    }

    /**
     * @param array<int,int> $laMaquinasIds
     * @return array<int,array<int,array{IdUsuario:int,NombreUsuario:string,Rol:string}>>
     */
    private function obtenerResponsablesPorMaquina(array $laMaquinasIds): array
    {
        if (count($laMaquinasIds) === 0) {
            return [];
        }

        $tnEstadoGeneralActivo = $this->obtenerEstadoSeguro('GENERAL', 1, 1);

        $laFilas = DB::connection($this->pcConexion)
            ->table('USUARIOMAQUINA as um')
            ->join('USUARIO as u', 'u.Usuario', '=', 'um.Usuario')
            ->leftJoin('USUARIOROL as ur', function ($toJoin) use ($tnEstadoGeneralActivo): void {
                $toJoin->on('ur.Usuario', '=', 'u.Usuario')->where('ur.Estado', '=', $tnEstadoGeneralActivo);
            })
            ->leftJoin('ROL as r', function ($toJoin) use ($tnEstadoGeneralActivo): void {
                $toJoin->on('r.Rol', '=', 'ur.Rol')->where('r.Estado', '=', $tnEstadoGeneralActivo);
            })
            ->whereIn('um.Maquina', $laMaquinasIds)
            ->where('um.Estado', $tnEstadoGeneralActivo)
            ->where('u.Estado', $tnEstadoGeneralActivo)
            ->orderBy('um.Maquina')
            ->orderBy('u.NombreUsuario')
            ->selectRaw('um.Maquina, u.Usuario as IdUsuario, u.NombreUsuario, COALESCE(r.NombreRol, "SinRol") as Rol')
            ->get();

        $taResultado = [];
        $taUnicos = [];
        foreach ($laFilas as $loFila) {
            $tnMaquina = (int)$loFila->Maquina;
            $tcLlave = $tnMaquina . '|' . (int)$loFila->IdUsuario . '|' . (string)$loFila->Rol;
            if (isset($taUnicos[$tcLlave])) {
                continue;
            }
            $taUnicos[$tcLlave] = true;
            $taResultado[$tnMaquina][] = [
                'IdUsuario' => (int)$loFila->IdUsuario,
                'NombreUsuario' => (string)$loFila->NombreUsuario,
                'Rol' => (string)$loFila->Rol,
            ];
        }

        return $taResultado;
    }

    /** @param array<int,int> $laMaquinasIds @return array<int,array{IdMaquina:int,Valor:int|float}> */
    private function rankingPorVentas(array $laMaquinasIds, Carbon $tdDesde, Carbon $tdHasta): array
    {
        $tnEstadoVentaActiva = $this->obtenerEstadoSeguro('VENTA', 1, 1);
        return DB::connection($this->pcConexion)
            ->table('VENTA')
            ->whereIn('Maquina', $laMaquinasIds)
            ->where('Estado', $tnEstadoVentaActiva)
            ->whereBetween('FechaVenta', [$tdDesde->copy()->startOfDay(), $tdHasta->copy()->endOfDay()])
            ->groupBy('Maquina')
            ->orderByDesc(DB::raw('COUNT(*)'))
            ->selectRaw('Maquina as IdMaquina, COUNT(*) as Valor')
            ->get()
            ->map(fn ($toFila) => ['IdMaquina' => (int)$toFila->IdMaquina, 'Valor' => (int)$toFila->Valor])
            ->all();
    }

    /** @param array<int,int> $laMaquinasIds @return array<int,array{IdMaquina:int,Valor:float}> */
    private function rankingPorIngresos(array $laMaquinasIds, Carbon $tdDesde, Carbon $tdHasta): array
    {
        $tnEstadoVentaActiva = $this->obtenerEstadoSeguro('VENTA', 1, 1);
        return DB::connection($this->pcConexion)
            ->table('VENTA')
            ->whereIn('Maquina', $laMaquinasIds)
            ->where('Estado', $tnEstadoVentaActiva)
            ->whereBetween('FechaVenta', [$tdDesde->copy()->startOfDay(), $tdHasta->copy()->endOfDay()])
            ->groupBy('Maquina')
            ->orderByDesc(DB::raw('COALESCE(SUM(Cantidad * PrecioUnitario), 0)'))
            ->selectRaw('Maquina as IdMaquina, COALESCE(SUM(Cantidad * PrecioUnitario), 0) as Valor')
            ->get()
            ->map(fn ($toFila) => ['IdMaquina' => (int)$toFila->IdMaquina, 'Valor' => (float)$toFila->Valor])
            ->all();
    }

    /** @param array<int,int> $laMaquinasIds @return array<int,array{IdMaquina:int,Valor:int}> */
    private function rankingPorAlertas(array $laMaquinasIds): array
    {
        $tnEstadoAlertaAbierta = $this->obtenerEstadoSeguro('ALERTA', 1, 1);
        return DB::connection($this->pcConexion)
            ->table('ALERTA')
            ->whereIn('Maquina', $laMaquinasIds)
            ->where('Estado', $tnEstadoAlertaAbierta)
            ->groupBy('Maquina')
            ->orderByDesc(DB::raw('COUNT(*)'))
            ->selectRaw('Maquina as IdMaquina, COUNT(*) as Valor')
            ->get()
            ->map(fn ($toFila) => ['IdMaquina' => (int)$toFila->IdMaquina, 'Valor' => (int)$toFila->Valor])
            ->all();
    }

    /** @param array<int,int> $laMaquinasIds @return array<int,array{IdMaquina:int,Valor:float}> */
    private function rankingPorMargen(array $laMaquinasIds, Carbon $tdDesde, Carbon $tdHasta): array
    {
        $tnEstadoVentaActiva = $this->obtenerEstadoSeguro('VENTA', 1, 1);

        $toCostoPromedio = DB::connection($this->pcConexion)
            ->table('MOVIMIENTOINVENTARIO')
            ->whereNotNull('CostoUnitario')
            ->groupBy('ProductoEmpresa', 'Lote')
            ->selectRaw('ProductoEmpresa, Lote, AVG(CostoUnitario) as CostoUnitario');

        return DB::connection($this->pcConexion)
            ->table('VENTA as v')
            ->leftJoinSub($toCostoPromedio, 'cp', function ($toJoin): void {
                $toJoin->on('cp.ProductoEmpresa', '=', 'v.ProductoEmpresa')->on('cp.Lote', '=', 'v.Lote');
            })
            ->whereIn('v.Maquina', $laMaquinasIds)
            ->where('v.Estado', $tnEstadoVentaActiva)
            ->whereBetween('v.FechaVenta', [$tdDesde->copy()->startOfDay(), $tdHasta->copy()->endOfDay()])
            ->groupBy('v.Maquina')
            ->orderByDesc(DB::raw('COALESCE(SUM((v.Cantidad * v.PrecioUnitario) - (v.Cantidad * COALESCE(cp.CostoUnitario, 0))), 0)'))
            ->selectRaw('v.Maquina as IdMaquina, COALESCE(SUM((v.Cantidad * v.PrecioUnitario) - (v.Cantidad * COALESCE(cp.CostoUnitario, 0))), 0) as Valor')
            ->get()
            ->map(fn ($toFila) => ['IdMaquina' => (int)$toFila->IdMaquina, 'Valor' => (float)$toFila->Valor])
            ->all();
    }

    private function derivarNombreMaquina(string $tcCodigoMaquina, int $tnMaquina): string
    {
        $tcCodigoMaquina = trim($tcCodigoMaquina);
        if ($tcCodigoMaquina !== '') {
            return $tcCodigoMaquina;
        }
        return 'MAQ-' . $tnMaquina;
    }

    private function derivarTipoMaquina(string $tcMarca, string $tcModelo): string
    {
        $tcTipo = trim($tcMarca . ' ' . $tcModelo);
        return $tcTipo !== '' ? $tcTipo : 'NO_DEFINIDO';
    }

    private function nivelAlerta(int $tnAbiertas, int $tnCriticas): string
    {
        if ($tnCriticas > 0) {
            return 'ALTO';
        }
        if ($tnAbiertas >= 3) {
            return 'MEDIO';
        }
        return 'BAJO';
    }

    /** @return array{0:Carbon,1:Carbon} */
    private function resolverRangoFechas(?string $tcFechaDesde, ?string $tcFechaHasta): array
    {
        $tdHasta = $this->parsearFecha($tcFechaHasta) ?? Carbon::now();
        $tdDesde = $this->parsearFecha($tcFechaDesde) ?? $tdHasta->copy()->subDays(30);

        if ($tdDesde->greaterThan($tdHasta)) {
            [$tdDesde, $tdHasta] = [$tdHasta->copy()->subDays(30), $tdHasta];
        }

        return [$tdDesde, $tdHasta];
    }

    private function parsearFecha(?string $tcFecha): ?Carbon
    {
        if ($tcFecha === null || trim($tcFecha) === '') {
            return null;
        }

        try {
            return Carbon::createFromFormat('Y-m-d', trim($tcFecha))->startOfDay();
        } catch (Throwable) {
            return null;
        }
    }

    private function normalizarRol(string $tcRol): string
    {
        $tcRol = trim($tcRol);
        if ($tcRol === '') {
            return '';
        }

        $tcRol = strtr($tcRol, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n', 'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ñ' => 'N']);
        return strtoupper($tcRol);
    }

    private function obtenerEstadoSeguro(string $tcEntidad, int $tnCodigo, int $tnFallback): int
    {
        try {
            return $this->toEstadoCatalogo->obtenerId($tcEntidad, $tnCodigo);
        } catch (RuntimeException) {
            return $tnFallback;
        }
    }
}


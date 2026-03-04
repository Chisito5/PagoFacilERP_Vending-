<?php

namespace App\Modulos\ErpV1\Services;

use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class VentaErpService
{
    private string $pcConexion = 'mysqlNegocio';

    /**
     * @param array<string,mixed> $laFiltros
     */
    public function listarVentas(array $laFiltros, int $tnPagina, int $tnTamanoPagina): LengthAwarePaginator
    {
        $toConsulta = $this->consultaVentasBase();
        $this->aplicarFiltros($toConsulta, $laFiltros);
        $this->aplicarOrden($toConsulta, $laFiltros);

        $toPaginador = $toConsulta->paginate($tnTamanoPagina, ['*'], 'Pagina', $tnPagina);
        $toPaginador->setCollection($toPaginador->getCollection()->map(fn ($toFila) => $this->mapearFilaVenta($toFila)));

        return $toPaginador;
    }

    /**
     * @param array<string,mixed> $laFiltros
     */
    public function listarTransacciones(array $laFiltros, int $tnPagina, int $tnTamanoPagina): LengthAwarePaginator
    {
        $toConsulta = $this->consultaVentasBase();
        $this->aplicarFiltros($toConsulta, $laFiltros);
        $this->aplicarOrden($toConsulta, $laFiltros);

        $toPaginador = $toConsulta->paginate($tnTamanoPagina, ['*'], 'Pagina', $tnPagina);
        $toPaginador->setCollection($toPaginador->getCollection()->map(fn ($toFila) => $this->mapearFilaTransaccion($toFila)));

        return $toPaginador;
    }

    /**
     * @return array<string,mixed>|null
     */
    public function obtenerVenta(int $tnVenta): ?array
    {
        $loFila = $this->consultaVentasBase()->where('v.Venta', $tnVenta)->first();
        return $loFila ? $this->mapearFilaVenta($loFila) : null;
    }

    /**
     * @return array<string,mixed>|null
     */
    public function obtenerTransaccion(int $tnTransaccion): ?array
    {
        $loFila = $this->consultaVentasBase()->where('v.Venta', $tnTransaccion)->first();
        return $loFila ? $this->mapearFilaTransaccion($loFila) : null;
    }

    /**
     * @return array<string,mixed>
     */
    public function historialVenta(int $tnVenta, int $tnPagina, int $tnTamanoPagina): array
    {
        $laEventos = [];
        $loVenta = DB::connection($this->pcConexion)->table('VENTA')->where('Venta', $tnVenta)->first();
        if (!$loVenta) {
            return ['Estado' => 'NO_ENCONTRADO'];
        }

        $laEventos[] = [
            'IdEvento' => 'VENTA-' . (int)$loVenta->Venta,
            'TipoEvento' => 'VENTA_CREADA',
            'FechaHora' => $this->isoLaPaz($loVenta->FechaVenta),
            'Estado' => $this->nombreEstadoVenta((int)$loVenta->Estado),
            'Usuario' => (int)($loVenta->Usr ?? 0),
            'Motivo' => null,
            'Antes' => null,
            'Despues' => [
                'Cantidad' => (int)$loVenta->Cantidad,
                'PrecioUnitario' => (float)$loVenta->PrecioUnitario,
                'MontoTotal' => round((float)$loVenta->Cantidad * (float)$loVenta->PrecioUnitario, 2),
            ],
            'Referencia' => null,
        ];

        $laReversas = DB::connection($this->pcConexion)
            ->table('VENTAREVERSA')
            ->where('Venta', $tnVenta)
            ->orderByDesc('FechaHora')
            ->orderByDesc('VentaReversa')
            ->get();

        foreach ($laReversas as $loReversa) {
            $laEventos[] = [
                'IdEvento' => 'REVERSA-' . (int)$loReversa->VentaReversa,
                'TipoEvento' => 'VENTA_REVERTIDA',
                'FechaHora' => $this->isoLaPaz($loReversa->FechaHora),
                'Estado' => 'REVERTIDA',
                'Usuario' => (int)($loReversa->Usr ?? 0),
                'Motivo' => (string)$loReversa->Motivo,
                'Antes' => null,
                'Despues' => null,
                'Referencia' => ['Venta' => $tnVenta],
            ];
        }

        usort($laEventos, static function (array $laA, array $laB): int {
            $tcA = (string)($laA['FechaHora'] ?? '');
            $tcB = (string)($laB['FechaHora'] ?? '');
            if ($tcA === $tcB) {
                return strcmp((string)$laB['IdEvento'], (string)$laA['IdEvento']);
            }
            return strcmp($tcB, $tcA);
        });

        $tnTotal = count($laEventos);
        $tnInicio = ($tnPagina - 1) * $tnTamanoPagina;
        $laPagina = array_slice($laEventos, $tnInicio, $tnTamanoPagina);
        $tnTotalPaginas = (int)max(1, ceil($tnTotal / max(1, $tnTamanoPagina)));

        return [
            'Estado' => 'OK',
            'Datos' => [
                'IdVenta' => $tnVenta,
                'Eventos' => array_values($laPagina),
            ],
            'Meta' => [
                'PaginaActual' => $tnPagina,
                'TamanoPagina' => $tnTamanoPagina,
                'TotalRegistros' => $tnTotal,
                'TotalPaginas' => $tnTotalPaginas,
            ],
        ];
    }

    private function consultaVentasBase()
    {
        $toUltimaReversa = DB::connection($this->pcConexion)
            ->table('VENTAREVERSA')
            ->selectRaw('Venta, MAX(VentaReversa) as UltimaReversa')
            ->groupBy('Venta');

        return DB::connection($this->pcConexion)
            ->table('VENTA as v')
            ->join('MAQUINA as m', 'm.Maquina', '=', 'v.Maquina')
            ->join('CELDA as c', 'c.Celda', '=', 'v.Celda')
            ->join('PRODUCTOEMPRESA as pe', 'pe.ProductoEmpresa', '=', 'v.ProductoEmpresa')
            ->join('PRODUCTO as p', 'p.Producto', '=', 'pe.Producto')
            ->leftJoinSub($toUltimaReversa, 'ur', function ($toJoin): void {
                $toJoin->on('ur.Venta', '=', 'v.Venta');
            })
            ->leftJoin('VENTAREVERSA as vr', 'vr.VentaReversa', '=', 'ur.UltimaReversa')
            ->leftJoin('ESTADO as es', 'es.Estado', '=', 'v.Estado')
            ->selectRaw('
                v.Venta,
                v.Maquina,
                m.CodigoMaquina,
                m.CodigoMaquina as NombreMaquina,
                v.Celda as IdCasilla,
                v.Celda as NumeroCasilla,
                p.Producto as IdProducto,
                p.CodigoProducto,
                p.NombreProducto,
                v.Cantidad as CantidadVendida,
                v.PrecioUnitario as PrecioUnitarioLista,
                v.PrecioUnitario as PrecioUnitarioFinal,
                v.FechaVenta as FechaHoraVenta,
                v.Estado as EstadoVenta,
                COALESCE(es.NombreEstado, "NO_DEFINIDO") as EstadoTransaccion,
                vr.Motivo as MotivoReversa,
                vr.FechaHora as FechaHoraReversa
            ');
    }

    /**
     * @param array<string,mixed> $laFiltros
     */
    private function aplicarFiltros($toConsulta, array $laFiltros): void
    {
        if (!empty($laFiltros['Maquina'])) {
            $toConsulta->where('v.Maquina', (int)$laFiltros['Maquina']);
        }

        if (!empty($laFiltros['Estado'])) {
            $toConsulta->where('v.Estado', (int)$laFiltros['Estado']);
        }

        if (!empty($laFiltros['FechaDesde'])) {
            $toConsulta->whereDate('v.FechaVenta', '>=', (string)$laFiltros['FechaDesde']);
        }

        if (!empty($laFiltros['FechaHasta'])) {
            $toConsulta->whereDate('v.FechaVenta', '<=', (string)$laFiltros['FechaHasta']);
        }

        if (isset($laFiltros['MontoDesde']) && $laFiltros['MontoDesde'] !== null) {
            $toConsulta->whereRaw('(v.Cantidad * v.PrecioUnitario) >= ?', [(float)$laFiltros['MontoDesde']]);
        }

        if (isset($laFiltros['MontoHasta']) && $laFiltros['MontoHasta'] !== null) {
            $toConsulta->whereRaw('(v.Cantidad * v.PrecioUnitario) <= ?', [(float)$laFiltros['MontoHasta']]);
        }

        $tcBusqueda = trim((string)($laFiltros['Busqueda'] ?? ''));
        if ($tcBusqueda !== '') {
            $toConsulta->where(function ($toWhere) use ($tcBusqueda): void {
                if (ctype_digit($tcBusqueda)) {
                    $toWhere->orWhere('v.Venta', (int)$tcBusqueda);
                }

                $tcLike = '%' . $tcBusqueda . '%';
                $toWhere->orWhere('m.CodigoMaquina', 'like', $tcLike)
                    ->orWhere('p.NombreProducto', 'like', $tcLike)
                    ->orWhere('p.CodigoProducto', 'like', $tcLike)
                    ->orWhereRaw('CAST(v.Celda AS CHAR) like ?', [$tcLike])
                    ->orWhereRaw('CAST(v.Venta AS CHAR) like ?', [$tcLike]);
            });
        }
    }

    /**
     * @param array<string,mixed> $laFiltros
     */
    private function aplicarOrden($toConsulta, array $laFiltros): void
    {
        $tcOrden = strtoupper((string)($laFiltros['Direccion'] ?? 'DESC')) === 'ASC' ? 'ASC' : 'DESC';
        $tcCampo = (string)($laFiltros['Orden'] ?? 'FechaHoraVenta');

        $laCampos = [
            'FechaHoraVenta' => 'v.FechaVenta',
            'IdVenta' => 'v.Venta',
            'MontoTotal' => DB::raw('(v.Cantidad * v.PrecioUnitario)'),
        ];

        $tcCampoSql = $laCampos[$tcCampo] ?? 'v.FechaVenta';
        $toConsulta->orderBy($tcCampoSql, $tcOrden)->orderBy('v.Venta', 'DESC');
    }

    /**
     * @return array<string,mixed>
     */
    private function mapearFilaVenta(object $loFila): array
    {
        $tnCantidad = (int)$loFila->CantidadVendida;
        $tnPrecio = (float)$loFila->PrecioUnitarioFinal;
        $tnMonto = round($tnCantidad * $tnPrecio, 2);
        $lbReversada = !empty($loFila->FechaHoraReversa);

        return [
            'IdVenta' => (int)$loFila->Venta,
            'IdTransaccion' => (int)$loFila->Venta,
            'ReferenciaOperacion' => null,
            'EstadoTransaccion' => (string)$loFila->EstadoTransaccion,
            'FechaHoraVenta' => $this->isoLaPaz($loFila->FechaHoraVenta),
            'IdMaquina' => (int)$loFila->Maquina,
            'CodigoMaquina' => (string)$loFila->CodigoMaquina,
            'NombreMaquina' => (string)$loFila->NombreMaquina,
            'IdCasilla' => (int)$loFila->IdCasilla,
            'NumeroCasilla' => (int)$loFila->NumeroCasilla,
            'IdProducto' => (int)$loFila->IdProducto,
            'CodigoProducto' => (string)$loFila->CodigoProducto,
            'NombreProducto' => (string)$loFila->NombreProducto,
            'CantidadVendida' => $tnCantidad,
            'PrecioUnitarioLista' => $tnPrecio,
            'PrecioUnitarioFinal' => $tnPrecio,
            'AplicaOferta' => false,
            'IdOferta' => null,
            'DescuentoTotal' => 0.0,
            'Subtotal' => $tnMonto,
            'MontoTotal' => $tnMonto,
            'MontoRecibido' => $tnMonto,
            'Comision' => 0.0,
            'Moneda' => 'BOB',
            'Reversada' => $lbReversada,
            'MotivoReversa' => $loFila->MotivoReversa ? (string)$loFila->MotivoReversa : null,
            'FechaHoraReversa' => $loFila->FechaHoraReversa ? $this->isoLaPaz($loFila->FechaHoraReversa) : null,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function mapearFilaTransaccion(object $loFila): array
    {
        return $this->mapearFilaVenta($loFila);
    }

    private function isoLaPaz(mixed $tmFechaHora): ?string
    {
        if ($tmFechaHora === null || trim((string)$tmFechaHora) === '') {
            return null;
        }

        try {
            return Carbon::parse((string)$tmFechaHora, (string)config('app.timezone', 'UTC'))
                ->setTimezone('America/La_Paz')
                ->toIso8601String();
        } catch (\Throwable) {
            return null;
        }
    }

    private function nombreEstadoVenta(int $tnEstado): string
    {
        $lo = DB::connection($this->pcConexion)
            ->table('ESTADO')
            ->where('Entidad', 'VENTA')
            ->where('Estado', $tnEstado)
            ->first();

        if ($lo && isset($lo->NombreEstado)) {
            return (string)$lo->NombreEstado;
        }

        return $tnEstado === 2 ? 'REVERTIDA' : 'ACTIVA';
    }
}

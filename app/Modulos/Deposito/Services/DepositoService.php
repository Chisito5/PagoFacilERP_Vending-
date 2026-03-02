<?php

namespace App\Modulos\Deposito\Services;

use App\Soporte\AuditoriaService;
use App\Soporte\AutorizacionNegocioService;
use App\Soporte\ControlVersionService;
use App\Support\EstadoCatalogo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use stdClass;

class DepositoService
{
    private string $pcConexion = 'mysqlNegocio';

    public function __construct(
        private EstadoCatalogo $toEstadoCatalogo,
        private ControlVersionService $toControlVersion,
        private AuditoriaService $toAuditoria,
        private AutorizacionNegocioService $toAutorizacion
    ) {
    }

    /**
     * SYSCOOP
     * category: Service
     * package: App\Modulos\Deposito\Services
     * author: Vladimir Meriles Velasquez
     * fecha: 01-03-2026
     * param: array<string,mixed> $taFiltros, int $tnUsuarioSesion
     * return: array<string,mixed>
     */
    public function Listar(array $taFiltros, int $tnUsuarioSesion): array
    {
        $tnPagina = max(1, (int)($taFiltros['Pagina'] ?? 1));
        $tnTamanoPagina = max(1, min((int)($taFiltros['TamanoPagina'] ?? 20), 200));
        $tnEmpresaFiltro = isset($taFiltros['Empresa']) && (int)$taFiltros['Empresa'] > 0 ? (int)$taFiltros['Empresa'] : null;
        $tnEstadoFiltro = isset($taFiltros['Estado']) && (int)$taFiltros['Estado'] > 0 ? (int)$taFiltros['Estado'] : null;
        $tcBusqueda = isset($taFiltros['Busqueda']) && trim((string)$taFiltros['Busqueda']) !== '' ? trim((string)$taFiltros['Busqueda']) : null;

        $tnEmpresaUsuario = $this->obtenerEmpresaUsuario($tnUsuarioSesion);
        $lbDueno = $this->toAutorizacion->esDueno($tnUsuarioSesion);

        if (!$lbDueno && $tnEmpresaFiltro !== null && $tnEmpresaFiltro !== $tnEmpresaUsuario) {
            return ['Estado' => 'NO_AUTORIZADO'];
        }

        $toConsulta = DB::connection($this->pcConexion)
            ->table('DEPOSITO as d')
            ->join('EMPRESA as e', 'e.Empresa', '=', 'd.Empresa')
            ->select([
                'd.Deposito as IdDeposito',
                'd.Empresa',
                DB::raw('COALESCE(e.NombreComercial, e.RazonSocial) as NombreEmpresa'),
                'd.NombreDeposito',
                'd.Descripcion',
                'd.Estado',
                'd.UsrFecha',
                'd.UsrHora',
            ])
            ->orderBy('d.NombreDeposito');

        if ($lbDueno) {
            if ($tnEmpresaFiltro !== null) {
                $toConsulta->where('d.Empresa', $tnEmpresaFiltro);
            }
        } else {
            $toConsulta->where('d.Empresa', $tnEmpresaUsuario);
        }

        if ($tnEstadoFiltro !== null) {
            $toConsulta->where('d.Estado', $tnEstadoFiltro);
        }

        if ($tcBusqueda !== null) {
            $toConsulta->where(function ($toWhere) use ($tcBusqueda): void {
                $toWhere->where('d.NombreDeposito', 'like', '%' . $tcBusqueda . '%')
                    ->orWhere('d.Descripcion', 'like', '%' . $tcBusqueda . '%')
                    ->orWhere('e.NombreComercial', 'like', '%' . $tcBusqueda . '%')
                    ->orWhere('e.RazonSocial', 'like', '%' . $tcBusqueda . '%');
            });
        }

        return [
            'Estado' => 'OK',
            'Paginador' => $toConsulta->paginate($tnTamanoPagina, ['*'], 'Pagina', $tnPagina),
        ];
    }

    /**
     * SYSCOOP
     * category: Service
     * package: App\Modulos\Deposito\Services
     * author: Vladimir Meriles Velasquez
     * fecha: 01-03-2026
     * param: int $tnDeposito, array<string,mixed> $taFiltros, int $tnUsuarioSesion
     * return: array<string,mixed>
     */
    public function Stock(int $tnDeposito, array $taFiltros, int $tnUsuarioSesion): array
    {
        $loDeposito = $this->obtenerDepositoPermitido($tnDeposito, $tnUsuarioSesion);
        if (!$loDeposito) {
            return ['Estado' => 'NO_AUTORIZADO'];
        }

        $tnPagina = max(1, (int)($taFiltros['Pagina'] ?? 1));
        $tnTamanoPagina = max(1, min((int)($taFiltros['TamanoPagina'] ?? 20), 200));
        $tnProducto = isset($taFiltros['Producto']) && (int)$taFiltros['Producto'] > 0 ? (int)$taFiltros['Producto'] : null;
        $tnLote = isset($taFiltros['Lote']) && (int)$taFiltros['Lote'] > 0 ? (int)$taFiltros['Lote'] : null;
        $tcBusqueda = isset($taFiltros['Busqueda']) && trim((string)$taFiltros['Busqueda']) !== '' ? trim((string)$taFiltros['Busqueda']) : null;

        $toConsulta = DB::connection($this->pcConexion)
            ->table('STOCKDEPOSITO as sd')
            ->join('DEPOSITO as d', 'd.Deposito', '=', 'sd.Deposito')
            ->join('PRODUCTO as p', 'p.Producto', '=', 'sd.Producto')
            ->leftJoin('LOTE as l', 'l.Lote', '=', 'sd.Lote')
            ->select([
                'sd.StockDeposito',
                'd.Deposito as IdDeposito',
                'd.NombreDeposito',
                'sd.Producto as IdProducto',
                DB::raw('COALESCE(p.CodigoProducto, p.CodigoSku) as CodigoProducto'),
                'p.NombreProducto as Producto',
                'sd.Lote as IdLote',
                'l.CodigoLote as Lote',
                'sd.CantidadDisponible',
                'sd.CantidadReservada',
                'sd.Estado',
                'sd.UsrFecha',
                'sd.UsrHora',
            ])
            ->where('sd.Deposito', $tnDeposito)
            ->orderByDesc('sd.StockDeposito');

        if ($tnProducto !== null) {
            $toConsulta->where('sd.Producto', $tnProducto);
        }
        if ($tnLote !== null) {
            $toConsulta->where('sd.Lote', $tnLote);
        }
        if ($tcBusqueda !== null) {
            $toConsulta->where(function ($toWhere) use ($tcBusqueda): void {
                $toWhere->where('p.NombreProducto', 'like', '%' . $tcBusqueda . '%')
                    ->orWhere('p.CodigoSku', 'like', '%' . $tcBusqueda . '%')
                    ->orWhere('p.CodigoProducto', 'like', '%' . $tcBusqueda . '%')
                    ->orWhere('l.CodigoLote', 'like', '%' . $tcBusqueda . '%');
            });
        }

        $toPaginador = $toConsulta->paginate($tnTamanoPagina, ['*'], 'Pagina', $tnPagina);
        $toPaginador->through(function ($toItem) {
            $la = (array)$toItem;
            $la['Version'] = $this->toControlVersion->versionDesdeFila($toItem);
            return $la;
        });

        return ['Estado' => 'OK', 'Paginador' => $toPaginador];
    }

    /**
     * SYSCOOP
     * category: Service
     * package: App\Modulos\Deposito\Services
     * author: Vladimir Meriles Velasquez
     * fecha: 01-03-2026
     * param: array<string,mixed> $taFiltros, int $tnUsuarioSesion
     * return: array<string,mixed>
     */
    public function Movimientos(array $taFiltros, int $tnUsuarioSesion): array
    {
        $tnPagina = max(1, (int)($taFiltros['Pagina'] ?? 1));
        $tnTamanoPagina = max(1, min((int)($taFiltros['TamanoPagina'] ?? 20), 200));
        $tnDeposito = isset($taFiltros['Deposito']) && (int)$taFiltros['Deposito'] > 0 ? (int)$taFiltros['Deposito'] : null;
        $tnMaquina = isset($taFiltros['Maquina']) && (int)$taFiltros['Maquina'] > 0 ? (int)$taFiltros['Maquina'] : null;
        $tnProducto = isset($taFiltros['Producto']) && (int)$taFiltros['Producto'] > 0 ? (int)$taFiltros['Producto'] : null;
        $tnLote = isset($taFiltros['Lote']) && (int)$taFiltros['Lote'] > 0 ? (int)$taFiltros['Lote'] : null;
        $tcTipo = isset($taFiltros['Tipo']) && trim((string)$taFiltros['Tipo']) !== '' ? mb_strtoupper(trim((string)$taFiltros['Tipo'])) : null;
        $tcFechaDesde = isset($taFiltros['FechaDesde']) ? (string)$taFiltros['FechaDesde'] : null;
        $tcFechaHasta = isset($taFiltros['FechaHasta']) ? (string)$taFiltros['FechaHasta'] : null;

        $tnEmpresaUsuario = $this->obtenerEmpresaUsuario($tnUsuarioSesion);
        $lbDueno = $this->toAutorizacion->esDueno($tnUsuarioSesion);

        $toConsulta = DB::connection($this->pcConexion)
            ->table('MOVIMIENTODEPOSITO as md')
            ->join('DEPOSITO as d', 'd.Deposito', '=', 'md.Deposito')
            ->join('PRODUCTO as p', 'p.Producto', '=', 'md.Producto')
            ->leftJoin('LOTE as l', 'l.Lote', '=', 'md.Lote')
            ->leftJoin('USUARIO as u', 'u.Usuario', '=', 'md.Usr')
            ->select([
                'md.MovimientoDeposito as IdMovimiento',
                'md.TipoMovimiento as Tipo',
                DB::raw('DATE_FORMAT(md.FechaHora, "%Y-%m-%d %H:%i:%s") as Fecha'),
                'md.Usr as IdUsuario',
                'u.NombreUsuario as Usuario',
                'md.Deposito as IdDeposito',
                'd.NombreDeposito as Deposito',
                'md.Producto as IdProducto',
                'p.NombreProducto as Producto',
                'md.Lote as IdLote',
                'l.CodigoLote as Lote',
                'md.Cantidad',
                'md.Referencia',
                'md.Maquina as IdMaquina',
                'md.Celda as IdCelda',
                'md.Observacion',
            ])
            ->orderByDesc('md.MovimientoDeposito');

        if (!$lbDueno) {
            $toConsulta->where('d.Empresa', $tnEmpresaUsuario);
        }

        if ($tnDeposito !== null) {
            $loDeposito = $this->obtenerDepositoPermitido($tnDeposito, $tnUsuarioSesion);
            if (!$loDeposito) {
                return ['Estado' => 'NO_AUTORIZADO'];
            }
            $toConsulta->where('md.Deposito', $tnDeposito);
        }
        if ($tnMaquina !== null) {
            $toConsulta->where('md.Maquina', $tnMaquina);
        }
        if ($tnProducto !== null) {
            $toConsulta->where('md.Producto', $tnProducto);
        }
        if ($tnLote !== null) {
            $toConsulta->where('md.Lote', $tnLote);
        }
        if ($tcTipo !== null) {
            $toConsulta->whereRaw('UPPER(md.TipoMovimiento) = ?', [$tcTipo]);
        }
        if ($tcFechaDesde !== null && $tcFechaDesde !== '') {
            $toConsulta->whereDate('md.FechaHora', '>=', $tcFechaDesde);
        }
        if ($tcFechaHasta !== null && $tcFechaHasta !== '') {
            $toConsulta->whereDate('md.FechaHora', '<=', $tcFechaHasta);
        }

        return ['Estado' => 'OK', 'Paginador' => $toConsulta->paginate($tnTamanoPagina, ['*'], 'Pagina', $tnPagina)];
    }

    /**
     * SYSCOOP
     * category: Service
     * package: App\Modulos\Deposito\Services
     * author: Vladimir Meriles Velasquez
     * fecha: 01-03-2026
     * param: array<string,mixed> $taDatos, int $tnUsuarioSesion
     * return: array<string,mixed>
     */
    public function Entrada(array $taDatos, int $tnUsuarioSesion): array
    {
        return $this->moverDeposito($taDatos, $tnUsuarioSesion, 'ENTRADA');
    }

    /**
     * SYSCOOP
     * category: Service
     * package: App\Modulos\Deposito\Services
     * author: Vladimir Meriles Velasquez
     * fecha: 01-03-2026
     * param: array<string,mixed> $taDatos, int $tnUsuarioSesion
     * return: array<string,mixed>
     */
    public function Salida(array $taDatos, int $tnUsuarioSesion): array
    {
        return $this->moverDeposito($taDatos, $tnUsuarioSesion, 'SALIDA');
    }

    /**
     * SYSCOOP
     * category: Service
     * package: App\Modulos\Deposito\Services
     * author: Vladimir Meriles Velasquez
     * fecha: 01-03-2026
     * param: array<string,mixed> $taDatos, int $tnUsuarioSesion
     * return: array<string,mixed>
     */
    public function TransferirAMaquina(array $taDatos, int $tnUsuarioSesion): array
    {
        return DB::connection($this->pcConexion)->transaction(function () use ($taDatos, $tnUsuarioSesion): array {
            $tnDeposito = (int)$taDatos['Deposito'];
            $tnMaquina = (int)$taDatos['Maquina'];
            $tnCelda = (int)$taDatos['Celda'];
            $tnProducto = (int)$taDatos['Producto'];
            $tnLote = isset($taDatos['Lote']) && (int)$taDatos['Lote'] > 0 ? (int)$taDatos['Lote'] : null;
            $tnCantidad = (int)$taDatos['Cantidad'];
            $tcMotivo = isset($taDatos['Motivo']) ? (string)$taDatos['Motivo'] : null;

            $loDeposito = $this->obtenerDepositoPermitido($tnDeposito, $tnUsuarioSesion);
            if (!$loDeposito) {
                return ['Estado' => 'NO_AUTORIZADO'];
            }
            if (!$this->toAutorizacion->puedeAccederMaquina($tnUsuarioSesion, $tnMaquina)) {
                return ['Estado' => 'NO_AUTORIZADO_MAQUINA'];
            }

            $tdAhora = now();
            $tnEstadoGeneralActivo = $this->obtenerEstado('GENERAL', 1, 1);
            $tnEstadoStockActivo = $this->obtenerEstado('STOCKDEPOSITO', 1, $tnEstadoGeneralActivo);
            $tnEstadoMovimientoRegistrado = $this->obtenerEstado('MOVIMIENTODEPOSITO', 1, $tnEstadoGeneralActivo);

            $loStock = $this->obtenerStockDepositoConLock($tnDeposito, $tnProducto, $tnLote);
            if (!$loStock) {
                return ['Estado' => 'NO_ENCONTRADO'];
            }
            if ((int)$loStock->CantidadDisponible < $tnCantidad) {
                return ['Estado' => 'STOCK_INSUFICIENTE', 'Disponible' => (int)$loStock->CantidadDisponible];
            }

            $loMaquina = DB::connection($this->pcConexion)
                ->table('MAQUINA as m')
                ->leftJoin('UBICACION as u', 'u.Ubicacion', '=', 'm.UbicacionActual')
                ->select(['m.Maquina', 'u.Empresa'])
                ->where('m.Maquina', $tnMaquina)
                ->lockForUpdate()
                ->first();
            if (!$loMaquina) {
                return ['Estado' => 'MAQUINA_NO_ENCONTRADA'];
            }

            $loCelda = DB::connection($this->pcConexion)
                ->table('CELDA')
                ->where('Maquina', $tnMaquina)
                ->where('Celda', $tnCelda)
                ->where('Estado', $tnEstadoGeneralActivo)
                ->lockForUpdate()
                ->first();
            if (!$loCelda) {
                return ['Estado' => 'CELDA_NO_ENCONTRADA'];
            }

            $tnEmpresaMaquina = (int)($loMaquina->Empresa ?? 0);
            if ($tnEmpresaMaquina <= 0) {
                $tnEmpresaMaquina = (int)$loDeposito->Empresa;
            }

            $tnProductoEmpresa = $this->obtenerOCrearProductoEmpresa($tnEmpresaMaquina, $tnProducto, $tnUsuarioSesion, $tdAhora, $tnEstadoGeneralActivo);
            $loExistencia = $this->obtenerOCrearExistenciaCelda($tnCelda, $tnProductoEmpresa, $tnLote, $tnUsuarioSesion, $tdAhora, $tnEstadoGeneralActivo);
            if (!$loExistencia) {
                return ['Estado' => 'ERROR_EXISTENCIA'];
            }

            DB::connection($this->pcConexion)->table('STOCKDEPOSITO')->where('StockDeposito', (int)$loStock->StockDeposito)->update([
                'CantidadDisponible' => (int)$loStock->CantidadDisponible - $tnCantidad,
                'Estado' => $tnEstadoStockActivo,
                'Usr' => $tnUsuarioSesion,
                'UsrFecha' => $tdAhora->toDateString(),
                'UsrHora' => $tdAhora->format('H:i:s'),
            ]);

            DB::connection($this->pcConexion)->table('EXISTENCIACELDA')->where('ExistenciaCelda', (int)$loExistencia->ExistenciaCelda)->update([
                'CantidadDisponible' => (int)$loExistencia->CantidadDisponible + $tnCantidad,
                'Usr' => $tnUsuarioSesion,
                'UsrFecha' => $tdAhora->toDateString(),
                'UsrHora' => $tdAhora->format('H:i:s'),
            ]);

            $tcReferencia = 'TRF-DEP-' . $tnDeposito . '-MAQ-' . $tnMaquina . '-CEL-' . $tnCelda;
            $tnMovimiento = (int)DB::connection($this->pcConexion)->table('MOVIMIENTODEPOSITO')->insertGetId([
                'Deposito' => $tnDeposito,
                'TipoMovimiento' => 'TRANSFERENCIA',
                'Producto' => $tnProducto,
                'Lote' => $tnLote,
                'Cantidad' => $tnCantidad,
                'Referencia' => $tcReferencia,
                'Observacion' => $tcMotivo,
                'Maquina' => $tnMaquina,
                'Celda' => $tnCelda,
                'FechaHora' => $tdAhora,
                'Estado' => $tnEstadoMovimientoRegistrado,
                'Usr' => $tnUsuarioSesion,
                'UsrFecha' => $tdAhora->toDateString(),
                'UsrHora' => $tdAhora->format('H:i:s'),
            ]);

            $this->registrarMovimientoInventarioTransferencia(
                $tnMaquina,
                $tnCelda,
                $tnProductoEmpresa,
                $tnLote,
                $tnCantidad,
                $tdAhora,
                $tcMotivo,
                $tnEstadoGeneralActivo
            );

            $loStockNuevo = DB::connection($this->pcConexion)->table('STOCKDEPOSITO')->where('StockDeposito', (int)$loStock->StockDeposito)->first();
            $loExistenciaNueva = DB::connection($this->pcConexion)->table('EXISTENCIACELDA')->where('ExistenciaCelda', (int)$loExistencia->ExistenciaCelda)->first();

            $this->toAuditoria->registrar('STOCKDEPOSITO', (int)$loStock->StockDeposito, 'TRANSFERIR_A_MAQUINA', (array)$loStock, (array)$loStockNuevo, $tnUsuarioSesion, $tcMotivo);
            $this->toAuditoria->registrar('EXISTENCIACELDA', (int)$loExistencia->ExistenciaCelda, 'TRANSFERENCIA_DEPOSITO', (array)$loExistencia, (array)$loExistenciaNueva, $tnUsuarioSesion, $tcMotivo);

            return [
                'Estado' => 'OK',
                'Datos' => [
                    'IdMovimiento' => $tnMovimiento,
                    'Referencia' => $tcReferencia,
                    'IdDeposito' => $tnDeposito,
                    'IdMaquina' => $tnMaquina,
                    'IdCelda' => $tnCelda,
                    'IdProducto' => $tnProducto,
                    'IdProductoEmpresa' => $tnProductoEmpresa,
                    'IdLote' => $tnLote,
                    'Cantidad' => $tnCantidad,
                    'StockDepositoDisponible' => (int)$loStockNuevo->CantidadDisponible,
                    'StockCeldaDisponible' => (int)$loExistenciaNueva->CantidadDisponible,
                    'VersionDeposito' => $this->toControlVersion->versionDesdeFila($loStockNuevo),
                    'VersionCelda' => $this->toControlVersion->versionDesdeFila($loExistenciaNueva),
                ],
            ];
        });
    }

    private function moverDeposito(array $taDatos, int $tnUsuarioSesion, string $tcTipo): array
    {
        return DB::connection($this->pcConexion)->transaction(function () use ($taDatos, $tnUsuarioSesion, $tcTipo): array {
            $tnDeposito = (int)$taDatos['Deposito'];
            $tnProducto = (int)$taDatos['Producto'];
            $tnLote = isset($taDatos['Lote']) && (int)$taDatos['Lote'] > 0 ? (int)$taDatos['Lote'] : null;
            $tnCantidad = (int)$taDatos['Cantidad'];
            $tcMotivo = isset($taDatos['Motivo']) ? (string)$taDatos['Motivo'] : null;

            $loDeposito = $this->obtenerDepositoPermitido($tnDeposito, $tnUsuarioSesion);
            if (!$loDeposito) {
                return ['Estado' => 'NO_AUTORIZADO'];
            }

            $tdAhora = now();
            $tnEstadoStockActivo = $this->obtenerEstado('STOCKDEPOSITO', 1, $this->obtenerEstado('GENERAL', 1, 1));
            $tnEstadoMovimientoRegistrado = $this->obtenerEstado('MOVIMIENTODEPOSITO', 1, $this->obtenerEstado('GENERAL', 1, 1));

            $loStock = $this->obtenerStockDepositoConLock($tnDeposito, $tnProducto, $tnLote);
            if (!$loStock) {
                if ($tcTipo === 'SALIDA') {
                    return ['Estado' => 'NO_ENCONTRADO'];
                }

                DB::connection($this->pcConexion)->table('STOCKDEPOSITO')->insert([
                    'Deposito' => $tnDeposito,
                    'Producto' => $tnProducto,
                    'Lote' => $tnLote,
                    'CantidadDisponible' => 0,
                    'CantidadReservada' => 0,
                    'Estado' => $tnEstadoStockActivo,
                    'Usr' => $tnUsuarioSesion,
                    'UsrFecha' => $tdAhora->toDateString(),
                    'UsrHora' => $tdAhora->format('H:i:s'),
                ]);
                $loStock = $this->obtenerStockDepositoConLock($tnDeposito, $tnProducto, $tnLote);
            }

            if (!$loStock) {
                return ['Estado' => 'ERROR_STOCK'];
            }

            $tnDisponibleActual = (int)$loStock->CantidadDisponible;
            if ($tcTipo === 'SALIDA' && $tnDisponibleActual < $tnCantidad) {
                return ['Estado' => 'STOCK_INSUFICIENTE', 'Disponible' => $tnDisponibleActual];
            }

            $tnNuevoDisponible = $tcTipo === 'ENTRADA' ? $tnDisponibleActual + $tnCantidad : $tnDisponibleActual - $tnCantidad;
            DB::connection($this->pcConexion)->table('STOCKDEPOSITO')->where('StockDeposito', (int)$loStock->StockDeposito)->update([
                'CantidadDisponible' => $tnNuevoDisponible,
                'Usr' => $tnUsuarioSesion,
                'UsrFecha' => $tdAhora->toDateString(),
                'UsrHora' => $tdAhora->format('H:i:s'),
            ]);

            $tcReferencia = $tcTipo . '-' . $tnDeposito;
            $tnMovimiento = (int)DB::connection($this->pcConexion)->table('MOVIMIENTODEPOSITO')->insertGetId([
                'Deposito' => $tnDeposito,
                'TipoMovimiento' => $tcTipo,
                'Producto' => $tnProducto,
                'Lote' => $tnLote,
                'Cantidad' => $tnCantidad,
                'Referencia' => $tcReferencia,
                'Observacion' => $tcMotivo,
                'Maquina' => null,
                'Celda' => null,
                'FechaHora' => $tdAhora,
                'Estado' => $tnEstadoMovimientoRegistrado,
                'Usr' => $tnUsuarioSesion,
                'UsrFecha' => $tdAhora->toDateString(),
                'UsrHora' => $tdAhora->format('H:i:s'),
            ]);

            $loStockNuevo = DB::connection($this->pcConexion)->table('STOCKDEPOSITO')->where('StockDeposito', (int)$loStock->StockDeposito)->first();
            $this->toAuditoria->registrar('STOCKDEPOSITO', (int)$loStock->StockDeposito, $tcTipo . '_DEPOSITO', (array)$loStock, (array)$loStockNuevo, $tnUsuarioSesion, $tcMotivo);

            return [
                'Estado' => 'OK',
                'Datos' => [
                    'IdMovimiento' => $tnMovimiento,
                    'IdDeposito' => $tnDeposito,
                    'Producto' => $tnProducto,
                    'Lote' => $tnLote,
                    'Cantidad' => $tnCantidad,
                    'CantidadDisponible' => (int)$loStockNuevo->CantidadDisponible,
                    'CantidadReservada' => (int)$loStockNuevo->CantidadReservada,
                    'Version' => $this->toControlVersion->versionDesdeFila($loStockNuevo),
                    'Referencia' => $tcReferencia,
                ],
            ];
        });
    }

    private function obtenerOCrearProductoEmpresa(int $tnEmpresa, int $tnProducto, int $tnUsuario, \Illuminate\Support\Carbon $tdAhora, int $tnEstado): int
    {
        $loProductoEmpresa = DB::connection($this->pcConexion)
            ->table('PRODUCTOEMPRESA')
            ->where('Empresa', $tnEmpresa)
            ->where('Producto', $tnProducto)
            ->lockForUpdate()
            ->first();

        if ($loProductoEmpresa) {
            return (int)$loProductoEmpresa->ProductoEmpresa;
        }

        $loProducto = DB::connection($this->pcConexion)
            ->table('PRODUCTO')
            ->select('NombreProducto', 'Descripcion')
            ->where('Producto', $tnProducto)
            ->first();

        return (int)DB::connection($this->pcConexion)->table('PRODUCTOEMPRESA')->insertGetId([
            'Empresa' => $tnEmpresa,
            'Producto' => $tnProducto,
            'NombrePublico' => (string)($loProducto->NombreProducto ?? ''),
            'DescripcionPublica' => (string)($loProducto->Descripcion ?? ''),
            'PlantillaVisual' => null,
            'Estado' => $tnEstado,
            'Usr' => $tnUsuario,
            'UsrFecha' => $tdAhora->toDateString(),
            'UsrHora' => $tdAhora->format('H:i:s'),
        ]);
    }

    private function obtenerOCrearExistenciaCelda(int $tnCelda, int $tnProductoEmpresa, ?int $tnLote, int $tnUsuario, \Illuminate\Support\Carbon $tdAhora, int $tnEstado): ?stdClass
    {
        $toQuery = DB::connection($this->pcConexion)
            ->table('EXISTENCIACELDA')
            ->where('Celda', $tnCelda)
            ->where('ProductoEmpresa', $tnProductoEmpresa);
        if ($tnLote === null) {
            $toQuery->whereNull('Lote');
        } else {
            $toQuery->where('Lote', $tnLote);
        }
        $loExistencia = $toQuery->lockForUpdate()->first();
        if ($loExistencia) {
            return $loExistencia;
        }

        DB::connection($this->pcConexion)->table('EXISTENCIACELDA')->insert([
            'Celda' => $tnCelda,
            'ProductoEmpresa' => $tnProductoEmpresa,
            'Lote' => $tnLote,
            'CantidadDisponible' => 0,
            'CantidadReservada' => 0,
            'Estado' => $tnEstado,
            'Usr' => $tnUsuario,
            'UsrFecha' => $tdAhora->toDateString(),
            'UsrHora' => $tdAhora->format('H:i:s'),
        ]);

        $toQuery2 = DB::connection($this->pcConexion)
            ->table('EXISTENCIACELDA')
            ->where('Celda', $tnCelda)
            ->where('ProductoEmpresa', $tnProductoEmpresa);
        if ($tnLote === null) {
            $toQuery2->whereNull('Lote');
        } else {
            $toQuery2->where('Lote', $tnLote);
        }
        return $toQuery2->lockForUpdate()->first();
    }

    private function obtenerDepositoPermitido(int $tnDeposito, int $tnUsuarioSesion): ?stdClass
    {
        $tnEmpresaUsuario = $this->obtenerEmpresaUsuario($tnUsuarioSesion);
        $lbDueno = $this->toAutorizacion->esDueno($tnUsuarioSesion);

        $toConsulta = DB::connection($this->pcConexion)->table('DEPOSITO')->where('Deposito', $tnDeposito);
        if (!$lbDueno) {
            $toConsulta->where('Empresa', $tnEmpresaUsuario);
        }

        return $toConsulta->first();
    }

    private function obtenerEmpresaUsuario(int $tnUsuarioSesion): int
    {
        $loUsuario = DB::connection($this->pcConexion)
            ->table('USUARIO')
            ->select('Empresa')
            ->where('Usuario', $tnUsuarioSesion)
            ->first();

        return (int)($loUsuario->Empresa ?? 0);
    }

    private function obtenerStockDepositoConLock(int $tnDeposito, int $tnProducto, ?int $tnLote): ?stdClass
    {
        $toConsulta = DB::connection($this->pcConexion)
            ->table('STOCKDEPOSITO')
            ->where('Deposito', $tnDeposito)
            ->where('Producto', $tnProducto);

        if ($tnLote === null) {
            $toConsulta->whereNull('Lote');
        } else {
            $toConsulta->where('Lote', $tnLote);
        }

        return $toConsulta->lockForUpdate()->first();
    }

    private function registrarMovimientoInventarioTransferencia(
        int $tnMaquina,
        int $tnCelda,
        int $tnProductoEmpresa,
        ?int $tnLote,
        int $tnCantidad,
        \Illuminate\Support\Carbon $tdAhora,
        ?string $tcObservacion,
        int $tnEstadoGeneralActivo
    ): void {
        if (!Schema::connection($this->pcConexion)->hasTable('MOVIMIENTOINVENTARIO')) {
            return;
        }
        if (!Schema::connection($this->pcConexion)->hasTable('TIPOMOVIMIENTOINVENTARIO')) {
            return;
        }

        $loTipo = DB::connection($this->pcConexion)
            ->table('TIPOMOVIMIENTOINVENTARIO')
            ->whereRaw('UPPER(NombreTipoMovimientoInventario) = ?', ['DEPOSITO_TRANSFERENCIA'])
            ->where('Estado', $tnEstadoGeneralActivo)
            ->first();
        if (!$loTipo) {
            $loTipo = DB::connection($this->pcConexion)
                ->table('TIPOMOVIMIENTOINVENTARIO')
                ->whereRaw('UPPER(NombreTipoMovimientoInventario) = ?', ['REPOSICION'])
                ->where('Estado', $tnEstadoGeneralActivo)
                ->first();
        }
        if (!$loTipo || (int)$loTipo->Factor !== 1) {
            return;
        }

        DB::connection($this->pcConexion)->table('MOVIMIENTOINVENTARIO')->insert([
            'Maquina' => $tnMaquina,
            'Celda' => $tnCelda,
            'ProductoEmpresa' => $tnProductoEmpresa,
            'Lote' => $tnLote,
            'TipoMovimientoInventario' => (int)$loTipo->TipoMovimientoInventario,
            'Cantidad' => $tnCantidad,
            'CostoUnitario' => null,
            'Reposicion' => null,
            'Merma' => null,
            'Transaccion' => null,
            'FechaHora' => $tdAhora,
            'Observacion' => $tcObservacion,
            'Estado' => $tnEstadoGeneralActivo,
            'Usr' => 0,
            'UsrFecha' => $tdAhora->toDateString(),
            'UsrHora' => $tdAhora->format('H:i:s'),
        ]);
    }

    private function obtenerEstado(string $tcEntidad, int $tnCodigo, int $tnFallback): int
    {
        try {
            return $this->toEstadoCatalogo->obtenerId($tcEntidad, $tnCodigo);
        } catch (RuntimeException) {
            return $tnFallback;
        }
    }
}

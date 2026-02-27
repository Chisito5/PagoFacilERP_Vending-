<?php

namespace App\Modulos\Reserva\Services;

use App\Support\EstadoCatalogo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use stdClass;

class ReservaService
{
    private const CODIGO_RESERVA_CREADA = 1;
    private const CODIGO_RESERVA_CONFIRMADA = 2;
    private const CODIGO_RESERVA_EXPIRADA = 3;
    private const CODIGO_RESERVA_CANCELADA = 4;
    private const CODIGO_VENTA_ACTIVA = 1;

    public function __construct(private EstadoCatalogo $toEstadoCatalogo)
    {
    }

    /**
     * SYSCOOP
     * category: Service
     * package: App\Modulos\Reserva\Services
     * author: Vladimir Meriles velasquez
     * fecha: 27-02-2026
     * param: int $tnMaquina
     * param: string $tcCodigoSeleccion
     * param: int $tnCantidad
     * param: int $tnExpiraSegundos
     * return: \Illuminate\Http\JsonResponse
     *
     * Reserva stock: mueve CantidadDisponible -> CantidadReservada con lock.
     * Crea RESERVA (autoincrement) y genera ReservaExterna "RSV-000X".
     * Inserta RESERVADETALLE usando columnas reales de la tabla.
     */
    public function Reservar(int $tnMaquina, string $tcCodigoSeleccion, int $tnCantidad, int $tnExpiraSegundos)
    {
        return DB::connection('mysqlNegocio')->transaction(function () use ($tnMaquina, $tcCodigoSeleccion, $tnCantidad, $tnExpiraSegundos) {
            try {
                $laEstadosReserva = $this->obtenerEstadosReserva();
            } catch (RuntimeException $toEx) {
                return response()->json(['Ok' => false, 'Mensaje' => $toEx->getMessage()], 500);
            }

            // 1) Resolver CELDA por (Maquina + CodigoSeleccion)
            $loCelda = DB::connection('mysqlNegocio')
                ->table('CELDA')
                ->where('Maquina', $tnMaquina)
                ->where('CodigoSeleccion', $tcCodigoSeleccion)
                ->where('Estado', 1)
                ->first();

            if (!$loCelda) {
                return response()->json([
                    'Ok' => false,
                    'Mensaje' => 'La celda no existe para esta maquina o esta inactiva'
                ], 400);
            }

            $tnCelda = (int)$loCelda->Celda;

            // 2) Lock EXISTENCIACELDA y validar stock
            $loExistencia = DB::connection('mysqlNegocio')
                ->table('EXISTENCIACELDA')
                ->where('Celda', $tnCelda)
                ->where('Estado', 1)
                ->lockForUpdate()
                ->first();

            if (!$loExistencia) {
                return response()->json([
                    'Ok' => false,
                    'Mensaje' => 'No existe existencia configurada para esta celda'
                ], 400);
            }

            $tnDisponible = (int)$loExistencia->CantidadDisponible;
            $tnReservada = (int)$loExistencia->CantidadReservada;

            if ($tnDisponible < $tnCantidad) {
                return response()->json([
                    'Ok' => false,
                    'Mensaje' => 'Stock insuficiente para reservar'
                ], 400);
            }

            // 3) Mover disponible -> reservada
            DB::connection('mysqlNegocio')
                ->table('EXISTENCIACELDA')
                ->where('ExistenciaCelda', (int)$loExistencia->ExistenciaCelda)
                ->update([
                    'CantidadDisponible' => $tnDisponible - $tnCantidad,
                    'CantidadReservada' => $tnReservada + $tnCantidad,
                ]);

            // 4) Crear cabecera RESERVA
            $tdAhora = now();
            $tdExpiraEn = $tdAhora->copy()->addSeconds($tnExpiraSegundos);

            // ReservaExterna es NOT NULL -> ponemos temporal y luego actualizamos a RSV-000X
            $tnReserva = DB::connection('mysqlNegocio')
                ->table('RESERVA')
                ->insertGetId([
                    'ReservaExterna' => 'TMP',
                    'Maquina' => $tnMaquina,
                    'FechaHoraReserva' => $tdAhora,
                    'ExpiraEn' => $tdExpiraEn,
                    'Estado' => $laEstadosReserva['CREADA'],
                    'Usr' => 0,
                    'UsrFecha' => $tdAhora->toDateString(),
                    'UsrHora' => $tdAhora->format('H:i:s'),
                ]);

            $tcReservaExterna = 'RSV-' . str_pad((string)$tnReserva, 4, '0', STR_PAD_LEFT);

            DB::connection('mysqlNegocio')
                ->table('RESERVA')
                ->where('Reserva', $tnReserva)
                ->update([
                    'ReservaExterna' => $tcReservaExterna
                ]);

            // 5) Insert detalle (RESERVADETALLE)
            if (Schema::connection('mysqlNegocio')->hasTable('RESERVADETALLE')) {

                $laCols = Schema::connection('mysqlNegocio')->getColumnListing('RESERVADETALLE');
                $lfHas = function (string $tcCol) use ($laCols): bool {
                    return in_array($tcCol, $laCols, true);
                };

                $laDet = [];

                if ($lfHas('Reserva')) $laDet['Reserva'] = $tnReserva;
                if ($lfHas('Celda')) $laDet['Celda'] = $tnCelda;

                if ($lfHas('ProductoEmpresa')) {
                    $tnProductoEmpresa = isset($loExistencia->ProductoEmpresa) ? (int)$loExistencia->ProductoEmpresa : 0;
                    if ($tnProductoEmpresa <= 0) {
                        return response()->json([
                            'Ok' => false,
                            'Mensaje' => 'No se pudo resolver ProductoEmpresa para la reserva'
                        ], 500);
                    }
                    $laDet['ProductoEmpresa'] = $tnProductoEmpresa;
                }

                if ($lfHas('Lote') && isset($loExistencia->Lote)) {
                    $laDet['Lote'] = (int)$loExistencia->Lote;
                }

                if ($lfHas('PrecioUnitario')) {
                    $loPlanograma = DB::connection('mysqlNegocio')
                        ->table('PLANOGRAMA')
                        ->where('Maquina', $tnMaquina)
                        ->where('Estado', 1)
                        ->orderByDesc('VersionPlanograma')
                        ->first();

                    if ($loPlanograma) {
                        $loPlanogramaCelda = DB::connection('mysqlNegocio')
                            ->table('PLANOGRAMACELDA')
                            ->where('Planograma', (int)$loPlanograma->Planograma)
                            ->where('Celda', $tnCelda)
                            ->where('Estado', 1)
                            ->first();

                        if ($loPlanogramaCelda && isset($loPlanogramaCelda->PrecioVenta)) {
                            $laDet['PrecioUnitario'] = (float)$loPlanogramaCelda->PrecioVenta;
                        }
                    }
                }

                if ($lfHas('CantidadReservada')) {
                    $laDet['CantidadReservada'] = $tnCantidad;
                } elseif ($lfHas('Cantidad')) {
                    $laDet['Cantidad'] = $tnCantidad;
                } elseif ($lfHas('CantidadAgregada')) {
                    $laDet['CantidadAgregada'] = $tnCantidad;
                }

                if ($lfHas('Estado')) $laDet['Estado'] = $laEstadosReserva['CREADA'];
                if ($lfHas('Usr')) $laDet['Usr'] = 0;
                if ($lfHas('UsrFecha')) $laDet['UsrFecha'] = $tdAhora->toDateString();
                if ($lfHas('UsrHora')) $laDet['UsrHora'] = $tdAhora->format('H:i:s');

                if (!isset($laDet['Reserva']) || !isset($laDet['Celda'])) {
                    return response()->json([
                        'Ok' => false,
                        'Mensaje' => 'No se pudo construir RESERVADETALLE: faltan columnas Reserva/Celda'
                    ], 500);
                }

                DB::connection('mysqlNegocio')->table('RESERVADETALLE')->insert($laDet);
            }

            return response()->json([
                'Ok' => true,
                'Mensaje' => 'Reserva creada correctamente',
                'Datos' => [
                    'Reserva' => $tnReserva,
                    'ReservaExterna' => $tcReservaExterna,
                    'Maquina' => $tnMaquina,
                    'Celda' => $tnCelda,
                    'CodigoSeleccion' => $tcCodigoSeleccion,
                    'Cantidad' => $tnCantidad,
                    'ExpiraEn' => $tdExpiraEn->toDateTimeString(),
                ]
            ]);
        });
    }

    /**
     * SYSCOOP
     * category: Service
     * package: App\Modulos\Reserva\Services
     * author: Vladimir Meriles velasquez
     * fecha: 27-02-2026
     * param: int $tnReserva
     * param: ?string $tcReservaExterna
     * param: ?string $tcMotivo
     * return: \Illuminate\Http\JsonResponse
     *
     * Cancela reserva: CantidadReservada -> CantidadDisponible.
     */
    public function Cancelar(int $tnReserva, ?string $tcReservaExterna = null, ?string $tcMotivo = null)
    {
        return DB::connection('mysqlNegocio')->transaction(function () use ($tnReserva, $tcReservaExterna, $tcMotivo) {
            try {
                $laEstadosReserva = $this->obtenerEstadosReserva();
            } catch (RuntimeException $toEx) {
                return response()->json(['Ok' => false, 'Mensaje' => $toEx->getMessage()], 500);
            }

            $loReserva = $this->obtenerReservaConLock($tnReserva, $tcReservaExterna);

            if (!$loReserva) {
                return response()->json(['Ok' => false, 'Mensaje' => 'Reserva no encontrada'], 404);
            }

            $tnEstado = (int)$loReserva->Estado;

            if ($tnEstado === $laEstadosReserva['CANCELADA']) {
                return response()->json([
                    'Ok' => true,
                    'Mensaje' => 'Reserva ya cancelada',
                    'Datos' => [
                        'Reserva' => (int)$loReserva->Reserva,
                        'ReservaExterna' => (string)$loReserva->ReservaExterna,
                    ]
                ]);
            }

            if ($tnEstado === $laEstadosReserva['EXPIRADA']) {
                return response()->json([
                    'Ok' => true,
                    'Mensaje' => 'Reserva ya expirada/liberada',
                    'Datos' => [
                        'Reserva' => (int)$loReserva->Reserva,
                        'ReservaExterna' => (string)$loReserva->ReservaExterna,
                    ]
                ]);
            }

            if ($tnEstado === $laEstadosReserva['CONFIRMADA']) {
                return response()->json(['Ok' => false, 'Mensaje' => 'No se puede cancelar, la reserva ya esta confirmada'], 409);
            }

            if ($tnEstado !== $laEstadosReserva['CREADA']) {
                return response()->json(['Ok' => false, 'Mensaje' => 'La reserva no esta en estado cancelable'], 409);
            }

            $loDet = $this->obtenerDetalleReserva((int)$loReserva->Reserva);
            if (!$loDet) {
                return response()->json(['Ok' => false, 'Mensaje' => 'No existe detalle para liberar stock'], 500);
            }

            $loExistencia = $this->obtenerExistenciaConLock((int)$loDet->Celda);
            if (!$loExistencia) {
                return response()->json(['Ok' => false, 'Mensaje' => 'No existe existencia para liberar'], 400);
            }

            $tnCantidad = (int)$loDet->Cantidad;
            if ($tnCantidad <= 0) {
                return response()->json(['Ok' => false, 'Mensaje' => 'Detalle sin cantidad valida'], 500);
            }

            if (!$this->liberarStockReservado($loExistencia, $tnCantidad)) {
                return response()->json(['Ok' => false, 'Mensaje' => 'Inconsistencia: reservada menor que lo reservado'], 409);
            }

            DB::connection('mysqlNegocio')
                ->table('RESERVA')
                ->where('Reserva', (int)$loReserva->Reserva)
                ->update([
                    'Estado' => $laEstadosReserva['CANCELADA'],
                ]);

            return response()->json([
                'Ok' => true,
                'Mensaje' => 'Reserva cancelada y stock liberado',
                'Datos' => [
                    'Reserva' => (int)$loReserva->Reserva,
                    'ReservaExterna' => (string)$loReserva->ReservaExterna,
                    'Celda' => (int)$loDet->Celda,
                    'CantidadLiberada' => $tnCantidad,
                    'Motivo' => $tcMotivo
                ]
            ]);
        });
    }

    /**
     * SYSCOOP
     * category: Service
     * package: App\Modulos\Reserva\Services
     * author: Vladimir Meriles velasquez
     * fecha: 27-02-2026
     * param: int $tnReserva
     * param: ?string $tcReservaExterna
     * return: \Illuminate\Http\JsonResponse
     *
     * Confirma reserva: registra venta final y baja CantidadReservada.
     */
    public function Confirmar(int $tnReserva, ?string $tcReservaExterna = null)
    {
        return DB::connection('mysqlNegocio')->transaction(function () use ($tnReserva, $tcReservaExterna) {
            try {
                $laEstadosReserva = $this->obtenerEstadosReserva();
                $tnEstadoVentaActiva = $this->toEstadoCatalogo->obtenerId('VENTA', self::CODIGO_VENTA_ACTIVA);
            } catch (RuntimeException $toEx) {
                return response()->json(['Ok' => false, 'Mensaje' => $toEx->getMessage()], 500);
            }

            $loReserva = $this->obtenerReservaConLock($tnReserva, $tcReservaExterna);

            if (!$loReserva) {
                return response()->json(['Ok' => false, 'Mensaje' => 'Reserva no encontrada'], 404);
            }

            $tnEstado = (int)$loReserva->Estado;

            if ($tnEstado === $laEstadosReserva['CONFIRMADA']) {
                return response()->json([
                    'Ok' => true,
                    'Mensaje' => 'Reserva ya confirmada',
                    'Datos' => [
                        'Reserva' => (int)$loReserva->Reserva,
                        'ReservaExterna' => (string)$loReserva->ReservaExterna,
                    ]
                ]);
            }

            if ($tnEstado === $laEstadosReserva['CANCELADA']) {
                return response()->json(['Ok' => false, 'Mensaje' => 'No se puede confirmar, la reserva esta cancelada'], 409);
            }

            if ($tnEstado === $laEstadosReserva['EXPIRADA']) {
                return response()->json(['Ok' => false, 'Mensaje' => 'No se puede confirmar, la reserva esta expirada'], 409);
            }

            if ($tnEstado !== $laEstadosReserva['CREADA']) {
                return response()->json(['Ok' => false, 'Mensaje' => 'La reserva no esta en estado confirmable'], 409);
            }

            $loDet = $this->obtenerDetalleReserva((int)$loReserva->Reserva);
            if (!$loDet) {
                return response()->json(['Ok' => false, 'Mensaje' => 'No existe detalle de reserva'], 500);
            }

            $loExistencia = $this->obtenerExistenciaConLock((int)$loDet->Celda);
            if (!$loExistencia) {
                return response()->json(['Ok' => false, 'Mensaje' => 'No existe existencia para confirmar'], 400);
            }

            $tnCantidad = (int)$loDet->Cantidad;
            if ($tnCantidad <= 0) {
                return response()->json(['Ok' => false, 'Mensaje' => 'Detalle sin cantidad valida'], 500);
            }

            if (isset($loReserva->ExpiraEn) && now()->gt($loReserva->ExpiraEn)) {
                if (!$this->liberarStockReservado($loExistencia, $tnCantidad)) {
                    return response()->json(['Ok' => false, 'Mensaje' => 'Inconsistencia al expirar reserva'], 409);
                }

                DB::connection('mysqlNegocio')
                    ->table('RESERVA')
                    ->where('Reserva', (int)$loReserva->Reserva)
                    ->update(['Estado' => $laEstadosReserva['EXPIRADA']]);

                return response()->json(['Ok' => false, 'Mensaje' => 'La reserva esta expirada, debe crear una nueva'], 409);
            }

            $tnReservada = (int)$loExistencia->CantidadReservada;
            if ($tnReservada < $tnCantidad) {
                return response()->json(['Ok' => false, 'Mensaje' => 'Inconsistencia: reservada menor que lo reservado'], 409);
            }

            $tdAhora = now();
            if (!$this->registrarVentaDesdeReserva($loReserva, $loDet, $tdAhora, $tnEstadoVentaActiva)) {
                return response()->json(['Ok' => false, 'Mensaje' => 'No se pudo registrar venta para confirmar la reserva'], 500);
            }

            DB::connection('mysqlNegocio')
                ->table('EXISTENCIACELDA')
                ->where('ExistenciaCelda', (int)$loExistencia->ExistenciaCelda)
                ->update([
                    'CantidadReservada' => $tnReservada - $tnCantidad,
                ]);

            DB::connection('mysqlNegocio')
                ->table('RESERVA')
                ->where('Reserva', (int)$loReserva->Reserva)
                ->update([
                    'Estado' => $laEstadosReserva['CONFIRMADA'],
                ]);

            return response()->json([
                'Ok' => true,
                'Mensaje' => 'Reserva confirmada y venta registrada',
                'Datos' => [
                    'Reserva' => (int)$loReserva->Reserva,
                    'ReservaExterna' => (string)$loReserva->ReservaExterna,
                    'Celda' => (int)$loDet->Celda,
                    'CantidadConfirmada' => $tnCantidad
                ]
            ]);
        });
    }

    /**
     * Expira reservas vencidas y libera stock reservado.
     *
     * @return array{procesadas:int,expiradas:int,saltadas:int,errores:int}
     */
    public function ExpirarVencidas(int $tnLote = 100): array
    {
        try {
            $laEstadosReserva = $this->obtenerEstadosReserva();
        } catch (RuntimeException $toEx) {
            report($toEx);
            return [
                'procesadas' => 0,
                'expiradas' => 0,
                'saltadas' => 0,
                'errores' => 1,
            ];
        }

        $laTotales = [
            'procesadas' => 0,
            'expiradas' => 0,
            'saltadas' => 0,
            'errores' => 0,
        ];

        $laReservas = DB::connection('mysqlNegocio')
            ->table('RESERVA')
            ->where('Estado', $laEstadosReserva['CREADA'])
            ->where('ExpiraEn', '<', now())
            ->orderBy('Reserva')
            ->limit($tnLote)
            ->pluck('Reserva');

        foreach ($laReservas as $tnReserva) {
            $laTotales['procesadas']++;

            try {
                $lbExpirada = DB::connection('mysqlNegocio')->transaction(function () use ($tnReserva, $laEstadosReserva) {
                    $loReserva = DB::connection('mysqlNegocio')
                        ->table('RESERVA')
                        ->where('Reserva', (int)$tnReserva)
                        ->lockForUpdate()
                        ->first();

                    if (!$loReserva) {
                        return false;
                    }

                    if ((int)$loReserva->Estado !== $laEstadosReserva['CREADA']) {
                        return false;
                    }

                    if (!isset($loReserva->ExpiraEn) || !now()->gt($loReserva->ExpiraEn)) {
                        return false;
                    }

                    $loDet = $this->obtenerDetalleReserva((int)$loReserva->Reserva);
                    if (!$loDet) {
                        return false;
                    }

                    $loExistencia = $this->obtenerExistenciaConLock((int)$loDet->Celda);
                    if (!$loExistencia) {
                        return false;
                    }

                    $tnCantidad = (int)$loDet->Cantidad;
                    if ($tnCantidad <= 0) {
                        return false;
                    }

                    if (!$this->liberarStockReservado($loExistencia, $tnCantidad)) {
                        return false;
                    }

                    DB::connection('mysqlNegocio')
                        ->table('RESERVA')
                        ->where('Reserva', (int)$loReserva->Reserva)
                        ->update(['Estado' => $laEstadosReserva['EXPIRADA']]);

                    return true;
                });

                if ($lbExpirada) {
                    $laTotales['expiradas']++;
                } else {
                    $laTotales['saltadas']++;
                }
            } catch (\Throwable $toEx) {
                report($toEx);
                $laTotales['errores']++;
            }
        }

        return $laTotales;
    }

    private function obtenerReservaConLock(int $tnReserva, ?string $tcReservaExterna): ?stdClass
    {
        if ($tnReserva <= 0 && (!$tcReservaExterna || trim($tcReservaExterna) === '')) {
            return null;
        }

        return DB::connection('mysqlNegocio')
            ->table('RESERVA')
            ->when($tnReserva > 0, fn($q) => $q->where('Reserva', $tnReserva))
            ->when($tnReserva <= 0 && $tcReservaExterna, fn($q) => $q->where('ReservaExterna', $tcReservaExterna))
            ->lockForUpdate()
            ->first();
    }

    private function obtenerDetalleReserva(int $tnReserva): ?stdClass
    {
        $loDet = DB::connection('mysqlNegocio')
            ->table('RESERVADETALLE')
            ->where('Reserva', $tnReserva)
            ->orderByDesc('ReservaDetalle')
            ->lockForUpdate()
            ->first();

        if (!$loDet) {
            return null;
        }

        if (!isset($loDet->Cantidad)) {
            return null;
        }

        return $loDet;
    }

    private function obtenerExistenciaConLock(int $tnCelda): ?stdClass
    {
        return DB::connection('mysqlNegocio')
            ->table('EXISTENCIACELDA')
            ->where('Celda', $tnCelda)
            ->where('Estado', 1)
            ->lockForUpdate()
            ->first();
    }

    private function liberarStockReservado(stdClass $loExistencia, int $tnCantidad): bool
    {
        $tnReservada = (int)$loExistencia->CantidadReservada;
        if ($tnReservada < $tnCantidad) {
            return false;
        }

        $tnDisponible = (int)$loExistencia->CantidadDisponible;

        DB::connection('mysqlNegocio')
            ->table('EXISTENCIACELDA')
            ->where('ExistenciaCelda', (int)$loExistencia->ExistenciaCelda)
            ->update([
                'CantidadDisponible' => $tnDisponible + $tnCantidad,
                'CantidadReservada' => $tnReservada - $tnCantidad,
            ]);

        return true;
    }

    private function registrarVentaDesdeReserva(stdClass $loReserva, stdClass $loDet, $tdAhora, int $tnEstadoVentaActiva): bool
    {
        if (
            !isset($loDet->Celda) ||
            !isset($loDet->ProductoEmpresa) ||
            !isset($loDet->Lote) ||
            !isset($loDet->Cantidad) ||
            !isset($loDet->PrecioUnitario)
        ) {
            return false;
        }

        DB::connection('mysqlNegocio')
            ->table('VENTA')
            ->insert([
                'Maquina' => (int)$loReserva->Maquina,
                'Celda' => (int)$loDet->Celda,
                'ProductoEmpresa' => (int)$loDet->ProductoEmpresa,
                'Lote' => (int)$loDet->Lote,
                'Cantidad' => (int)$loDet->Cantidad,
                'PrecioUnitario' => (float)$loDet->PrecioUnitario,
                'FechaVenta' => $tdAhora,
                'Estado' => $tnEstadoVentaActiva,
                'Usr' => 0,
                'UsrFecha' => $tdAhora->toDateString(),
                'UsrHora' => $tdAhora->format('H:i:s'),
            ]);

        return true;
    }

    /**
     * @return array{CREADA:int,CONFIRMADA:int,EXPIRADA:int,CANCELADA:int}
     */
    private function obtenerEstadosReserva(): array
    {
        return [
            'CREADA' => $this->toEstadoCatalogo->obtenerId('RESERVA', self::CODIGO_RESERVA_CREADA),
            'CONFIRMADA' => $this->toEstadoCatalogo->obtenerId('RESERVA', self::CODIGO_RESERVA_CONFIRMADA),
            'EXPIRADA' => $this->toEstadoCatalogo->obtenerId('RESERVA', self::CODIGO_RESERVA_EXPIRADA),
            'CANCELADA' => $this->toEstadoCatalogo->obtenerId('RESERVA', self::CODIGO_RESERVA_CANCELADA),
        ];
    }
}

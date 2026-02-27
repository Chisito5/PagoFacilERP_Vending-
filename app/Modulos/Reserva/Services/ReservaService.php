<?php

namespace App\Modulos\Reserva\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ReservaService
{
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
                    'Mensaje' => 'La celda no existe para esta máquina o está inactiva'
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
                    'Estado' => 1,
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

                // Campo obligatorio en varios esquemas de RESERVADETALLE
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

                if ($lfHas('Estado')) $laDet['Estado'] = 1;
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

            $loReserva = DB::connection('mysqlNegocio')
                ->table('RESERVA')
                ->when($tnReserva > 0, fn($q) => $q->where('Reserva', $tnReserva))
                ->when($tnReserva <= 0 && $tcReservaExterna, fn($q) => $q->where('ReservaExterna', $tcReservaExterna))
                ->lockForUpdate()
                ->first();

            if (!$loReserva) {
                return response()->json(['Ok' => false, 'Mensaje' => 'Reserva no encontrada'], 404);
            }

            if ((int)$loReserva->Estado !== 1) {
                return response()->json(['Ok' => false, 'Mensaje' => 'La reserva no está activa'], 409);
            }

            // Tomar detalle
            $loDet = DB::connection('mysqlNegocio')
                ->table('RESERVADETALLE')
                ->where('Reserva', (int)$loReserva->Reserva)
                ->orderByDesc('ReservaDetalle')
                ->first();

            if (!$loDet) {
                return response()->json(['Ok' => false, 'Mensaje' => 'No existe detalle para liberar stock'], 500);
            }

            $tnCelda = (int)$loDet->Celda;

            // CantidadReservada (tu tabla real)
            $tnCantidad = isset($loDet->CantidadReservada) ? (int)$loDet->CantidadReservada
                : (isset($loDet->Cantidad) ? (int)$loDet->Cantidad : 0);

            if ($tnCantidad <= 0) {
                return response()->json(['Ok' => false, 'Mensaje' => 'Detalle sin cantidad válida'], 500);
            }

            $loExistencia = DB::connection('mysqlNegocio')
                ->table('EXISTENCIACELDA')
                ->where('Celda', $tnCelda)
                ->where('Estado', 1)
                ->lockForUpdate()
                ->first();

            if (!$loExistencia) {
                return response()->json(['Ok' => false, 'Mensaje' => 'No existe existencia para liberar'], 400);
            }

            $tnDisponible = (int)$loExistencia->CantidadDisponible;
            $tnReservada = (int)$loExistencia->CantidadReservada;

            if ($tnReservada < $tnCantidad) {
                return response()->json(['Ok' => false, 'Mensaje' => 'Inconsistencia: reservada menor que lo reservado'], 409);
            }

            DB::connection('mysqlNegocio')
                ->table('EXISTENCIACELDA')
                ->where('ExistenciaCelda', (int)$loExistencia->ExistenciaCelda)
                ->update([
                    'CantidadDisponible' => $tnDisponible + $tnCantidad,
                    'CantidadReservada' => $tnReservada - $tnCantidad,
                ]);

            // Marcar cancelada (ajusta Estado según tu catálogo si aplica)
            DB::connection('mysqlNegocio')
                ->table('RESERVA')
                ->where('Reserva', (int)$loReserva->Reserva)
                ->update([
                    'Estado' => 4, // 4 = cancelada (si tu tabla ESTADO maneja ese código)
                ]);

            return response()->json([
                'Ok' => true,
                'Mensaje' => 'Reserva cancelada y stock liberado',
                'Datos' => [
                    'Reserva' => (int)$loReserva->Reserva,
                    'ReservaExterna' => (string)$loReserva->ReservaExterna,
                    'Celda' => $tnCelda,
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
     * Confirma reserva: baja CantidadReservada (ya estaba descontado del disponible).
     * (La venta/pago/reembolso se integra después, este paso solo asegura stock).
     */
    public function Confirmar(int $tnReserva, ?string $tcReservaExterna = null)
    {
        return DB::connection('mysqlNegocio')->transaction(function () use ($tnReserva, $tcReservaExterna) {

            $loReserva = DB::connection('mysqlNegocio')
                ->table('RESERVA')
                ->when($tnReserva > 0, fn($q) => $q->where('Reserva', $tnReserva))
                ->when($tnReserva <= 0 && $tcReservaExterna, fn($q) => $q->where('ReservaExterna', $tcReservaExterna))
                ->lockForUpdate()
                ->first();

            if (!$loReserva) {
                return response()->json(['Ok' => false, 'Mensaje' => 'Reserva no encontrada'], 404);
            }

            if ((int)$loReserva->Estado !== 1) {
                return response()->json(['Ok' => false, 'Mensaje' => 'La reserva no está activa'], 409);
            }

            // Expirada
            if (isset($loReserva->ExpiraEn) && now()->gt($loReserva->ExpiraEn)) {
                return response()->json(['Ok' => false, 'Mensaje' => 'La reserva está expirada'], 409);
            }

            $loDet = DB::connection('mysqlNegocio')
                ->table('RESERVADETALLE')
                ->where('Reserva', (int)$loReserva->Reserva)
                ->orderByDesc('ReservaDetalle')
                ->first();

            if (!$loDet) {
                return response()->json(['Ok' => false, 'Mensaje' => 'No existe detalle de reserva'], 500);
            }

            $tnCelda = (int)$loDet->Celda;

            $tnCantidad = isset($loDet->CantidadReservada) ? (int)$loDet->CantidadReservada
                : (isset($loDet->Cantidad) ? (int)$loDet->Cantidad : 0);

            if ($tnCantidad <= 0) {
                return response()->json(['Ok' => false, 'Mensaje' => 'Detalle sin cantidad válida'], 500);
            }

            $loExistencia = DB::connection('mysqlNegocio')
                ->table('EXISTENCIACELDA')
                ->where('Celda', $tnCelda)
                ->where('Estado', 1)
                ->lockForUpdate()
                ->first();

            if (!$loExistencia) {
                return response()->json(['Ok' => false, 'Mensaje' => 'No existe existencia para confirmar'], 400);
            }

            $tnReservada = (int)$loExistencia->CantidadReservada;

            if ($tnReservada < $tnCantidad) {
                return response()->json(['Ok' => false, 'Mensaje' => 'Inconsistencia: reservada menor que lo reservado'], 409);
            }

            // Confirmar = consumir la reserva: baja reservada
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
                    'Estado' => 2, // 2 = confirmada (ajusta si aplica)
                ]);

            return response()->json([
                'Ok' => true,
                'Mensaje' => 'Reserva confirmada (stock asegurado)',
                'Datos' => [
                    'Reserva' => (int)$loReserva->Reserva,
                    'ReservaExterna' => (string)$loReserva->ReservaExterna,
                    'Celda' => $tnCelda,
                    'CantidadConfirmada' => $tnCantidad
                ]
            ]);
        });
    }
}

<?php

namespace App\Modulos\Venta\Services;

use Illuminate\Support\Facades\DB;

class VentaReversaService
{
    /**
     * SYSCOOP
     * category: Service
     * package: App\Modulos\Venta\Services
     * author: Vladimir Meriles velasquez
     * fecha: 27-02-2026
     * param: int $tnVenta
     * param: string $tcMotivo
     * return: \Illuminate\Http\JsonResponse
     *
     * Revierte venta: suma CantidadDisponible y registra VENTAREVERSA.
     */
    public function Reversa(int $tnVenta, string $tcMotivo)
    {
        /*
        ESTADO 1 = Pendiente
        ESTADO 2 = Pagado
        ESTADO 3 = Reversion
        ESTADO 4 = Anulado
        ESTADO 5 = Revision
        ESTADO 6 = Prueba
        */

        return DB::connection('mysqlNegocio')->transaction(function () use ($tnVenta, $tcMotivo) {

            $loVenta = DB::table('VENTA')
                ->where('Venta', $tnVenta)
                ->lockForUpdate()
                ->first();

            if (!$loVenta) {
                return response()->json([
                    'Ok' => false,
                    'Mensaje' => 'Venta no encontrada'
                ], 404);
            }

            if ((int)$loVenta->Estado !== 1) {
                return response()->json([
                    'Ok' => false,
                    'Mensaje' => 'La venta no está activa o ya fue revertida'
                ], 409);
            }

            $tnCelda = (int)$loVenta->Celda;
            $tnCantidad = (int)$loVenta->Cantidad;

            $loExistencia = DB::connection('mysqlNegocio')
                ->table('EXISTENCIACELDA')
                ->where('Celda', $tnCelda)
                ->where('Estado', 1)
                ->lockForUpdate()
                ->first();

            if (!$loExistencia) {
                return response()->json([
                    'Ok' => false,
                    'Mensaje' => 'No existe existencia para devolver stock'
                ], 400);
            }

            $tnDisponible = (int)$loExistencia->CantidadDisponible;

            DB::connection('mysqlNegocio')
                ->table('EXISTENCIACELDA')
                ->where('ExistenciaCelda', (int)$loExistencia->ExistenciaCelda)
                ->update([
                    'CantidadDisponible' => $tnDisponible + $tnCantidad
                ]);

            $tdAhora = now();

            DB::connection('mysqlNegocio')
                ->table('VENTAREVERSA')
                ->insert([
                    'Venta' => $tnVenta,
                    'Motivo' => $tcMotivo,
                    'FechaHora' => $tdAhora,
                    'Usr' => 0,
                    'UsrFecha' => $tdAhora->toDateString(),
                    'UsrHora' => $tdAhora->format('H:i:s')
                ]);

            DB::connection('mysqlNegocio')
                ->table('VENTA')
                ->where('Venta', $tnVenta)
                ->update([
                    'Estado' => 0
                ]);

            return response()->json([
                'Ok' => true,
                'Mensaje' => 'Venta revertida correctamente',
                'Datos' => [
                    'Venta' => $tnVenta,
                    'Celda' => $tnCelda,
                    'CantidadDevuelta' => $tnCantidad
                ]
            ]);
        });
    }
}
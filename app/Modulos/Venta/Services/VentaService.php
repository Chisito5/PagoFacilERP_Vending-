<?php

namespace App\Modulos\Venta\Services;

use Illuminate\Support\Facades\DB;

class VentaService
{
    public function Vender(int $Celda, int $Cantidad)
    {
        return DB::connection('mysqlNegocio')->transaction(function () use ($Celda, $Cantidad) {

            // 1) Obtener la celda y su máquina
            $celda = DB::connection('mysqlNegocio')
                ->table('CELDA')
                ->where('Celda', $Celda)      // PK = Celda (según tu nueva regla)
                ->where('Estado', 1)
                ->first();

            if (!$celda) {
                return response()->json([
                    'Ok' => false,
                    'Mensaje' => 'La celda no existe o está inactiva'
                ], 400);
            }

            $maquinaId = (int) $celda->Maquina;

            // 2) Bloquear existencia de esa celda
            $existencia = DB::connection('mysqlNegocio')
                ->table('EXISTENCIACELDA')
                ->where('Celda', $Celda)
                ->where('Estado', 1)
                ->lockForUpdate()
                ->first();

            if (!$existencia) {
                return response()->json([
                    'Ok' => false,
                    'Mensaje' => 'No existe stock configurado para esta celda'
                ], 400);
            }

            if ($existencia->CantidadDisponible < $Cantidad) {
                return response()->json([
                    'Ok' => false,
                    'Mensaje' => 'Stock insuficiente'
                ], 400);
            }

            // 3) Descontar stock
            DB::connection('mysqlNegocio')
                ->table('EXISTENCIACELDA')
                ->where('ExistenciaCelda', $existencia->ExistenciaCelda)
                ->update([
                    'CantidadDisponible' => $existencia->CantidadDisponible - $Cantidad
                ]);

            // 4) Registrar venta
            DB::connection('mysqlNegocio')
                ->table('VENTA')
                ->insert([
                    'Maquina' => $maquinaId,
                    'Celda' => $Celda,
                    'ProductoEmpresa' => $existencia->ProductoEmpresa,
                    'Lote' => $existencia->Lote,
                    'Cantidad' => $Cantidad,
                    'PrecioUnitario' => 10.00, // luego lo sacamos del planograma
                    'FechaVenta' => now(),
                    'Estado' => 1,
                    'Usr' => 0,
                    'UsrFecha' => now()->toDateString(),
                    'UsrHora' => now()->format('H:i:s')
                ]);

            return response()->json([
                'Ok' => true,
                'Mensaje' => 'Venta procesada correctamente'
            ]);
        });
    }
}
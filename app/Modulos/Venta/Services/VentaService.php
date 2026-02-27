<?php

namespace App\Modulos\Venta\Services;

use Illuminate\Support\Facades\DB;

class VentaService
{
    /**
     * SYSCOOP
     * category: Service
     * package: App\Modulos\Venta\Services
     * author: Vladimir Meriles velasquez
     * fecha: 27-02-2026
     * param: int $tnMaquina
     * param: string $tcCodigoSeleccion
     * param: int $tnCantidad
     * return: \Illuminate\Http\JsonResponse
     *
     * Vende en base a (Maquina + CodigoSeleccion) y descuenta stock.
     */
    public function VenderPorSeleccion(int $tnMaquina, string $tcCodigoSeleccion, int $tnCantidad)
    {
        return DB::connection('mysqlNegocio')->transaction(function () use ($tnMaquina, $tcCodigoSeleccion, $tnCantidad) {

            // 1) Buscar celda real (ID) perteneciente a la máquina
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

            $tnCelda = (int) $loCelda->Celda;

            // 2) Bloquear existencia
            $loExistencia = DB::connection('mysqlNegocio')
                ->table('EXISTENCIACELDA')
                ->where('Celda', $tnCelda)
                ->where('Estado', 1)
                ->lockForUpdate()
                ->first();

            if (!$loExistencia) {
                return response()->json([
                    'Ok' => false,
                    'Mensaje' => 'No existe stock configurado para esta celda'
                ], 400);
            }

            if ((int)$loExistencia->CantidadDisponible < $tnCantidad) {
                return response()->json([
                    'Ok' => false,
                    'Mensaje' => 'Stock insuficiente'
                ], 400);
            }

            // 3) Descontar stock
            DB::connection('mysqlNegocio')
                ->table('EXISTENCIACELDA')
                ->where('ExistenciaCelda', (int)$loExistencia->ExistenciaCelda)
                ->update([
                    'CantidadDisponible' => (int)$loExistencia->CantidadDisponible - $tnCantidad
                ]);

            // 4) Registrar venta (precio fijo por ahora)
            $tnPrecioUnitario = 10.00;

            DB::connection('mysqlNegocio')
                ->table('VENTA')
                ->insert([
                    'Maquina' => $tnMaquina,
                    'Celda' => $tnCelda,
                    'ProductoEmpresa' => (int)$loExistencia->ProductoEmpresa,
                    'Lote' => (int)$loExistencia->Lote,
                    'Cantidad' => $tnCantidad,
                    'PrecioUnitario' => $tnPrecioUnitario,
                    'FechaVenta' => now(),
                    'Estado' => 1,
                    'Usr' => 0,
                    'UsrFecha' => now()->toDateString(),
                    'UsrHora' => now()->format('H:i:s')
                ]);

            return response()->json([
                'Ok' => true,
                'Mensaje' => 'Venta procesada correctamente',
                'Datos' => [
                    'Maquina' => $tnMaquina,
                    'Celda' => $tnCelda,
                    'CodigoSeleccion' => $tcCodigoSeleccion,
                    'Cantidad' => $tnCantidad,
                    'PrecioUnitario' => $tnPrecioUnitario
                ]
            ]);
        });
    }

    /**
     * SYSCOOP
     * category: Service
     * package: App\Modulos\Venta\Services
     * author: Vladimir Meriles velasquez
     * fecha: 27-02-2026
     * return: \Illuminate\Http\JsonResponse
     *
     * Lista ventas (últimas primero).
     */
    public function Listar()
    {
        $loVentas = DB::connection('mysqlNegocio')
            ->table('VENTA')
            ->orderByDesc('Venta')
            ->limit(200)
            ->get();

        return response()->json([
            'Ok' => true,
            'Mensaje' => 'Listado de ventas',
            'Datos' => $loVentas
        ]);
    }

    /**
     * SYSCOOP
     * category: Service
     * package: App\Modulos\Venta\Services
     * author: Vladimir Meriles velasquez
     * fecha: 27-02-2026
     * param: int $tnMaquina
     * return: \Illuminate\Http\JsonResponse
     *
     * Lista ventas por máquina.
     */
    public function ListarPorMaquina(int $tnMaquina)
    {
        $loVentas = DB::connection('mysqlNegocio')
            ->table('VENTA')
            ->where('Maquina', $tnMaquina)
            ->orderByDesc('Venta')
            ->limit(200)
            ->get();

        return response()->json([
            'Ok' => true,
            'Mensaje' => 'Listado de ventas por máquina',
            'Datos' => $loVentas
        ]);
    }
}
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
     * Vende en base a (Maquina + CodigoSeleccion), descuenta stock y registra VENTA.
     * PrecioUnitario se obtiene desde PLANOGRAMACELDA del planograma activo.
     */
    public function VenderPorSeleccion(int $tnMaquina, string $tcCodigoSeleccion, int $tnCantidad)
    {
        return DB::connection('mysqlNegocio')->transaction(function () use ($tnMaquina, $tcCodigoSeleccion, $tnCantidad) {

            // 1) Buscar CELDA por (Maquina + CodigoSeleccion)
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

            // 2) Obtener planograma activo (última versión)
            $loPlanograma = DB::connection('mysqlNegocio')
                ->table('PLANOGRAMA')
                ->where('Maquina', $tnMaquina)
                ->where('Estado', 1)
                ->orderByDesc('VersionPlanograma')
                ->first();

            if (!$loPlanograma) {
                return response()->json([
                    'Ok' => false,
                    'Mensaje' => 'No existe planograma activo para la máquina'
                ], 400);
            }

            // 3) Obtener precio por celda desde PLANOGRAMACELDA
            $loPlanogramaCelda = DB::connection('mysqlNegocio')
                ->table('PLANOGRAMACELDA')
                ->where('Planograma', (int)$loPlanograma->Planograma)
                ->where('Celda', $tnCelda)
                ->where('Estado', 1)
                ->first();

            if (!$loPlanogramaCelda) {
                return response()->json([
                    'Ok' => false,
                    'Mensaje' => 'La celda no tiene precio configurado en el planograma'
                ], 400);
            }

            $tnPrecioUnitario = (float)$loPlanogramaCelda->PrecioVenta;

            // 4) Bloquear existencia para descontar stock de forma segura
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

            // 5) Descontar stock
            DB::connection('mysqlNegocio')
                ->table('EXISTENCIACELDA')
                ->where('ExistenciaCelda', (int)$loExistencia->ExistenciaCelda)
                ->update([
                    'CantidadDisponible' => (int)$loExistencia->CantidadDisponible - $tnCantidad
                ]);

            // 6) Registrar venta
            $tdAhora = now();

            DB::connection('mysqlNegocio')
                ->table('VENTA')
                ->insert([
                    'Maquina' => $tnMaquina,
                    'Celda' => $tnCelda,
                    'ProductoEmpresa' => (int)$loExistencia->ProductoEmpresa,
                    'Lote' => (int)$loExistencia->Lote,
                    'Cantidad' => $tnCantidad,
                    'PrecioUnitario' => $tnPrecioUnitario,
                    'FechaVenta' => $tdAhora,
                    'Estado' => 1, // Activo
                    'Usr' => 0,
                    'UsrFecha' => $tdAhora->toDateString(),
                    'UsrHora' => $tdAhora->format('H:i:s')
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
     * Lista ventas por máquina (últimas primero).
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
     * Reversa una venta: devuelve stock y marca la venta como Inactiva (Estado=2).
     * Inserta registro en VENTAREVERSA.
     */
    public function Reversar(int $tnVenta, string $tcMotivo)
    {
        return DB::connection('mysqlNegocio')->transaction(function () use ($tnVenta, $tcMotivo) {

            // 1) Bloquear venta
            $loVenta = DB::connection('mysqlNegocio')
                ->table('VENTA')
                ->where('Venta', $tnVenta)
                ->lockForUpdate()
                ->first();

            if (!$loVenta) {
                return response()->json([
                    'Ok' => false,
                    'Mensaje' => 'Venta no encontrada'
                ], 404);
            }

            // Solo se revierte si está Activa
            if ((int)$loVenta->Estado !== 1) {
                return response()->json([
                    'Ok' => false,
                    'Mensaje' => 'La venta no está activa o ya fue revertida'
                ], 409);
            }

            $tnCelda = (int)$loVenta->Celda;
            $tnCantidad = (int)$loVenta->Cantidad;

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
                    'Mensaje' => 'No existe existencia para devolver stock'
                ], 400);
            }

            // 3) Devolver stock
            DB::connection('mysqlNegocio')
                ->table('EXISTENCIACELDA')
                ->where('ExistenciaCelda', (int)$loExistencia->ExistenciaCelda)
                ->update([
                    'CantidadDisponible' => (int)$loExistencia->CantidadDisponible + $tnCantidad
                ]);

            // 4) Registrar VENTAREVERSA
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

            // 5) Marcar venta como Inactiva (Estado=2) para cumplir FK de ESTADO
            DB::connection('mysqlNegocio')
                ->table('VENTA')
                ->where('Venta', $tnVenta)
                ->update([
                    'Estado' => 2
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
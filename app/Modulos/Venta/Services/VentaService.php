<?php

namespace App\Modulos\Venta\Services;

use Illuminate\Support\Facades\DB;

class VentaService
{
    /**
     * Metodo que registra una venta en base a tnMaquina, tcCodigoSeleccion y tnCantidad
     * - Resuelve Celda por (Maquina + CodigoSeleccion)
     * - Valida stock en EXISTENCIACELDA
     * - Obtiene PrecioUnitario desde PLANOGRAMACELDA.PrecioVenta (planograma activo)
     * - Descuenta stock e inserta en VENTA
     *
     * @method      Vender()
     * @author      Vladimir Meriles 
     * @fecha       26-02-2026
     * @param       int    $tnMaquina
     * @param       string $tcCodigoSeleccion
     * @param       int    $tnCantidad
     * @return      \Illuminate\Http\JsonResponse
     */
    public function Vender(int $tnMaquina, string $tcCodigoSeleccion, int $tnCantidad)
    {
        return DB::connection('mysqlNegocio')->transaction(function () use ($tnMaquina, $tcCodigoSeleccion, $tnCantidad) {

            // 1) Resolver Celda por Maquina + CodigoSeleccion
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

            $lnCelda = (int) $loCelda->Celda;

            // 2) Bloquear existencia de esa celda
            $loExistencia = DB::connection('mysqlNegocio')
                ->table('EXISTENCIACELDA')
                ->where('Celda', $lnCelda)
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

            // 3) Obtener Planograma activo (FechaFin NULL) para la maquina
            $loPlanograma = DB::connection('mysqlNegocio')
                ->table('PLANOGRAMA')
                ->where('Maquina', $tnMaquina)
                ->where('Estado', 1)
                ->whereNull('FechaFin')
                ->orderByDesc('VersionPlanograma')
                ->first();

            if (!$loPlanograma) {
                return response()->json([
                    'Ok' => false,
                    'Mensaje' => 'No existe un planograma activo para esta máquina'
                ], 400);
            }

            $lnPlanograma = (int) $loPlanograma->Planograma;

            // 4) Obtener precio desde PLANOGRAMACELDA
            $loPlanogramaCelda = DB::connection('mysqlNegocio')
                ->table('PLANOGRAMACELDA')
                ->where('Planograma', $lnPlanograma)
                ->where('Celda', $lnCelda)
                ->where('Estado', 1)
                ->first();

            if (!$loPlanogramaCelda) {
                return response()->json([
                    'Ok' => false,
                    'Mensaje' => 'La celda no está configurada en el planograma activo'
                ], 400);
            }

            // (Opcional recomendado) validar que el ProductoEmpresa coincida con existencia
            if ((int)$loPlanogramaCelda->ProductoEmpresa !== (int)$loExistencia->ProductoEmpresa) {
                return response()->json([
                    'Ok' => false,
                    'Mensaje' => 'ProductoEmpresa no coincide entre planograma y existencia'
                ], 400);
            }

            $lnPrecioUnitario = (float) ($loPlanogramaCelda->PrecioVenta ?? 0);

            if ($lnPrecioUnitario <= 0) {
                return response()->json([
                    'Ok' => false,
                    'Mensaje' => 'PrecioVenta inválido en planograma'
                ], 400);
            }

            // 5) Descontar stock
            DB::connection('mysqlNegocio')
                ->table('EXISTENCIACELDA')
                ->where('ExistenciaCelda', $loExistencia->ExistenciaCelda)
                ->update([
                    'CantidadDisponible' => (int)$loExistencia->CantidadDisponible - $tnCantidad
                ]);

            // 6) Registrar venta
            DB::connection('mysqlNegocio')
                ->table('VENTA')
                ->insert([
                    'Maquina' => $tnMaquina,
                    'Celda' => $lnCelda,
                    'ProductoEmpresa' => $loExistencia->ProductoEmpresa,
                    'Lote' => $loExistencia->Lote,
                    'Cantidad' => $tnCantidad,
                    'PrecioUnitario' => $lnPrecioUnitario,
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
                    'Celda' => $lnCelda,
                    'CodigoSeleccion' => $tcCodigoSeleccion,
                    'Cantidad' => $tnCantidad,
                    'PrecioUnitario' => $lnPrecioUnitario
                ]
            ]);
        });
    }
}
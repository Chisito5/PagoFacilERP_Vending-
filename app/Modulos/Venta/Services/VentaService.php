<?php

namespace App\Modulos\Venta\Services;

use Illuminate\Support\Facades\DB;

/**
 *
 * Servicio que gestiona el proceso de Venta (validación, descuento e inserción de registro).
 *
 * @category     PagoFacil
 * @package      Venta
 * @author       Equipo PagoFacil
 * @fecha        26-02-2026
 */
class VentaService
{
    /**
     * Realiza una venta por (Maquina + CodigoSeleccion).
     *
     * @method      Vender()
     * @author      Equipo PagoFacil
     * @fecha       26-02-2026
     * @param       int $tnMaquina
     * @param       string $tcCodigoSeleccion
     * @param       int $tnCantidad
     */
    public function Vender(int $tnMaquina, string $tcCodigoSeleccion, int $tnCantidad)
    {
        return DB::connection('mysqlNegocio')->transaction(function () use ($tnMaquina, $tcCodigoSeleccion, $tnCantidad) {

            // 1) Buscar celda por máquina + código (A1, A2, etc.)
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

            $lnCelda = (int)$loCelda->Celda;

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

            // 3) Descontar stock
            DB::connection('mysqlNegocio')
                ->table('EXISTENCIACELDA')
                ->where('ExistenciaCelda', $loExistencia->ExistenciaCelda)
                ->update([
                    'CantidadDisponible' => (int)$loExistencia->CantidadDisponible - $tnCantidad
                ]);

            // 4) Registrar venta
            DB::connection('mysqlNegocio')
                ->table('VENTA')
                ->insert([
                    'Maquina' => $tnMaquina,
                    'Celda' => $lnCelda,
                    'ProductoEmpresa' => $loExistencia->ProductoEmpresa,
                    'Lote' => $loExistencia->Lote,
                    'Cantidad' => $tnCantidad,
                    'PrecioUnitario' => 10.00, // siguiente micropaso: tomar PrecioVenta desde PLANOGRAMACELDA
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

<?php

namespace App\Modulos\Venta\Services;

use App\Support\EstadoCatalogo;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class VentaService
{
    private const CODIGO_VENTA_ACTIVA = 1;
    private const CODIGO_VENTA_REVERTIDA = 2;

    public function __construct(private EstadoCatalogo $toEstadoCatalogo)
    {
    }

    public function VenderPorSeleccion(int $tnMaquina, string $tcCodigoSeleccion, int $tnCantidad)
    {
        return DB::connection('mysqlNegocio')->transaction(function () use ($tnMaquina, $tcCodigoSeleccion, $tnCantidad) {
            try {
                $tnEstadoVentaActiva = $this->toEstadoCatalogo->obtenerId('VENTA', self::CODIGO_VENTA_ACTIVA);
            } catch (RuntimeException $toEx) {
                return response()->json(['Ok' => false, 'Mensaje' => $toEx->getMessage()], 500);
            }

            $loCelda = DB::connection('mysqlNegocio')
                ->table('CELDA')
                ->where('Maquina', $tnMaquina)
                ->where('CodigoSeleccion', $tcCodigoSeleccion)
                ->where('Estado', 1)
                ->first();

            if (!$loCelda) {
                return response()->json(['Ok' => false, 'Mensaje' => 'La celda no existe para esta maquina o esta inactiva'], 400);
            }

            $tnCelda = (int)$loCelda->Celda;

            $loPlanograma = DB::connection('mysqlNegocio')
                ->table('PLANOGRAMA')
                ->where('Maquina', $tnMaquina)
                ->where('Estado', 1)
                ->orderByDesc('VersionPlanograma')
                ->first();

            if (!$loPlanograma) {
                return response()->json(['Ok' => false, 'Mensaje' => 'No existe planograma activo para la maquina'], 400);
            }

            $loPlanogramaCelda = DB::connection('mysqlNegocio')
                ->table('PLANOGRAMACELDA')
                ->where('Planograma', (int)$loPlanograma->Planograma)
                ->where('Celda', $tnCelda)
                ->where('Estado', 1)
                ->first();

            if (!$loPlanogramaCelda) {
                return response()->json(['Ok' => false, 'Mensaje' => 'La celda no tiene precio configurado en el planograma'], 400);
            }

            $tnPrecioUnitario = (float)$loPlanogramaCelda->PrecioVenta;

            $loExistencia = DB::connection('mysqlNegocio')
                ->table('EXISTENCIACELDA')
                ->where('Celda', $tnCelda)
                ->where('Estado', 1)
                ->lockForUpdate()
                ->first();

            if (!$loExistencia) {
                return response()->json(['Ok' => false, 'Mensaje' => 'No existe stock configurado para esta celda'], 400);
            }

            if ((int)$loExistencia->CantidadDisponible < $tnCantidad) {
                return response()->json(['Ok' => false, 'Mensaje' => 'Stock insuficiente'], 400);
            }

            DB::connection('mysqlNegocio')
                ->table('EXISTENCIACELDA')
                ->where('ExistenciaCelda', (int)$loExistencia->ExistenciaCelda)
                ->update([
                    'CantidadDisponible' => (int)$loExistencia->CantidadDisponible - $tnCantidad
                ]);

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
                    'Estado' => $tnEstadoVentaActiva,
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

    public function Listar()
    {
        $loVentas = DB::connection('mysqlNegocio')
            ->table('VENTA')
            ->orderByDesc('Venta')
            ->limit(200)
            ->get();

        return response()->json(['Ok' => true, 'Mensaje' => 'Listado de ventas', 'Datos' => $loVentas]);
    }

    public function ListarPorMaquina(int $tnMaquina)
    {
        $loVentas = DB::connection('mysqlNegocio')
            ->table('VENTA')
            ->where('Maquina', $tnMaquina)
            ->orderByDesc('Venta')
            ->limit(200)
            ->get();

        return response()->json(['Ok' => true, 'Mensaje' => 'Listado de ventas por maquina', 'Datos' => $loVentas]);
    }

    public function Reversar(int $tnVenta, string $tcMotivo)
    {
        return DB::connection('mysqlNegocio')->transaction(function () use ($tnVenta, $tcMotivo) {
            try {
                $tnEstadoVentaActiva = $this->toEstadoCatalogo->obtenerId('VENTA', self::CODIGO_VENTA_ACTIVA);
                $tnEstadoVentaRevertida = $this->toEstadoCatalogo->obtenerId('VENTA', self::CODIGO_VENTA_REVERTIDA);
            } catch (RuntimeException $toEx) {
                return response()->json(['Ok' => false, 'Mensaje' => $toEx->getMessage()], 500);
            }

            $loVenta = DB::connection('mysqlNegocio')
                ->table('VENTA')
                ->where('Venta', $tnVenta)
                ->lockForUpdate()
                ->first();

            if (!$loVenta) {
                return response()->json(['Ok' => false, 'Mensaje' => 'Venta no encontrada'], 404);
            }

            $tnEstadoActual = (int)$loVenta->Estado;
            if ($tnEstadoActual === $tnEstadoVentaRevertida) {
                return response()->json([
                    'Ok' => true,
                    'Mensaje' => 'Venta ya revertida',
                    'Datos' => ['Venta' => $tnVenta]
                ]);
            }

            if ($tnEstadoActual !== $tnEstadoVentaActiva) {
                return response()->json(['Ok' => false, 'Mensaje' => 'La venta no esta en estado reversible'], 409);
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
                return response()->json(['Ok' => false, 'Mensaje' => 'No existe existencia para devolver stock'], 400);
            }

            DB::connection('mysqlNegocio')
                ->table('EXISTENCIACELDA')
                ->where('ExistenciaCelda', (int)$loExistencia->ExistenciaCelda)
                ->update([
                    'CantidadDisponible' => (int)$loExistencia->CantidadDisponible + $tnCantidad
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
                    'Estado' => $tnEstadoVentaRevertida
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

<?php

namespace App\Modulos\Venta\Services;

use App\Events\EventoStockActualizado;
use App\Events\EventoVentaCreada;
use App\Events\EventoVentaReversada;
use App\Support\EstadoCatalogo;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class VentaService
{
    private const CODIGO_VENTA_ACTIVA = 1;
    private const CODIGO_VENTA_REVERTIDA = 2;

    public function __construct(private EstadoCatalogo $toEstadoCatalogo)
    {
    }

    public function VenderPorSeleccion(int $tnMaquina, string $tcCodigoSeleccion, int $tnCantidad): JsonResponse
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

            $tnCantidadDisponibleNueva = (int)$loExistencia->CantidadDisponible - $tnCantidad;

            DB::connection('mysqlNegocio')
                ->table('EXISTENCIACELDA')
                ->where('ExistenciaCelda', (int)$loExistencia->ExistenciaCelda)
                ->update([
                    'CantidadDisponible' => $tnCantidadDisponibleNueva
                ]);

            $tdAhora = now();
            $tnVenta = (int)DB::connection('mysqlNegocio')
                ->table('VENTA')
                ->insertGetId([
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

            $tnEmpresa = $this->obtenerEmpresaPorMaquina($tnMaquina);
            $this->emitirEventoSeguro(new EventoVentaCreada($tnMaquina, $tnEmpresa, [
                'Venta' => $tnVenta,
                'Celda' => $tnCelda,
                'CodigoSeleccion' => $tcCodigoSeleccion,
                'Cantidad' => $tnCantidad,
                'PrecioUnitario' => $tnPrecioUnitario,
            ]), 'venta.creada');
            $this->emitirEventoSeguro(new EventoStockActualizado($tnMaquina, $tnEmpresa, [
                'Origen' => 'venta',
                'Venta' => $tnVenta,
                'Celda' => $tnCelda,
                'CodigoSeleccion' => $tcCodigoSeleccion,
                'CantidadDisponible' => $tnCantidadDisponibleNueva,
                'CantidadReservada' => (int)$loExistencia->CantidadReservada,
            ]), 'stock.actualizado.venta');

            return response()->json([
                'Ok' => true,
                'Mensaje' => 'Venta procesada correctamente',
                'Datos' => [
                    'Venta' => $tnVenta,
                    'Maquina' => $tnMaquina,
                    'Celda' => $tnCelda,
                    'CodigoSeleccion' => $tcCodigoSeleccion,
                    'Cantidad' => $tnCantidad,
                    'PrecioUnitario' => $tnPrecioUnitario
                ]
            ]);
        });
    }

    public function Listar(int $tnPagina = 1, int $tnTamanoPagina = 20): LengthAwarePaginator
    {
        $tnTamanoPagina = max(1, min($tnTamanoPagina, 200));

        return DB::connection('mysqlNegocio')
            ->table('VENTA')
            ->orderByDesc('Venta')
            ->paginate($tnTamanoPagina, ['*'], 'Pagina', max(1, $tnPagina));
    }

    public function ListarPorMaquina(int $tnMaquina, int $tnPagina = 1, int $tnTamanoPagina = 20): LengthAwarePaginator
    {
        $tnTamanoPagina = max(1, min($tnTamanoPagina, 200));

        return DB::connection('mysqlNegocio')
            ->table('VENTA')
            ->where('Maquina', $tnMaquina)
            ->orderByDesc('Venta')
            ->paginate($tnTamanoPagina, ['*'], 'Pagina', max(1, $tnPagina));
    }

    public function Reversar(int $tnVenta, string $tcMotivo): JsonResponse
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

            $tnCantidadDisponibleNueva = (int)$loExistencia->CantidadDisponible + $tnCantidad;

            DB::connection('mysqlNegocio')
                ->table('EXISTENCIACELDA')
                ->where('ExistenciaCelda', (int)$loExistencia->ExistenciaCelda)
                ->update([
                    'CantidadDisponible' => $tnCantidadDisponibleNueva
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

            $tnEmpresa = $this->obtenerEmpresaPorMaquina((int)$loVenta->Maquina);
            $this->emitirEventoSeguro(new EventoVentaReversada((int)$loVenta->Maquina, $tnEmpresa, [
                'Venta' => $tnVenta,
                'Motivo' => $tcMotivo,
                'Celda' => $tnCelda,
                'CantidadDevuelta' => $tnCantidad,
            ]), 'venta.reversada');
            $this->emitirEventoSeguro(new EventoStockActualizado((int)$loVenta->Maquina, $tnEmpresa, [
                'Origen' => 'reversa',
                'Venta' => $tnVenta,
                'Celda' => $tnCelda,
                'CantidadDisponible' => $tnCantidadDisponibleNueva,
                'CantidadReservada' => (int)$loExistencia->CantidadReservada,
            ]), 'stock.actualizado.reversa');

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

    private function obtenerEmpresaPorMaquina(int $tnMaquina): ?int
    {
        $loMaquina = DB::connection('mysqlNegocio')
            ->table('MAQUINA as m')
            ->leftJoin('UBICACION as u', 'u.Ubicacion', '=', 'm.UbicacionActual')
            ->where('m.Maquina', $tnMaquina)
            ->select('u.Empresa')
            ->first();

        if (!$loMaquina || !isset($loMaquina->Empresa)) {
            return null;
        }

        return (int)$loMaquina->Empresa;
    }

    private function emitirEventoSeguro(object $toEvento, string $tcContexto): void
    {
        try {
            event($toEvento);
        } catch (Throwable $toEx) {
            Log::warning('evento_tiempo_real_fallido', [
                'contexto' => $tcContexto,
                'modulo' => 'venta',
                'error' => $toEx->getMessage(),
            ]);
        }
    }
}

<?php

namespace App\Modulos\Reposicion\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use stdClass;

class ReposicionService
{
    private const ESTADO_ACTIVO = 1;
    private const NOMBRE_TIPO_MOV_REPOSICION = 'REPOSICION';

    /**
     * SYSCOOP
     * category: Service
     * package: App\Modulos\Reposicion\Services
     * author: Vladimir Meriles velasquez
     * fecha: 27-02-2026
     * param: int $tnMaquina
     * param: string $tcCodigoSeleccion
     * param: int $tnCantidad
     * param: int $tnUsuarioOperador
     * param: ?int $tnProductoEmpresa
     * param: ?int $tnLote
     * param: ?string $tcObservacion
     * return: \Illuminate\Http\JsonResponse
     *
     * Recarga stock en base a (Maquina + CodigoSeleccion) y registra REPOSICION.
     */
    public function RecargarPorSeleccion(
        int $tnMaquina,
        string $tcCodigoSeleccion,
        int $tnCantidad,
        int $tnUsuarioOperador,
        ?int $tnProductoEmpresa = null,
        ?int $tnLote = null,
        ?string $tcObservacion = null
    ) {
        return DB::connection('mysqlNegocio')->transaction(function () use (
            $tnMaquina,
            $tcCodigoSeleccion,
            $tnCantidad,
            $tnUsuarioOperador,
            $tnProductoEmpresa,
            $tnLote,
            $tcObservacion
        ) {
            $loUsuarioOperador = DB::connection('mysqlNegocio')
                ->table('USUARIO')
                ->where('Usuario', $tnUsuarioOperador)
                ->where('Estado', self::ESTADO_ACTIVO)
                ->first();

            if (!$loUsuarioOperador) {
                return response()->json([
                    'Ok' => false,
                    'Mensaje' => 'UsuarioOperador no existe o esta inactivo'
                ], 400);
            }

            $loCelda = $this->obtenerCeldaPorSeleccion($tnMaquina, $tcCodigoSeleccion);
            if (!$loCelda) {
                return response()->json([
                    'Ok' => false,
                    'Mensaje' => 'La celda no existe para esta maquina o esta inactiva'
                ], 400);
            }

            $tnCelda = (int)$loCelda->Celda;
            $loExistencia = $this->obtenerExistenciaConLock($tnCelda);
            if (!$loExistencia) {
                return response()->json([
                    'Ok' => false,
                    'Mensaje' => 'No existe existencia configurada para esta celda'
                ], 400);
            }

            $tnProductoEmpresaFinal = $tnProductoEmpresa !== null ? $tnProductoEmpresa : (int)$loExistencia->ProductoEmpresa;
            $tnLoteFinal = $tnLote !== null ? $tnLote : (isset($loExistencia->Lote) ? (int)$loExistencia->Lote : null);

            if ($tnLoteFinal === null || $tnLoteFinal <= 0) {
                return response()->json([
                    'Ok' => false,
                    'Mensaje' => 'Lote requerido para control de integridad'
                ], 400);
            }

            $loLote = $this->obtenerLoteConLock($tnLoteFinal);
            if (!$loLote) {
                return response()->json([
                    'Ok' => false,
                    'Mensaje' => 'Lote no encontrado o inactivo'
                ], 400);
            }

            if (!$this->validarLoteCorrespondeProductoEmpresa($tnLoteFinal, $tnProductoEmpresaFinal)) {
                return response()->json([
                    'Ok' => false,
                    'Mensaje' => 'El lote no corresponde al ProductoEmpresa indicado'
                ], 409);
            }

            $tnCapacidadMaxima = (int)$loCelda->CapacidadMaxima;
            $tnOcupadoActual = (int)$loExistencia->CantidadDisponible + (int)$loExistencia->CantidadReservada;
            $tnOcupadoNuevoCelda = $tnOcupadoActual + $tnCantidad;

            if (!$this->validarCapacidadCelda($tnCapacidadMaxima, $tnOcupadoNuevoCelda)) {
                Log::warning('reposicion_conflicts_capacidad', [
                    'Maquina' => $tnMaquina,
                    'Celda' => $tnCelda,
                    'CapacidadMaxima' => $tnCapacidadMaxima,
                    'OcupadoActual' => $tnOcupadoActual,
                    'CantidadAgregada' => $tnCantidad,
                    'OcupadoNuevo' => $tnOcupadoNuevoCelda,
                ]);

                return response()->json([
                    'Ok' => false,
                    'Mensaje' => 'Capacidad excedida'
                ], 409);
            }

            $tnOcupadoFilaActual = (int)$loExistencia->CantidadDisponible + (int)$loExistencia->CantidadReservada;
            $tnOcupadoFilaNuevo = $tnOcupadoFilaActual + $tnCantidad;

            $tnCantidadInicialLote = (int)$loLote->CantidadInicial;
            if ($tnCantidadInicialLote <= 0) {
                return response()->json([
                    'Ok' => false,
                    'Mensaje' => 'Lote sin capacidad definida'
                ], 400);
            }

            $lbLoteValido = $this->validarCapacidadLote(
                isset($loExistencia->Lote) ? (int)$loExistencia->Lote : null,
                $tnLoteFinal,
                $tnOcupadoFilaActual,
                $tnOcupadoFilaNuevo,
                $tnCantidadInicialLote
            );

            if (!$lbLoteValido) {
                Log::warning('reposicion_conflicts_lote', [
                    'Lote' => $tnLoteFinal,
                    'CantidadInicial' => $tnCantidadInicialLote,
                    'ExistenciaCelda' => (int)$loExistencia->ExistenciaCelda,
                    'OcupadoFilaActual' => $tnOcupadoFilaActual,
                    'OcupadoFilaNuevo' => $tnOcupadoFilaNuevo,
                ]);

                return response()->json([
                    'Ok' => false,
                    'Mensaje' => 'Lote insuficiente'
                ], 409);
            }

            $tnNuevaCantidad = (int)$loExistencia->CantidadDisponible + $tnCantidad;

            DB::connection('mysqlNegocio')
                ->table('EXISTENCIACELDA')
                ->where('ExistenciaCelda', (int)$loExistencia->ExistenciaCelda)
                ->update([
                    'ProductoEmpresa' => $tnProductoEmpresaFinal,
                    'Lote' => $tnLoteFinal,
                    'CantidadDisponible' => $tnNuevaCantidad
                ]);

            $tdAhora = now();
            $tnReposicion = DB::connection('mysqlNegocio')
                ->table('REPOSICION')
                ->insertGetId([
                    'Maquina' => $tnMaquina,
                    'UsuarioOperador' => $tnUsuarioOperador,
                    'FechaHoraInicio' => $tdAhora,
                    'FechaHoraFin' => $tdAhora,
                    'Observacion' => $tcObservacion,
                    'Estado' => self::ESTADO_ACTIVO,
                    'Usr' => 0,
                    'UsrFecha' => $tdAhora->toDateString(),
                    'UsrHora' => $tdAhora->format('H:i:s'),
                ]);

            if (Schema::connection('mysqlNegocio')->hasTable('REPOSICIONDETALLE')) {
                DB::connection('mysqlNegocio')
                    ->table('REPOSICIONDETALLE')
                    ->insert([
                        'Reposicion' => $tnReposicion,
                        'Celda' => $tnCelda,
                        'ProductoEmpresa' => $tnProductoEmpresaFinal,
                        'Lote' => $tnLoteFinal,
                        'CantidadAgregada' => $tnCantidad,
                        'Estado' => self::ESTADO_ACTIVO,
                        'Usr' => 0,
                        'UsrFecha' => $tdAhora->toDateString(),
                        'UsrHora' => $tdAhora->format('H:i:s'),
                    ]);
            }

            $this->registrarMovimientoInventarioReposicion(
                $tnReposicion,
                $tnMaquina,
                $tnCelda,
                $tnProductoEmpresaFinal,
                $tnLoteFinal,
                $tnCantidad,
                $tdAhora,
                $tcObservacion
            );

            return response()->json([
                'Ok' => true,
                'Mensaje' => 'Stock recargado correctamente',
                'Datos' => [
                    'Reposicion' => $tnReposicion,
                    'Maquina' => $tnMaquina,
                    'Celda' => $tnCelda,
                    'CodigoSeleccion' => $tcCodigoSeleccion,
                    'ExistenciaCelda' => (int)$loExistencia->ExistenciaCelda,
                    'ProductoEmpresa' => $tnProductoEmpresaFinal,
                    'Lote' => $tnLoteFinal,
                    'CantidadAgregada' => $tnCantidad,
                    'CantidadDisponible' => $tnNuevaCantidad,
                    'CapacidadMaximaCelda' => $tnCapacidadMaxima,
                    'CapacidadLote' => $tnCantidadInicialLote,
                ]
            ]);
        });
    }

    /**
     * SYSCOOP
     * category: Service
     * package: App\Modulos\Reposicion\Services
     * author: Vladimir Meriles velasquez
     * fecha: 27-02-2026
     * return: \Illuminate\Http\JsonResponse
     *
     * Lista reposiciones (ultimas primero).
     */
    public function Listar()
    {
        $loDatos = DB::connection('mysqlNegocio')
            ->table('REPOSICION')
            ->orderByDesc('Reposicion')
            ->limit(200)
            ->get();

        return response()->json([
            'Ok' => true,
            'Mensaje' => 'Listado de reposiciones',
            'Datos' => $loDatos
        ]);
    }

    /**
     * SYSCOOP
     * category: Service
     * package: App\Modulos\Reposicion\Services
     * author: Vladimir Meriles velasquez
     * fecha: 27-02-2026
     * param: int $tnMaquina
     * return: \Illuminate\Http\JsonResponse
     *
     * Lista reposiciones por maquina.
     */
    public function ListarPorMaquina(int $tnMaquina)
    {
        $loDatos = DB::connection('mysqlNegocio')
            ->table('REPOSICION')
            ->where('Maquina', $tnMaquina)
            ->orderByDesc('Reposicion')
            ->limit(200)
            ->get();

        return response()->json([
            'Ok' => true,
            'Mensaje' => 'Listado de reposiciones por maquina',
            'Datos' => $loDatos
        ]);
    }

    /**
     * SYSCOOP
     * category: Service
     * package: App\Modulos\Reposicion\Services
     * author: Vladimir Meriles velasquez
     * fecha: 27-02-2026
     * param: int $tnReposicion
     * return: \Illuminate\Http\JsonResponse
     *
     * Obtiene una reposicion por ID.
     */
    public function Obtener(int $tnReposicion)
    {
        $loDato = DB::connection('mysqlNegocio')
            ->table('REPOSICION')
            ->where('Reposicion', $tnReposicion)
            ->first();

        if (!$loDato) {
            return response()->json([
                'Ok' => false,
                'Mensaje' => 'Reposicion no encontrada'
            ], 404);
        }

        return response()->json([
            'Ok' => true,
            'Mensaje' => 'Reposicion encontrada',
            'Datos' => $loDato
        ]);
    }

    private function obtenerCeldaPorSeleccion(int $tnMaquina, string $tcCodigoSeleccion): ?stdClass
    {
        return DB::connection('mysqlNegocio')
            ->table('CELDA')
            ->where('Maquina', $tnMaquina)
            ->where('CodigoSeleccion', $tcCodigoSeleccion)
            ->where('Estado', self::ESTADO_ACTIVO)
            ->lockForUpdate()
            ->first();
    }

    private function obtenerExistenciaConLock(int $tnCelda): ?stdClass
    {
        return DB::connection('mysqlNegocio')
            ->table('EXISTENCIACELDA')
            ->where('Celda', $tnCelda)
            ->where('Estado', self::ESTADO_ACTIVO)
            ->lockForUpdate()
            ->first();
    }

    private function obtenerLoteConLock(int $tnLote): ?stdClass
    {
        return DB::connection('mysqlNegocio')
            ->table('LOTE')
            ->where('Lote', $tnLote)
            ->where('Estado', self::ESTADO_ACTIVO)
            ->lockForUpdate()
            ->first();
    }

    private function validarCapacidadCelda(int $tnCapacidadMaxima, int $tnOcupadoNuevoCelda): bool
    {
        if ($tnCapacidadMaxima <= 0) {
            return false;
        }

        return $tnOcupadoNuevoCelda <= $tnCapacidadMaxima;
    }

    private function validarCapacidadLote(
        ?int $tnLoteActual,
        int $tnLote,
        int $tnOcupadoFilaActual,
        int $tnOcupadoFilaNuevo,
        int $tnCantidadInicialLote
    ): bool {
        $laExistenciasLote = DB::connection('mysqlNegocio')
            ->table('EXISTENCIACELDA')
            ->select('ExistenciaCelda', 'CantidadDisponible', 'CantidadReservada')
            ->where('Lote', $tnLote)
            ->lockForUpdate()
            ->get();

        $tnTotalActualLote = 0;
        foreach ($laExistenciasLote as $loExistenciaLote) {
            $tnTotalActualLote += (int)$loExistenciaLote->CantidadDisponible + (int)$loExistenciaLote->CantidadReservada;
        }

        $tnTotalNuevoLote = $tnTotalActualLote + $tnOcupadoFilaNuevo;
        if ($tnLoteActual !== null && $tnLoteActual === $tnLote) {
            $tnTotalNuevoLote = $tnTotalActualLote - $tnOcupadoFilaActual + $tnOcupadoFilaNuevo;
        }

        return $tnTotalNuevoLote <= $tnCantidadInicialLote;
    }

    private function validarLoteCorrespondeProductoEmpresa(int $tnLote, int $tnProductoEmpresa): bool
    {
        $loPar = DB::connection('mysqlNegocio')
            ->table('LOTE as l')
            ->join('PRODUCTOEMPRESA as pe', 'pe.Producto', '=', 'l.Producto')
            ->where('l.Lote', $tnLote)
            ->where('pe.ProductoEmpresa', $tnProductoEmpresa)
            ->select('l.Lote')
            ->first();

        return (bool)$loPar;
    }

    private function registrarMovimientoInventarioReposicion(
        int $tnReposicion,
        int $tnMaquina,
        int $tnCelda,
        int $tnProductoEmpresa,
        int $tnLote,
        int $tnCantidad,
        $tdAhora,
        ?string $tcObservacion
    ): void {
        if (!Schema::connection('mysqlNegocio')->hasTable('MOVIMIENTOINVENTARIO')) {
            return;
        }

        if (!Schema::connection('mysqlNegocio')->hasTable('TIPOMOVIMIENTOINVENTARIO')) {
            return;
        }

        $loTipo = DB::connection('mysqlNegocio')
            ->table('TIPOMOVIMIENTOINVENTARIO')
            ->whereRaw('UPPER(NombreTipoMovimientoInventario) = ?', [self::NOMBRE_TIPO_MOV_REPOSICION])
            ->where('Estado', self::ESTADO_ACTIVO)
            ->first();

        if (!$loTipo || (int)$loTipo->Factor !== 1) {
            return;
        }

        DB::connection('mysqlNegocio')
            ->table('MOVIMIENTOINVENTARIO')
            ->insert([
                'Maquina' => $tnMaquina,
                'Celda' => $tnCelda,
                'ProductoEmpresa' => $tnProductoEmpresa,
                'Lote' => $tnLote,
                'TipoMovimientoInventario' => (int)$loTipo->TipoMovimientoInventario,
                'Cantidad' => $tnCantidad,
                'CostoUnitario' => null,
                'Reposicion' => $tnReposicion,
                'Merma' => null,
                'Transaccion' => null,
                'FechaHora' => $tdAhora,
                'Observacion' => $tcObservacion,
                'Estado' => self::ESTADO_ACTIVO,
                'Usr' => 0,
                'UsrFecha' => $tdAhora->toDateString(),
                'UsrHora' => $tdAhora->format('H:i:s'),
            ]);
    }
}

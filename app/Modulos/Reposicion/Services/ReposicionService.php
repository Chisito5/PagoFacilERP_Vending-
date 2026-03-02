<?php

namespace App\Modulos\Reposicion\Services;

use App\Events\EventoReposicionCreada;
use App\Events\EventoStockActualizado;
use App\Soporte\RespuestaApi;
use App\Support\EstadoCatalogo;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use stdClass;
use Throwable;

class ReposicionService
{
    private const CODIGO_GENERAL_ACTIVO = 1;
    private const CODIGO_REPOSICION_REGISTRADA = 1;
    private const NOMBRE_TIPO_MOV_REPOSICION = 'REPOSICION';

    public function __construct(private EstadoCatalogo $toEstadoCatalogo)
    {
    }

    public function PrevalidarPorSeleccion(
        int $tnMaquina,
        string $tcCodigoSeleccion,
        int $tnCantidad,
        int $tnUsuarioOperador,
        ?int $tnProductoEmpresa = null,
        ?int $tnLote = null
    ): JsonResponse {
        return DB::connection('mysqlNegocio')->transaction(function () use (
            $tnMaquina,
            $tcCodigoSeleccion,
            $tnCantidad,
            $tnUsuarioOperador,
            $tnProductoEmpresa,
            $tnLote
        ) {
            $mxEvaluacion = $this->evaluarReposicion(
                $tnMaquina,
                $tcCodigoSeleccion,
                $tnCantidad,
                $tnUsuarioOperador,
                $tnProductoEmpresa,
                $tnLote
            );

            if ($mxEvaluacion instanceof JsonResponse) {
                return $mxEvaluacion;
            }

            /** @var array<string,mixed> $laContexto */
            $laContexto = $mxEvaluacion;
            /** @var stdClass $loExistencia */
            $loExistencia = $laContexto['existencia'];
            /** @var stdClass $loCelda */
            $loCelda = $laContexto['celda'];
            /** @var stdClass $loLote */
            $loLote = $laContexto['lote'];

            $tnDisponibleActual = (int)$loExistencia->CantidadDisponible;
            $tnReservadaActual = (int)$loExistencia->CantidadReservada;
            $tnDisponibleNuevo = $tnDisponibleActual + $tnCantidad;

            return RespuestaApi::exito('Prevalidacion de reposicion correcta', [
                'Maquina' => $tnMaquina,
                'Celda' => (int)$loCelda->Celda,
                'CodigoSeleccion' => $tcCodigoSeleccion,
                'ProductoEmpresa' => (int)$laContexto['producto_empresa'],
                'Lote' => (int)$laContexto['lote_final'],
                'CantidadAgregada' => $tnCantidad,
                'CantidadDisponibleActual' => $tnDisponibleActual,
                'CantidadReservadaActual' => $tnReservadaActual,
                'CantidadDisponibleProyectada' => $tnDisponibleNuevo,
                'CapacidadMaximaCelda' => (int)$loCelda->CapacidadMaxima,
                'CapacidadLote' => (int)$loLote->CantidadInicial,
            ]);
        });
    }

    public function RecargarPorSeleccion(
        int $tnMaquina,
        string $tcCodigoSeleccion,
        int $tnCantidad,
        int $tnUsuarioOperador,
        ?int $tnProductoEmpresa = null,
        ?int $tnLote = null,
        ?string $tcObservacion = null
    ): JsonResponse {
        return DB::connection('mysqlNegocio')->transaction(function () use (
            $tnMaquina,
            $tcCodigoSeleccion,
            $tnCantidad,
            $tnUsuarioOperador,
            $tnProductoEmpresa,
            $tnLote,
            $tcObservacion
        ) {
            $mxEvaluacion = $this->evaluarReposicion(
                $tnMaquina,
                $tcCodigoSeleccion,
                $tnCantidad,
                $tnUsuarioOperador,
                $tnProductoEmpresa,
                $tnLote
            );

            if ($mxEvaluacion instanceof JsonResponse) {
                return $mxEvaluacion;
            }

            /** @var array<string,mixed> $laContexto */
            $laContexto = $mxEvaluacion;
            /** @var stdClass $loExistencia */
            $loExistencia = $laContexto['existencia'];
            /** @var stdClass $loCelda */
            $loCelda = $laContexto['celda'];

            $tnEstadoReposicionRegistrada = (int)$laContexto['estado_reposicion_registrada'];
            $tnEstadoGeneralActivo = (int)$laContexto['estado_general_activo'];
            $tnCelda = (int)$loCelda->Celda;
            $tnProductoEmpresaFinal = (int)$laContexto['producto_empresa'];
            $tnLoteFinal = (int)$laContexto['lote_final'];
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
            $tnReposicion = (int)DB::connection('mysqlNegocio')
                ->table('REPOSICION')
                ->insertGetId([
                    'Maquina' => $tnMaquina,
                    'UsuarioOperador' => $tnUsuarioOperador,
                    'FechaHoraInicio' => $tdAhora,
                    'FechaHoraFin' => $tdAhora,
                    'Observacion' => $tcObservacion,
                    'Estado' => $tnEstadoReposicionRegistrada,
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
                        'Estado' => $tnEstadoReposicionRegistrada,
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
                $tcObservacion,
                $tnEstadoGeneralActivo
            );

            $tnEmpresa = $this->obtenerEmpresaPorMaquina($tnMaquina);
            $this->emitirEventoSeguro(new EventoReposicionCreada($tnMaquina, $tnEmpresa, [
                'Reposicion' => $tnReposicion,
                'Celda' => $tnCelda,
                'CodigoSeleccion' => $tcCodigoSeleccion,
                'CantidadAgregada' => $tnCantidad,
            ]), 'reposicion.creada');
            $this->emitirEventoSeguro(new EventoStockActualizado($tnMaquina, $tnEmpresa, [
                'Origen' => 'reposicion',
                'Reposicion' => $tnReposicion,
                'Celda' => $tnCelda,
                'CodigoSeleccion' => $tcCodigoSeleccion,
                'CantidadDisponible' => $tnNuevaCantidad,
                'CantidadReservada' => (int)$loExistencia->CantidadReservada,
            ]), 'stock.actualizado.reposicion');

            return RespuestaApi::exito('Stock recargado correctamente', [
                'Reposicion' => $tnReposicion,
                'Maquina' => $tnMaquina,
                'Celda' => $tnCelda,
                'CodigoSeleccion' => $tcCodigoSeleccion,
                'ExistenciaCelda' => (int)$loExistencia->ExistenciaCelda,
                'ProductoEmpresa' => $tnProductoEmpresaFinal,
                'Lote' => $tnLoteFinal,
                'CantidadAgregada' => $tnCantidad,
                'CantidadDisponible' => $tnNuevaCantidad,
                'CapacidadMaximaCelda' => (int)$loCelda->CapacidadMaxima,
                'CapacidadLote' => (int)$laContexto['capacidad_lote'],
            ]);
        });
    }

    public function Listar(int $tnPagina = 1, int $tnTamanoPagina = 20): LengthAwarePaginator
    {
        $tnTamanoPagina = max(1, min($tnTamanoPagina, 200));

        return DB::connection('mysqlNegocio')
            ->table('REPOSICION')
            ->orderByDesc('Reposicion')
            ->paginate($tnTamanoPagina, ['*'], 'Pagina', max(1, $tnPagina));
    }

    public function ListarPorMaquina(int $tnMaquina, int $tnPagina = 1, int $tnTamanoPagina = 20): LengthAwarePaginator
    {
        $tnTamanoPagina = max(1, min($tnTamanoPagina, 200));

        return DB::connection('mysqlNegocio')
            ->table('REPOSICION')
            ->where('Maquina', $tnMaquina)
            ->orderByDesc('Reposicion')
            ->paginate($tnTamanoPagina, ['*'], 'Pagina', max(1, $tnPagina));
    }

    /**
     * @return array<string,mixed>|null
     */
    public function Obtener(int $tnReposicion): ?array
    {
        $loCabecera = DB::connection('mysqlNegocio')
            ->table('REPOSICION')
            ->where('Reposicion', $tnReposicion)
            ->first();

        if (!$loCabecera) {
            return null;
        }

        $laDetalle = DB::connection('mysqlNegocio')
            ->table('REPOSICIONDETALLE')
            ->where('Reposicion', $tnReposicion)
            ->orderBy('ReposicionDetalle')
            ->get()
            ->all();

        $laMovimientos = DB::connection('mysqlNegocio')
            ->table('MOVIMIENTOINVENTARIO')
            ->where('Reposicion', $tnReposicion)
            ->orderBy('MovimientoInventario')
            ->get()
            ->all();

        return [
            'Cabecera' => $loCabecera,
            'Detalle' => $laDetalle,
            'Auditoria' => [
                'MovimientosInventario' => $laMovimientos
            ],
        ];
    }

    /**
     * @return array<string,mixed>|JsonResponse
     */
    private function evaluarReposicion(
        int $tnMaquina,
        string $tcCodigoSeleccion,
        int $tnCantidad,
        int $tnUsuarioOperador,
        ?int $tnProductoEmpresa,
        ?int $tnLote
    ) {
        try {
            $tnEstadoGeneralActivo = $this->toEstadoCatalogo->obtenerId('GENERAL', self::CODIGO_GENERAL_ACTIVO);
            $tnEstadoReposicionRegistrada = $this->toEstadoCatalogo->obtenerId('REPOSICION', self::CODIGO_REPOSICION_REGISTRADA);
        } catch (RuntimeException $toEx) {
            return RespuestaApi::error($toEx->getMessage(), 500);
        }

        $loUsuarioOperador = DB::connection('mysqlNegocio')
            ->table('USUARIO')
            ->where('Usuario', $tnUsuarioOperador)
            ->where('Estado', $tnEstadoGeneralActivo)
            ->first();

        if (!$loUsuarioOperador) {
            return RespuestaApi::error('UsuarioOperador no existe o esta inactivo', 400, [
                ['Codigo' => 'REPO_001', 'Campo' => 'UsuarioOperador', 'Detalle' => 'Usuario operador invalido']
            ]);
        }

        $loCelda = $this->obtenerCeldaPorSeleccion($tnMaquina, $tcCodigoSeleccion, $tnEstadoGeneralActivo);
        if (!$loCelda) {
            return RespuestaApi::error('La celda no existe para esta maquina o esta inactiva', 400, [
                ['Codigo' => 'REPO_002', 'Campo' => 'CodigoSeleccion', 'Detalle' => 'Celda no encontrada']
            ]);
        }

        $tnCelda = (int)$loCelda->Celda;
        $loExistencia = $this->obtenerExistenciaConLock($tnCelda, $tnEstadoGeneralActivo);
        if (!$loExistencia) {
            return RespuestaApi::error('No existe existencia configurada para esta celda', 400, [
                ['Codigo' => 'REPO_003', 'Campo' => 'Celda', 'Detalle' => 'No existe stock configurado']
            ]);
        }

        $tnProductoEmpresaFinal = $tnProductoEmpresa !== null ? $tnProductoEmpresa : (int)$loExistencia->ProductoEmpresa;
        $tnLoteFinal = $tnLote !== null ? $tnLote : (isset($loExistencia->Lote) ? (int)$loExistencia->Lote : null);

        if ($tnLoteFinal === null || $tnLoteFinal <= 0) {
            return RespuestaApi::error('Lote requerido para control de integridad', 400, [
                ['Codigo' => 'REPO_004', 'Campo' => 'Lote', 'Detalle' => 'Debe indicar Lote valido']
            ]);
        }

        $loLote = $this->obtenerLoteConLock($tnLoteFinal, $tnEstadoGeneralActivo);
        if (!$loLote) {
            return RespuestaApi::error('Lote no encontrado o inactivo', 400, [
                ['Codigo' => 'REPO_005', 'Campo' => 'Lote', 'Detalle' => 'Lote inexistente']
            ]);
        }

        if (!$this->validarLoteCorrespondeProductoEmpresa($tnLoteFinal, $tnProductoEmpresaFinal)) {
            return RespuestaApi::error('El lote no corresponde al ProductoEmpresa indicado', 409, [
                ['Codigo' => 'REPO_006', 'Campo' => 'Lote', 'Detalle' => 'Producto/lote no coincide']
            ]);
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

            return RespuestaApi::error('Capacidad excedida', 409, [
                ['Codigo' => 'REPO_007', 'Campo' => 'Cantidad', 'Detalle' => 'Supera la capacidad maxima de la celda']
            ]);
        }

        $tnOcupadoFilaActual = (int)$loExistencia->CantidadDisponible + (int)$loExistencia->CantidadReservada;
        $tnOcupadoFilaNuevo = $tnOcupadoFilaActual + $tnCantidad;
        $tnCantidadInicialLote = (int)$loLote->CantidadInicial;

        if ($tnCantidadInicialLote <= 0) {
            return RespuestaApi::error('Lote sin capacidad definida', 400, [
                ['Codigo' => 'REPO_008', 'Campo' => 'Lote', 'Detalle' => 'CantidadInicial del lote no configurada']
            ]);
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

            return RespuestaApi::error('Lote insuficiente', 409, [
                ['Codigo' => 'REPO_009', 'Campo' => 'Lote', 'Detalle' => 'La reposicion supera la capacidad del lote']
            ]);
        }

        return [
            'estado_general_activo' => $tnEstadoGeneralActivo,
            'estado_reposicion_registrada' => $tnEstadoReposicionRegistrada,
            'celda' => $loCelda,
            'existencia' => $loExistencia,
            'lote' => $loLote,
            'producto_empresa' => $tnProductoEmpresaFinal,
            'lote_final' => $tnLoteFinal,
            'capacidad_lote' => $tnCantidadInicialLote,
        ];
    }

    private function obtenerCeldaPorSeleccion(int $tnMaquina, string $tcCodigoSeleccion, int $tnEstadoGeneralActivo): ?stdClass
    {
        return DB::connection('mysqlNegocio')
            ->table('CELDA')
            ->where('Maquina', $tnMaquina)
            ->where('CodigoSeleccion', $tcCodigoSeleccion)
            ->where('Estado', $tnEstadoGeneralActivo)
            ->lockForUpdate()
            ->first();
    }

    private function obtenerExistenciaConLock(int $tnCelda, int $tnEstadoGeneralActivo): ?stdClass
    {
        return DB::connection('mysqlNegocio')
            ->table('EXISTENCIACELDA')
            ->where('Celda', $tnCelda)
            ->where('Estado', $tnEstadoGeneralActivo)
            ->lockForUpdate()
            ->first();
    }

    private function obtenerLoteConLock(int $tnLote, int $tnEstadoGeneralActivo): ?stdClass
    {
        return DB::connection('mysqlNegocio')
            ->table('LOTE')
            ->where('Lote', $tnLote)
            ->where('Estado', $tnEstadoGeneralActivo)
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
            ->select('CantidadDisponible', 'CantidadReservada')
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
        ?string $tcObservacion,
        int $tnEstadoGeneralActivo
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
            ->where('Estado', $tnEstadoGeneralActivo)
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
                'Estado' => $tnEstadoGeneralActivo,
                'Usr' => 0,
                'UsrFecha' => $tdAhora->toDateString(),
                'UsrHora' => $tdAhora->format('H:i:s'),
            ]);
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
                'modulo' => 'reposicion',
                'error' => $toEx->getMessage(),
            ]);
        }
    }
}

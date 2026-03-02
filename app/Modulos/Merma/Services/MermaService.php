<?php

namespace App\Modulos\Merma\Services;

use App\Soporte\AuditoriaService;
use App\Soporte\ControlVersionService;
use App\Soporte\EstadoNegocioService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class MermaService
{
    private string $pcConexion = 'mysqlNegocio';

    public function __construct(
        private EstadoNegocioService $toEstadoNegocio,
        private ControlVersionService $toControlVersion,
        private AuditoriaService $toAuditoria
    ) {
    }

    public function listar(?int $tnMaquina, ?int $tnEstado, int $tnPagina, int $tnTamanoPagina): LengthAwarePaginator
    {
        $tnTamanoPagina = max(1, min($tnTamanoPagina, 200));

        $to = DB::connection($this->pcConexion)->table('MERMA as m')
            ->leftJoin('USUARIO as u', 'u.Usuario', '=', 'm.UsuarioOperador')
            ->select(['m.*', 'u.NombreUsuario as NombreUsuarioOperador'])
            ->orderByDesc('m.Merma');

        if ($tnMaquina !== null && $tnMaquina > 0) {
            $to->where('m.Maquina', $tnMaquina);
        }
        if ($tnEstado !== null && $tnEstado > 0) {
            $to->where('m.Estado', $tnEstado);
        }

        return $to->paginate($tnTamanoPagina, ['*'], 'Pagina', max(1, $tnPagina));
    }

    public function obtener(int $tnMerma): ?array
    {
        $lo = DB::connection($this->pcConexion)->table('MERMA')->where('Merma', $tnMerma)->first();
        if (!$lo) {
            return null;
        }

        $la = $this->normalizar($lo);
        $la['Detalles'] = $this->listarDetalles($tnMerma);
        $la['Evidencias'] = $this->listarEvidencias($tnMerma);

        return $la;
    }

    /** @param array<string,mixed> $taDatos */
    public function crear(array $taDatos, int $tnUsuarioSesion): array
    {
        $tdAhora = now();
        $tnEstadoRegistrada = $this->toEstadoNegocio->estado('MERMA', 1);

        $tnMerma = DB::connection($this->pcConexion)->table('MERMA')->insertGetId([
            'Maquina' => (int)$taDatos['Maquina'],
            'UsuarioOperador' => (int)$taDatos['UsuarioOperador'],
            'TipoMerma' => (int)$taDatos['TipoMerma'],
            'FechaHora' => $taDatos['FechaHora'] ?? $tdAhora,
            'Observacion' => $taDatos['Observacion'] ?? null,
            'Estado' => (int)($taDatos['Estado'] ?? $tnEstadoRegistrada),
            'Usr' => $tnUsuarioSesion,
            'UsrFecha' => $tdAhora->toDateString(),
            'UsrHora' => $tdAhora->format('H:i:s'),
        ]);

        $la = $this->obtener($tnMerma) ?? [];
        $this->toAuditoria->registrar('MERMA', $tnMerma, 'CREAR', null, $la, $tnUsuarioSesion, $taDatos['Motivo'] ?? null);

        return $la;
    }

    /** @param array<string,mixed> $taDatos */
    public function actualizar(int $tnMerma, array $taDatos, string $tcVersion, int $tnUsuarioSesion, bool $lbParcial): array
    {
        return DB::connection($this->pcConexion)->transaction(function () use ($tnMerma, $taDatos, $tcVersion, $tnUsuarioSesion, $lbParcial): array {
            $lo = DB::connection($this->pcConexion)->table('MERMA')->where('Merma', $tnMerma)->lockForUpdate()->first();
            if (!$lo) {
                return ['Estado' => 'NO_ENCONTRADO'];
            }
            if (!$this->toControlVersion->coincide($tcVersion, $lo)) {
                return ['Estado' => 'CONFLICTO_VERSION', 'Actual' => $this->obtener($tnMerma)];
            }

            $laCampos = ['Maquina', 'UsuarioOperador', 'TipoMerma', 'FechaHora', 'Observacion', 'Estado'];
            $laUpdate = [];
            foreach ($laCampos as $tcCampo) {
                if (array_key_exists($tcCampo, $taDatos)) {
                    $laUpdate[$tcCampo] = $taDatos[$tcCampo];
                } elseif (!$lbParcial) {
                    $laUpdate[$tcCampo] = $lo->{$tcCampo};
                }
            }

            $tdAhora = now();
            $laUpdate['Usr'] = $tnUsuarioSesion;
            $laUpdate['UsrFecha'] = $tdAhora->toDateString();
            $laUpdate['UsrHora'] = $tdAhora->format('H:i:s');

            DB::connection($this->pcConexion)->table('MERMA')->where('Merma', $tnMerma)->update($laUpdate);

            $laDespues = $this->obtener($tnMerma) ?? [];
            $this->toAuditoria->registrar('MERMA', $tnMerma, 'ACTUALIZAR', (array)$lo, $laDespues, $tnUsuarioSesion, $taDatos['Motivo'] ?? null);

            return ['Estado' => 'OK', 'Datos' => $laDespues];
        });
    }

    public function eliminarLogico(int $tnMerma, string $tcVersion, int $tnUsuarioSesion, ?string $tcMotivo): array
    {
        return DB::connection($this->pcConexion)->transaction(function () use ($tnMerma, $tcVersion, $tnUsuarioSesion, $tcMotivo): array {
            $lo = DB::connection($this->pcConexion)->table('MERMA')->where('Merma', $tnMerma)->lockForUpdate()->first();
            if (!$lo) {
                return ['Estado' => 'NO_ENCONTRADO'];
            }
            if (!$this->toControlVersion->coincide($tcVersion, $lo)) {
                return ['Estado' => 'CONFLICTO_VERSION', 'Actual' => $this->obtener($tnMerma)];
            }

            $tdAhora = now();
            DB::connection($this->pcConexion)->table('MERMA')->where('Merma', $tnMerma)->update([
                'Estado' => $this->toEstadoNegocio->inactivoGeneral(),
                'Usr' => $tnUsuarioSesion,
                'UsrFecha' => $tdAhora->toDateString(),
                'UsrHora' => $tdAhora->format('H:i:s'),
            ]);

            DB::connection($this->pcConexion)->table('MERMADETALLE')->where('Merma', $tnMerma)->update([
                'Estado' => $this->toEstadoNegocio->inactivoGeneral(),
                'Usr' => $tnUsuarioSesion,
                'UsrFecha' => $tdAhora->toDateString(),
                'UsrHora' => $tdAhora->format('H:i:s'),
            ]);

            $laDespues = $this->obtener($tnMerma) ?? [];
            $this->toAuditoria->registrar('MERMA', $tnMerma, 'ELIMINAR_LOGICO', (array)$lo, $laDespues, $tnUsuarioSesion, $tcMotivo);

            return ['Estado' => 'OK', 'Datos' => $laDespues];
        });
    }

    public function agregarDetalle(int $tnMerma, array $taDatos, int $tnUsuarioSesion, ?string $tcMotivo): array
    {
        $tdAhora = now();
        $tnEstadoActivo = $this->toEstadoNegocio->activoGeneral();

        $tnId = DB::connection($this->pcConexion)->table('MERMADETALLE')->insertGetId([
            'Merma' => $tnMerma,
            'Celda' => (int)$taDatos['Celda'],
            'ProductoEmpresa' => (int)$taDatos['ProductoEmpresa'],
            'Lote' => $taDatos['Lote'] ?? null,
            'CantidadRetirada' => (int)$taDatos['CantidadRetirada'],
            'Estado' => $tnEstadoActivo,
            'Usr' => $tnUsuarioSesion,
            'UsrFecha' => $tdAhora->toDateString(),
            'UsrHora' => $tdAhora->format('H:i:s'),
        ]);

        $la = (array)DB::connection($this->pcConexion)->table('MERMADETALLE')->where('MermaDetalle', $tnId)->first();
        $la['Version'] = $this->toControlVersion->versionDesdeFila($la);
        $this->toAuditoria->registrar('MERMADETALLE', $tnId, 'CREAR', null, $la, $tnUsuarioSesion, $tcMotivo);

        return $la;
    }

    public function quitarDetalle(int $tnMerma, int $tnMermaDetalle, int $tnUsuarioSesion, ?string $tcMotivo): array
    {
        $lo = DB::connection($this->pcConexion)->table('MERMADETALLE')->where('MermaDetalle', $tnMermaDetalle)->where('Merma', $tnMerma)->first();
        if (!$lo) {
            return ['Estado' => 'NO_ENCONTRADO'];
        }

        $tdAhora = now();
        DB::connection($this->pcConexion)->table('MERMADETALLE')->where('MermaDetalle', $tnMermaDetalle)->update([
            'Estado' => $this->toEstadoNegocio->inactivoGeneral(),
            'Usr' => $tnUsuarioSesion,
            'UsrFecha' => $tdAhora->toDateString(),
            'UsrHora' => $tdAhora->format('H:i:s'),
        ]);

        $la = (array)DB::connection($this->pcConexion)->table('MERMADETALLE')->where('MermaDetalle', $tnMermaDetalle)->first();
        $la['Version'] = $this->toControlVersion->versionDesdeFila($la);
        $this->toAuditoria->registrar('MERMADETALLE', $tnMermaDetalle, 'ELIMINAR_LOGICO', (array)$lo, $la, $tnUsuarioSesion, $tcMotivo);

        return ['Estado' => 'OK', 'Datos' => $la];
    }

    public function subirEvidencia(int $tnMerma, UploadedFile $toArchivo, int $tnUsuarioSesion, ?string $tcMotivo): array
    {
        $tcNombre = 'merma_' . $tnMerma . '_' . now()->format('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $toArchivo->getClientOriginalExtension();
        $tcRuta = $toArchivo->storeAs('merma/evidencia', $tcNombre, 'public');
        $tdAhora = now();

        $tnId = DB::connection($this->pcConexion)->table('MERMAEVIDENCIA')->insertGetId([
            'Merma' => $tnMerma,
            'NombreArchivo' => $toArchivo->getClientOriginalName(),
            'RutaArchivo' => $tcRuta,
            'MimeArchivo' => $toArchivo->getClientMimeType(),
            'TamanoBytes' => $toArchivo->getSize(),
            'Estado' => $this->toEstadoNegocio->activoGeneral(),
            'Usr' => $tnUsuarioSesion,
            'UsrFecha' => $tdAhora->toDateString(),
            'UsrHora' => $tdAhora->format('H:i:s'),
        ]);

        $la = (array)DB::connection($this->pcConexion)->table('MERMAEVIDENCIA')->where('MermaEvidencia', $tnId)->first();
        $la['UrlArchivo'] = Storage::disk('public')->url((string)$la['RutaArchivo']);
        $this->toAuditoria->registrar('MERMAEVIDENCIA', $tnId, 'CREAR', null, $la, $tnUsuarioSesion, $tcMotivo);

        return $la;
    }

    /** @return array<int,array<string,mixed>> */
    public function listarEvidencias(int $tnMerma): array
    {
        return DB::connection($this->pcConexion)
            ->table('MERMAEVIDENCIA')
            ->where('Merma', $tnMerma)
            ->orderByDesc('MermaEvidencia')
            ->get()
            ->map(function ($toFila) {
                $la = (array)$toFila;
                $la['Version'] = $this->toControlVersion->versionDesdeFila($toFila);
                $la['UrlArchivo'] = Storage::disk('public')->url((string)$la['RutaArchivo']);
                return $la;
            })
            ->all();
    }

    public function aprobar(int $tnMerma, int $tnUsuarioSesion, ?string $tcMotivo): array
    {
        $tnEstadoRegistrada = $this->toEstadoNegocio->estado('MERMA', 1);
        $tnEstadoAprobada = $this->toEstadoNegocio->estado('MERMA', 2);

        return DB::connection($this->pcConexion)->transaction(function () use ($tnMerma, $tnEstadoRegistrada, $tnEstadoAprobada, $tnUsuarioSesion, $tcMotivo): array {
            $loMerma = DB::connection($this->pcConexion)->table('MERMA')->where('Merma', $tnMerma)->lockForUpdate()->first();
            if (!$loMerma) {
                return ['Estado' => 'NO_ENCONTRADO'];
            }

            if ((int)$loMerma->Estado === $tnEstadoAprobada) {
                return ['Estado' => 'IDEMPOTENTE', 'Datos' => $this->obtener($tnMerma)];
            }

            if ((int)$loMerma->Estado !== $tnEstadoRegistrada) {
                return ['Estado' => 'TRANSICION_INVALIDA', 'Datos' => $this->obtener($tnMerma)];
            }

            $laDetalles = DB::connection($this->pcConexion)
                ->table('MERMADETALLE')
                ->where('Merma', $tnMerma)
                ->where('Estado', $this->toEstadoNegocio->activoGeneral())
                ->orderBy('MermaDetalle')
                ->get();

            if ($laDetalles->isEmpty()) {
                return ['Estado' => 'SIN_DETALLE'];
            }

            $tnTipoMovimientoMerma = $this->obtenerTipoMovimientoMerma($tnUsuarioSesion);
            $tdAhora = now();

            foreach ($laDetalles as $toDet) {
                $loExistencia = DB::connection($this->pcConexion)
                    ->table('EXISTENCIACELDA')
                    ->where('Celda', (int)$toDet->Celda)
                    ->lockForUpdate()
                    ->first();

                if (!$loExistencia) {
                    return ['Estado' => 'EXISTENCIA_NO_ENCONTRADA', 'Detalle' => (int)$toDet->MermaDetalle];
                }

                $tnRetirar = (int)$toDet->CantidadRetirada;
                $tnDisponible = (int)$loExistencia->CantidadDisponible;

                if ($tnDisponible < $tnRetirar) {
                    return ['Estado' => 'STOCK_INSUFICIENTE', 'Detalle' => (int)$toDet->MermaDetalle];
                }

                $tnNuevoDisponible = $tnDisponible - $tnRetirar;

                DB::connection($this->pcConexion)
                    ->table('EXISTENCIACELDA')
                    ->where('ExistenciaCelda', (int)$loExistencia->ExistenciaCelda)
                    ->update([
                        'CantidadDisponible' => $tnNuevoDisponible,
                        'Usr' => $tnUsuarioSesion,
                        'UsrFecha' => $tdAhora->toDateString(),
                        'UsrHora' => $tdAhora->format('H:i:s'),
                    ]);

                DB::connection($this->pcConexion)
                    ->table('MOVIMIENTOINVENTARIO')
                    ->insert([
                        'Maquina' => (int)$loMerma->Maquina,
                        'Celda' => (int)$toDet->Celda,
                        'ProductoEmpresa' => (int)$toDet->ProductoEmpresa,
                        'Lote' => $toDet->Lote,
                        'TipoMovimientoInventario' => $tnTipoMovimientoMerma,
                        'Cantidad' => $tnRetirar,
                        'CostoUnitario' => null,
                        'Reposicion' => null,
                        'Merma' => $tnMerma,
                        'Transaccion' => null,
                        'FechaHora' => $tdAhora,
                        'Observacion' => 'Aprobacion de merma',
                        'Estado' => $this->toEstadoNegocio->activoGeneral(),
                        'Usr' => $tnUsuarioSesion,
                        'UsrFecha' => $tdAhora->toDateString(),
                        'UsrHora' => $tdAhora->format('H:i:s'),
                    ]);
            }

            DB::connection($this->pcConexion)->table('MERMA')->where('Merma', $tnMerma)->update([
                'Estado' => $tnEstadoAprobada,
                'Usr' => $tnUsuarioSesion,
                'UsrFecha' => $tdAhora->toDateString(),
                'UsrHora' => $tdAhora->format('H:i:s'),
            ]);

            $laDespues = $this->obtener($tnMerma) ?? [];
            $this->toAuditoria->registrar('MERMA', $tnMerma, 'APROBAR', (array)$loMerma, $laDespues, $tnUsuarioSesion, $tcMotivo);

            return ['Estado' => 'OK', 'Datos' => $laDespues];
        });
    }

    public function rechazar(int $tnMerma, int $tnUsuarioSesion, ?string $tcMotivo): array
    {
        $tnEstadoRegistrada = $this->toEstadoNegocio->estado('MERMA', 1);
        $tnEstadoRechazada = $this->toEstadoNegocio->estado('MERMA', 3);

        return DB::connection($this->pcConexion)->transaction(function () use ($tnMerma, $tnEstadoRegistrada, $tnEstadoRechazada, $tnUsuarioSesion, $tcMotivo): array {
            $loMerma = DB::connection($this->pcConexion)->table('MERMA')->where('Merma', $tnMerma)->lockForUpdate()->first();
            if (!$loMerma) {
                return ['Estado' => 'NO_ENCONTRADO'];
            }

            if ((int)$loMerma->Estado === $tnEstadoRechazada) {
                return ['Estado' => 'IDEMPOTENTE', 'Datos' => $this->obtener($tnMerma)];
            }

            if ((int)$loMerma->Estado !== $tnEstadoRegistrada) {
                return ['Estado' => 'TRANSICION_INVALIDA', 'Datos' => $this->obtener($tnMerma)];
            }

            $tdAhora = now();
            DB::connection($this->pcConexion)->table('MERMA')->where('Merma', $tnMerma)->update([
                'Estado' => $tnEstadoRechazada,
                'Usr' => $tnUsuarioSesion,
                'UsrFecha' => $tdAhora->toDateString(),
                'UsrHora' => $tdAhora->format('H:i:s'),
            ]);

            $laDespues = $this->obtener($tnMerma) ?? [];
            $this->toAuditoria->registrar('MERMA', $tnMerma, 'RECHAZAR', (array)$loMerma, $laDespues, $tnUsuarioSesion, $tcMotivo);

            return ['Estado' => 'OK', 'Datos' => $laDespues];
        });
    }

    /** @return array<int,array<string,mixed>> */
    public function listarDetalles(int $tnMerma): array
    {
        return DB::connection($this->pcConexion)
            ->table('MERMADETALLE')
            ->where('Merma', $tnMerma)
            ->orderByDesc('MermaDetalle')
            ->get()
            ->map(fn ($toFila) => $this->normalizar($toFila))
            ->all();
    }

    private function normalizar(object|array $tmFila): array
    {
        $la = is_array($tmFila) ? $tmFila : (array)$tmFila;
        $la['Version'] = $this->toControlVersion->versionDesdeFila($tmFila);
        return $la;
    }

    private function obtenerTipoMovimientoMerma(int $tnUsuarioSesion): int
    {
        $tnEstadoActivo = $this->toEstadoNegocio->activoGeneral();

        $lo = DB::connection($this->pcConexion)
            ->table('TIPOMOVIMIENTOINVENTARIO')
            ->where('NombreTipoMovimientoInventario', 'MERMA')
            ->first();

        if ($lo) {
            return (int)$lo->TipoMovimientoInventario;
        }

        $tdAhora = now();
        return DB::connection($this->pcConexion)->table('TIPOMOVIMIENTOINVENTARIO')->insertGetId([
            'NombreTipoMovimientoInventario' => 'MERMA',
            'Factor' => -1,
            'Descripcion' => 'Salida por merma',
            'Estado' => $tnEstadoActivo,
            'Usr' => $tnUsuarioSesion,
            'UsrFecha' => $tdAhora->toDateString(),
            'UsrHora' => $tdAhora->format('H:i:s'),
        ]);
    }
}

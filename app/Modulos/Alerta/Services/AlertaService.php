<?php

namespace App\Modulos\Alerta\Services;

use App\Soporte\AuditoriaService;
use App\Soporte\ControlVersionService;
use App\Soporte\EstadoNegocioService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class AlertaService
{
    private string $pcConexion = 'mysqlNegocio';

    public function __construct(
        private EstadoNegocioService $toEstadoNegocio,
        private ControlVersionService $toControlVersion,
        private AuditoriaService $toAuditoria
    ) {
    }

    public function listarReglas(?int $tnEmpresa, ?int $tnEstado, int $tnPagina, int $tnTamanoPagina): LengthAwarePaginator
    {
        $tnTamanoPagina = max(1, min($tnTamanoPagina, 200));
        $to = DB::connection($this->pcConexion)->table('REGLAALERTA')->orderByDesc('ReglaAlerta');
        if ($tnEmpresa !== null && $tnEmpresa > 0) {
            $to->where('Empresa', $tnEmpresa);
        }
        if ($tnEstado !== null && $tnEstado > 0) {
            $to->where('Estado', $tnEstado);
        }
        return $to->paginate($tnTamanoPagina, ['*'], 'Pagina', max(1, $tnPagina));
    }

    public function obtenerRegla(int $tnRegla): ?array
    {
        $lo = DB::connection($this->pcConexion)->table('REGLAALERTA')->where('ReglaAlerta', $tnRegla)->first();
        return $lo ? $this->normalizar($lo) : null;
    }

    /** @param array<string,mixed> $taDatos */
    public function crearRegla(array $taDatos, int $tnUsuario): array
    {
        $tdAhora = now();
        $tnId = DB::connection($this->pcConexion)->table('REGLAALERTA')->insertGetId([
            'Empresa' => $taDatos['Empresa'] ?? null,
            'Maquina' => $taDatos['Maquina'] ?? null,
            'Celda' => $taDatos['Celda'] ?? null,
            'TipoAlerta' => $taDatos['TipoAlerta'] ?? null,
            'TipoTelemetria' => $taDatos['TipoTelemetria'] ?? null,
            'TipoRegla' => (string)$taDatos['TipoRegla'],
            'UmbralMinimo' => $taDatos['UmbralMinimo'] ?? null,
            'UmbralMaximo' => $taDatos['UmbralMaximo'] ?? null,
            'Prioridad' => (int)($taDatos['Prioridad'] ?? 1),
            'MensajeRegla' => $taDatos['MensajeRegla'] ?? null,
            'Estado' => (int)($taDatos['Estado'] ?? $this->toEstadoNegocio->activoGeneral()),
            'Usr' => $tnUsuario,
            'UsrFecha' => $tdAhora->toDateString(),
            'UsrHora' => $tdAhora->format('H:i:s'),
        ]);

        $la = $this->obtenerRegla($tnId) ?? [];
        $this->toAuditoria->registrar('REGLAALERTA', $tnId, 'CREAR', null, $la, $tnUsuario, $taDatos['Motivo'] ?? null);

        return $la;
    }

    /** @param array<string,mixed> $taDatos */
    public function actualizarRegla(int $tnRegla, array $taDatos, string $tcVersion, int $tnUsuario, bool $lbParcial): array
    {
        return DB::connection($this->pcConexion)->transaction(function () use ($tnRegla, $taDatos, $tcVersion, $tnUsuario, $lbParcial): array {
            $lo = DB::connection($this->pcConexion)->table('REGLAALERTA')->where('ReglaAlerta', $tnRegla)->lockForUpdate()->first();
            if (!$lo) {
                return ['Estado' => 'NO_ENCONTRADO'];
            }
            if (!$this->toControlVersion->coincide($tcVersion, $lo)) {
                return ['Estado' => 'CONFLICTO_VERSION', 'Actual' => $this->obtenerRegla($tnRegla)];
            }

            $laCampos = ['Empresa', 'Maquina', 'Celda', 'TipoAlerta', 'TipoTelemetria', 'TipoRegla', 'UmbralMinimo', 'UmbralMaximo', 'Prioridad', 'MensajeRegla', 'Estado'];
            $laUpdate = [];
            foreach ($laCampos as $tcCampo) {
                if (array_key_exists($tcCampo, $taDatos)) {
                    $laUpdate[$tcCampo] = $taDatos[$tcCampo];
                } elseif (!$lbParcial) {
                    $laUpdate[$tcCampo] = $lo->{$tcCampo};
                }
            }

            $tdAhora = now();
            $laUpdate['Usr'] = $tnUsuario;
            $laUpdate['UsrFecha'] = $tdAhora->toDateString();
            $laUpdate['UsrHora'] = $tdAhora->format('H:i:s');

            DB::connection($this->pcConexion)->table('REGLAALERTA')->where('ReglaAlerta', $tnRegla)->update($laUpdate);

            $laDespues = $this->obtenerRegla($tnRegla) ?? [];
            $this->toAuditoria->registrar('REGLAALERTA', $tnRegla, 'ACTUALIZAR', (array)$lo, $laDespues, $tnUsuario, $taDatos['Motivo'] ?? null);

            return ['Estado' => 'OK', 'Datos' => $laDespues];
        });
    }

    public function eliminarRegla(int $tnRegla, string $tcVersion, int $tnUsuario, ?string $tcMotivo): array
    {
        return DB::connection($this->pcConexion)->transaction(function () use ($tnRegla, $tcVersion, $tnUsuario, $tcMotivo): array {
            $lo = DB::connection($this->pcConexion)->table('REGLAALERTA')->where('ReglaAlerta', $tnRegla)->lockForUpdate()->first();
            if (!$lo) {
                return ['Estado' => 'NO_ENCONTRADO'];
            }
            if (!$this->toControlVersion->coincide($tcVersion, $lo)) {
                return ['Estado' => 'CONFLICTO_VERSION', 'Actual' => $this->obtenerRegla($tnRegla)];
            }

            $tdAhora = now();
            DB::connection($this->pcConexion)->table('REGLAALERTA')->where('ReglaAlerta', $tnRegla)->update([
                'Estado' => $this->toEstadoNegocio->inactivoGeneral(),
                'Usr' => $tnUsuario,
                'UsrFecha' => $tdAhora->toDateString(),
                'UsrHora' => $tdAhora->format('H:i:s'),
            ]);

            $laDespues = $this->obtenerRegla($tnRegla) ?? [];
            $this->toAuditoria->registrar('REGLAALERTA', $tnRegla, 'ELIMINAR_LOGICO', (array)$lo, $laDespues, $tnUsuario, $tcMotivo);

            return ['Estado' => 'OK', 'Datos' => $laDespues];
        });
    }

    public function listarAlertas(?int $tnMaquina, ?int $tnEstado, int $tnPagina, int $tnTamanoPagina): LengthAwarePaginator
    {
        $tnTamanoPagina = max(1, min($tnTamanoPagina, 200));

        $to = DB::connection($this->pcConexion)->table('ALERTA as a')
            ->leftJoin('TIPOALERTA as t', 't.TipoAlerta', '=', 'a.TipoAlerta')
            ->leftJoin('USUARIO as u', 'u.Usuario', '=', 'a.UsuarioAsignado')
            ->select(['a.*', 't.NombreTipoAlerta', 'u.NombreUsuario as NombreUsuarioAsignado'])
            ->orderByDesc('a.Alerta');

        if ($tnMaquina !== null && $tnMaquina > 0) {
            $to->where('a.Maquina', $tnMaquina);
        }
        if ($tnEstado !== null && $tnEstado > 0) {
            $to->where('a.Estado', $tnEstado);
        }

        return $to->paginate($tnTamanoPagina, ['*'], 'Pagina', max(1, $tnPagina));
    }

    public function obtenerAlerta(int $tnAlerta): ?array
    {
        $lo = DB::connection($this->pcConexion)
            ->table('ALERTA')
            ->where('Alerta', $tnAlerta)
            ->first();

        return $lo ? $this->normalizar($lo) : null;
    }

    public function atenderAlerta(int $tnAlerta, int $tnUsuarioSesion, ?string $tcMotivo): array
    {
        return $this->cambiarEstadoAlerta($tnAlerta, $this->toEstadoNegocio->estado('ALERTA', 2), $tnUsuarioSesion, $tcMotivo, 'ATENDER');
    }

    public function escalarAlerta(int $tnAlerta, int $tnUsuarioSesion, ?string $tcMotivo): array
    {
        return $this->cambiarEstadoAlerta($tnAlerta, $this->toEstadoNegocio->estado('ALERTA', 3), $tnUsuarioSesion, $tcMotivo, 'ESCALAR');
    }

    public function cerrarAlerta(int $tnAlerta, int $tnUsuarioSesion, ?string $tcMotivo): array
    {
        return $this->cambiarEstadoAlerta($tnAlerta, $this->toEstadoNegocio->estado('ALERTA', 4), $tnUsuarioSesion, $tcMotivo, 'CERRAR');
    }

    public function generarAutomaticas(): array
    {
        $tnGeneradas = 0;
        $tnSaltadas = 0;
        $tnErrores = 0;
        $tnEstadoActiva = $this->toEstadoNegocio->activoGeneral();
        $tnEstadoAlertaAbierta = $this->toEstadoNegocio->estado('ALERTA', 1);
        $tdAhora = now();

        $laReglas = DB::connection($this->pcConexion)
            ->table('REGLAALERTA')
            ->where('Estado', $tnEstadoActiva)
            ->orderBy('ReglaAlerta')
            ->get();

        foreach ($laReglas as $toRegla) {
            try {
                $lbAplica = false;
                $tcMensaje = (string)($toRegla->MensajeRegla ?? 'Alerta automatica');

                if (strtoupper((string)$toRegla->TipoRegla) === 'STOCK_MINIMO') {
                    $toConsulta = DB::connection($this->pcConexion)
                        ->table('EXISTENCIACELDA as ec')
                        ->join('CELDA as c', 'c.Celda', '=', 'ec.Celda')
                        ->where('c.Estado', $tnEstadoActiva)
                        ->where('ec.Estado', $tnEstadoActiva);

                    if (!is_null($toRegla->Maquina)) {
                        $toConsulta->where('c.Maquina', (int)$toRegla->Maquina);
                    }
                    if (!is_null($toRegla->Celda)) {
                        $toConsulta->where('ec.Celda', (int)$toRegla->Celda);
                    }
                    if (!is_null($toRegla->UmbralMinimo)) {
                        $toConsulta->where('ec.CantidadDisponible', '<=', (int)$toRegla->UmbralMinimo);
                    }

                    $laFilas = $toConsulta->select(['c.Maquina', 'ec.Celda', 'ec.ProductoEmpresa'])->limit(50)->get();
                    foreach ($laFilas as $toFila) {
                        if ($this->existeAlertaAbierta((int)$toFila->Maquina, (int)$toFila->Celda, (int)$toFila->ProductoEmpresa, (int)($toRegla->TipoAlerta ?? 0), $tnEstadoAlertaAbierta)) {
                            $tnSaltadas++;
                            continue;
                        }

                        DB::connection($this->pcConexion)->table('ALERTA')->insert([
                            'TipoAlerta' => (int)($toRegla->TipoAlerta ?? 1),
                            'Maquina' => (int)$toFila->Maquina,
                            'Celda' => (int)$toFila->Celda,
                            'ProductoEmpresa' => (int)$toFila->ProductoEmpresa,
                            'Mensaje' => $tcMensaje,
                            'Prioridad' => (int)$toRegla->Prioridad,
                            'FechaHoraGeneracion' => $tdAhora,
                            'FechaHoraAtencion' => null,
                            'UsuarioAsignado' => null,
                            'Estado' => $tnEstadoAlertaAbierta,
                            'Usr' => 0,
                            'UsrFecha' => $tdAhora->toDateString(),
                            'UsrHora' => $tdAhora->format('H:i:s'),
                        ]);
                        $tnGeneradas++;
                    }

                    $lbAplica = true;
                }

                if (strtoupper((string)$toRegla->TipoRegla) === 'TELEMETRIA_UMBRAL') {
                    $toUltima = DB::connection($this->pcConexion)
                        ->table('TELEMETRIA as t')
                        ->join('DISPOSITIVO as d', 'd.Dispositivo', '=', 't.Dispositivo')
                        ->join('MAQUINADISPOSITIVO as md', 'md.Dispositivo', '=', 'd.Dispositivo')
                        ->join('MAQUINA as m', 'm.Maquina', '=', 'md.Maquina')
                        ->where('t.Estado', $tnEstadoActiva)
                        ->where('md.Estado', $tnEstadoActiva)
                        ->where('m.Estado', $tnEstadoActiva);

                    if (!is_null($toRegla->Maquina)) {
                        $toUltima->where('m.Maquina', (int)$toRegla->Maquina);
                    }
                    if (!is_null($toRegla->TipoTelemetria)) {
                        $toUltima->where('t.TipoTelemetria', (int)$toRegla->TipoTelemetria);
                    }

                    $laTelemetria = $toUltima
                        ->select(['m.Maquina', 't.ValorNumerico'])
                        ->orderByDesc('t.FechaHora')
                        ->limit(50)
                        ->get();

                    foreach ($laTelemetria as $toTele) {
                        $tnValor = (float)($toTele->ValorNumerico ?? 0);
                        $lbDispara = false;
                        if (!is_null($toRegla->UmbralMinimo) && $tnValor < (float)$toRegla->UmbralMinimo) {
                            $lbDispara = true;
                        }
                        if (!is_null($toRegla->UmbralMaximo) && $tnValor > (float)$toRegla->UmbralMaximo) {
                            $lbDispara = true;
                        }

                        if (!$lbDispara) {
                            continue;
                        }

                        if ($this->existeAlertaAbierta((int)$toTele->Maquina, null, null, (int)($toRegla->TipoAlerta ?? 0), $tnEstadoAlertaAbierta)) {
                            $tnSaltadas++;
                            continue;
                        }

                        DB::connection($this->pcConexion)->table('ALERTA')->insert([
                            'TipoAlerta' => (int)($toRegla->TipoAlerta ?? 1),
                            'Maquina' => (int)$toTele->Maquina,
                            'Celda' => null,
                            'ProductoEmpresa' => null,
                            'Mensaje' => $tcMensaje,
                            'Prioridad' => (int)$toRegla->Prioridad,
                            'FechaHoraGeneracion' => $tdAhora,
                            'FechaHoraAtencion' => null,
                            'UsuarioAsignado' => null,
                            'Estado' => $tnEstadoAlertaAbierta,
                            'Usr' => 0,
                            'UsrFecha' => $tdAhora->toDateString(),
                            'UsrHora' => $tdAhora->format('H:i:s'),
                        ]);
                        $tnGeneradas++;
                    }

                    $lbAplica = true;
                }

                if (!$lbAplica) {
                    $tnSaltadas++;
                }
            } catch (\Throwable) {
                $tnErrores++;
            }
        }

        return [
            'Reglas' => count($laReglas),
            'Generadas' => $tnGeneradas,
            'Saltadas' => $tnSaltadas,
            'Errores' => $tnErrores,
        ];
    }

    private function existeAlertaAbierta(int $tnMaquina, ?int $tnCelda, ?int $tnProductoEmpresa, int $tnTipoAlerta, int $tnEstadoAbierta): bool
    {
        $to = DB::connection($this->pcConexion)->table('ALERTA')->where('Maquina', $tnMaquina)->where('Estado', $tnEstadoAbierta);
        if ($tnTipoAlerta > 0) {
            $to->where('TipoAlerta', $tnTipoAlerta);
        }

        if ($tnCelda !== null) {
            $to->where('Celda', $tnCelda);
        }

        if ($tnProductoEmpresa !== null) {
            $to->where('ProductoEmpresa', $tnProductoEmpresa);
        }

        return $to->exists();
    }

    private function cambiarEstadoAlerta(int $tnAlerta, int $tnEstadoDestino, int $tnUsuarioSesion, ?string $tcMotivo, string $tcAccion): array
    {
        $tnAbierta = $this->toEstadoNegocio->estado('ALERTA', 1);
        $tnAtendida = $this->toEstadoNegocio->estado('ALERTA', 2);
        $tnEscalada = $this->toEstadoNegocio->estado('ALERTA', 3);
        $tnCerrada = $this->toEstadoNegocio->estado('ALERTA', 4);

        return DB::connection($this->pcConexion)->transaction(function () use ($tnAlerta, $tnEstadoDestino, $tnUsuarioSesion, $tcMotivo, $tcAccion, $tnAbierta, $tnAtendida, $tnEscalada, $tnCerrada): array {
            $lo = DB::connection($this->pcConexion)->table('ALERTA')->where('Alerta', $tnAlerta)->lockForUpdate()->first();
            if (!$lo) {
                return ['Estado' => 'NO_ENCONTRADO'];
            }

            $tnActual = (int)$lo->Estado;
            if ($tnActual === $tnEstadoDestino) {
                return ['Estado' => 'IDEMPOTENTE', 'Datos' => $this->obtenerAlerta($tnAlerta)];
            }

            $lbValida = false;
            if ($tnEstadoDestino === $tnAtendida && $tnActual === $tnAbierta) {
                $lbValida = true;
            }
            if ($tnEstadoDestino === $tnEscalada && ($tnActual === $tnAbierta || $tnActual === $tnAtendida)) {
                $lbValida = true;
            }
            if ($tnEstadoDestino === $tnCerrada && in_array($tnActual, [$tnAbierta, $tnAtendida, $tnEscalada], true)) {
                $lbValida = true;
            }

            if (!$lbValida) {
                return ['Estado' => 'TRANSICION_INVALIDA', 'Datos' => $this->obtenerAlerta($tnAlerta)];
            }

            $tdAhora = now();
            DB::connection($this->pcConexion)->table('ALERTA')->where('Alerta', $tnAlerta)->update([
                'Estado' => $tnEstadoDestino,
                'FechaHoraAtencion' => $tdAhora,
                'UsuarioAsignado' => $tnUsuarioSesion,
                'Usr' => $tnUsuarioSesion,
                'UsrFecha' => $tdAhora->toDateString(),
                'UsrHora' => $tdAhora->format('H:i:s'),
            ]);

            $laDespues = $this->obtenerAlerta($tnAlerta) ?? [];
            $this->toAuditoria->registrar('ALERTA', $tnAlerta, $tcAccion, (array)$lo, $laDespues, $tnUsuarioSesion, $tcMotivo);

            return ['Estado' => 'OK', 'Datos' => $laDespues];
        });
    }

    private function normalizar(object|array $tmFila): array
    {
        $la = is_array($tmFila) ? $tmFila : (array)$tmFila;
        $la['Version'] = $this->toControlVersion->versionDesdeFila($tmFila);
        return $la;
    }
}

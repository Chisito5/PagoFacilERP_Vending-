<?php

namespace App\Modulos\MaquinaOperativo\Services;

use App\Soporte\AuditoriaService;
use App\Soporte\ControlVersionService;
use App\Soporte\EstadoNegocioService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class MaquinaOperativoService
{
    private string $pcConexion = 'mysqlNegocio';

    public function __construct(
        private EstadoNegocioService $toEstadoNegocio,
        private ControlVersionService $toControlVersion,
        private AuditoriaService $toAuditoria
    ) {
    }

    public function obtenerUbicacion(int $tnMaquina): ?array
    {
        $lo = DB::connection($this->pcConexion)
            ->table('MAQUINA as m')
            ->leftJoin('UBICACION as u', 'u.Ubicacion', '=', 'm.UbicacionActual')
            ->select([
                'm.Maquina', 'm.UbicacionActual', 'm.UsrFecha', 'm.UsrHora',
                'u.Ubicacion', 'u.Empresa', 'u.NombreUbicacion', 'u.Departamento', 'u.Ciudad',
                'u.Zona', 'u.Direccion', 'u.Referencia', 'u.Latitud', 'u.Longitud', 'u.ContactoNombre',
                'u.ContactoTelefono', 'u.Estado'
            ])
            ->where('m.Maquina', $tnMaquina)
            ->first();

        if (!$lo) {
            return null;
        }

        $la = (array)$lo;
        $la['Version'] = $this->toControlVersion->versionDesdeFila($lo);
        return $la;
    }

    public function actualizarUbicacion(int $tnMaquina, int $tnUbicacion, string $tcVersion, int $tnUsuarioSesion, ?string $tcMotivo): array
    {
        return DB::connection($this->pcConexion)->transaction(function () use ($tnMaquina, $tnUbicacion, $tcVersion, $tnUsuarioSesion, $tcMotivo): array {
            $loActual = DB::connection($this->pcConexion)->table('MAQUINA')->where('Maquina', $tnMaquina)->lockForUpdate()->first();
            if (!$loActual) {
                return ['Estado' => 'NO_ENCONTRADO'];
            }
            if (!$this->toControlVersion->coincide($tcVersion, $loActual)) {
                return ['Estado' => 'CONFLICTO_VERSION', 'Actual' => $this->obtenerUbicacion($tnMaquina)];
            }

            $tdAhora = now();
            DB::connection($this->pcConexion)->table('MAQUINA')->where('Maquina', $tnMaquina)->update([
                'UbicacionActual' => $tnUbicacion,
                'Usr' => $tnUsuarioSesion,
                'UsrFecha' => $tdAhora->toDateString(),
                'UsrHora' => $tdAhora->format('H:i:s'),
            ]);

            $laAntes = (array)$loActual;
            $laDespues = $this->obtenerUbicacion($tnMaquina) ?? [];
            $this->toAuditoria->registrar('MAQUINA', $tnMaquina, 'CAMBIO_UBICACION', $laAntes, $laDespues, $tnUsuarioSesion, $tcMotivo);

            return ['Estado' => 'OK', 'Datos' => $laDespues];
        });
    }

    public function obtenerEstadoOperativo(int $tnMaquina): ?array
    {
        $lo = DB::connection($this->pcConexion)
            ->table('MAQUINAESTADOOPERATIVO')
            ->where('Maquina', $tnMaquina)
            ->first();

        if (!$lo) {
            $tdAhora = now();
            $tnEstadoActivo = $this->toEstadoNegocio->activoGeneral();
            DB::connection($this->pcConexion)->table('MAQUINAESTADOOPERATIVO')->insert([
                'Maquina' => $tnMaquina,
                'EstadoOperativo' => 'OPERATIVA',
                'Motivo' => 'Estado inicial por defecto',
                'Estado' => $tnEstadoActivo,
                'Usr' => 0,
                'UsrFecha' => $tdAhora->toDateString(),
                'UsrHora' => $tdAhora->format('H:i:s'),
            ]);
            $lo = DB::connection($this->pcConexion)->table('MAQUINAESTADOOPERATIVO')->where('Maquina', $tnMaquina)->first();
        }

        $la = (array)$lo;
        $la['Version'] = $this->toControlVersion->versionDesdeFila($lo);
        return $la;
    }

    public function actualizarEstadoOperativo(int $tnMaquina, string $tcEstadoOperativo, int $tnUsuarioSesion, ?string $tcMotivo): array
    {
        $tcEstadoOperativo = strtoupper(trim($tcEstadoOperativo));
        $laPermitidos = ['OPERATIVA', 'MANTENIMIENTO', 'FUERA_SERVICIO'];
        if (!in_array($tcEstadoOperativo, $laPermitidos, true)) {
            return ['Estado' => 'ESTADO_INVALIDO'];
        }

        return DB::connection($this->pcConexion)->transaction(function () use ($tnMaquina, $tcEstadoOperativo, $tnUsuarioSesion, $tcMotivo): array {
            $tdAhora = now();
            $tnEstadoActivo = $this->toEstadoNegocio->activoGeneral();

            $loActual = DB::connection($this->pcConexion)
                ->table('MAQUINAESTADOOPERATIVO')
                ->where('Maquina', $tnMaquina)
                ->lockForUpdate()
                ->first();

            if (!$loActual) {
                DB::connection($this->pcConexion)->table('MAQUINAESTADOOPERATIVO')->insert([
                    'Maquina' => $tnMaquina,
                    'EstadoOperativo' => $tcEstadoOperativo,
                    'Motivo' => $tcMotivo,
                    'Estado' => $tnEstadoActivo,
                    'Usr' => $tnUsuarioSesion,
                    'UsrFecha' => $tdAhora->toDateString(),
                    'UsrHora' => $tdAhora->format('H:i:s'),
                ]);

                DB::connection($this->pcConexion)->table('MAQUINAESTADOOPERATIVOHISTORIAL')->insert([
                    'Maquina' => $tnMaquina,
                    'EstadoOperativoAnterior' => null,
                    'EstadoOperativoNuevo' => $tcEstadoOperativo,
                    'Motivo' => $tcMotivo,
                    'FechaCambio' => $tdAhora,
                    'Estado' => $tnEstadoActivo,
                    'Usr' => $tnUsuarioSesion,
                    'UsrFecha' => $tdAhora->toDateString(),
                    'UsrHora' => $tdAhora->format('H:i:s'),
                ]);

                $laDespues = $this->obtenerEstadoOperativo($tnMaquina) ?? [];
                $this->toAuditoria->registrar('MAQUINAESTADOOPERATIVO', $tnMaquina, 'CREAR', null, $laDespues, $tnUsuarioSesion, $tcMotivo);

                return ['Estado' => 'OK', 'Datos' => $laDespues];
            }

            if ((string)$loActual->EstadoOperativo === $tcEstadoOperativo) {
                return ['Estado' => 'IDEMPOTENTE', 'Datos' => $this->obtenerEstadoOperativo($tnMaquina)];
            }

            DB::connection($this->pcConexion)->table('MAQUINAESTADOOPERATIVO')->where('MaquinaEstadoOperativo', (int)$loActual->MaquinaEstadoOperativo)->update([
                'EstadoOperativo' => $tcEstadoOperativo,
                'Motivo' => $tcMotivo,
                'Usr' => $tnUsuarioSesion,
                'UsrFecha' => $tdAhora->toDateString(),
                'UsrHora' => $tdAhora->format('H:i:s'),
            ]);

            DB::connection($this->pcConexion)->table('MAQUINAESTADOOPERATIVOHISTORIAL')->insert([
                'Maquina' => $tnMaquina,
                'EstadoOperativoAnterior' => (string)$loActual->EstadoOperativo,
                'EstadoOperativoNuevo' => $tcEstadoOperativo,
                'Motivo' => $tcMotivo,
                'FechaCambio' => $tdAhora,
                'Estado' => $tnEstadoActivo,
                'Usr' => $tnUsuarioSesion,
                'UsrFecha' => $tdAhora->toDateString(),
                'UsrHora' => $tdAhora->format('H:i:s'),
            ]);

            $laDespues = $this->obtenerEstadoOperativo($tnMaquina) ?? [];
            $this->toAuditoria->registrar('MAQUINAESTADOOPERATIVO', $tnMaquina, 'ACTUALIZAR', (array)$loActual, $laDespues, $tnUsuarioSesion, $tcMotivo);

            return ['Estado' => 'OK', 'Datos' => $laDespues];
        });
    }

    public function listarHistorial(int $tnMaquina, int $tnPagina, int $tnTamanoPagina): LengthAwarePaginator
    {
        $tnTamanoPagina = max(1, min($tnTamanoPagina, 200));

        return DB::connection($this->pcConexion)
            ->table('MAQUINAESTADOOPERATIVOHISTORIAL')
            ->where('Maquina', $tnMaquina)
            ->orderByDesc('MaquinaEstadoOperativoHistorial')
            ->paginate($tnTamanoPagina, ['*'], 'Pagina', max(1, $tnPagina));
    }
}

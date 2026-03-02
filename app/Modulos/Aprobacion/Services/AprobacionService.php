<?php

namespace App\Modulos\Aprobacion\Services;

use App\Soporte\AuditoriaService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class AprobacionService
{
    private string $pcConexion = 'mysqlNegocio';

    public function __construct(private AuditoriaService $toAuditoria)
    {
    }

    /** @param array<string,mixed> $taDatos */
    public function Solicitar(array $taDatos, int $tnUsuario): array
    {
        $tdAhora = now();

        $tnAprobacion = DB::connection($this->pcConexion)
            ->table('APROBACION')
            ->insertGetId([
                'Entidad' => strtoupper((string)$taDatos['Entidad']),
                'EntidadId' => isset($taDatos['EntidadId']) ? (string)$taDatos['EntidadId'] : null,
                'AccionSolicitada' => strtoupper((string)$taDatos['AccionSolicitada']),
                'DatosPropuestos' => isset($taDatos['DatosPropuestos']) ? json_encode($taDatos['DatosPropuestos'], JSON_UNESCAPED_UNICODE) : null,
                'Motivo' => $taDatos['Motivo'] ?? null,
                'Estado' => 1,
                'SolicitadoPor' => $tnUsuario > 0 ? $tnUsuario : null,
                'AprobadoPor' => null,
                'FechaSolicitud' => $tdAhora,
                'FechaResolucion' => null,
                'ComentarioResolucion' => null,
                'Usr' => $tnUsuario,
                'UsrFecha' => $tdAhora->toDateString(),
                'UsrHora' => $tdAhora->format('H:i:s'),
            ]);

        $la = $this->Obtener($tnAprobacion);
        $this->toAuditoria->registrar('APROBACION', $tnAprobacion, 'SOLICITAR', null, $la, $tnUsuario, $taDatos['Motivo'] ?? null);

        return $la ?? [];
    }

    public function Aprobar(int $tnAprobacion, int $tnUsuario, ?string $tcMotivo): array
    {
        return $this->resolverAprobacion($tnAprobacion, $tnUsuario, $tcMotivo, true);
    }

    public function Rechazar(int $tnAprobacion, int $tnUsuario, ?string $tcMotivo): array
    {
        return $this->resolverAprobacion($tnAprobacion, $tnUsuario, $tcMotivo, false);
    }

    public function Listar(?int $tnEstado, ?string $tcEntidad, ?string $tcFechaDesde, ?string $tcFechaHasta, int $tnPagina, int $tnTamanoPagina): LengthAwarePaginator
    {
        $tnTamanoPagina = max(1, min($tnTamanoPagina, 200));

        $toConsulta = DB::connection($this->pcConexion)
            ->table('APROBACION as a')
            ->leftJoin('USUARIO as us', 'us.Usuario', '=', 'a.SolicitadoPor')
            ->leftJoin('USUARIO as ua', 'ua.Usuario', '=', 'a.AprobadoPor')
            ->select([
                'a.Aprobacion', 'a.Entidad', 'a.EntidadId', 'a.AccionSolicitada', 'a.DatosPropuestos',
                'a.Motivo', 'a.Estado', 'a.SolicitadoPor', 'us.NombreUsuario as SolicitadoPorUsuario',
                'a.AprobadoPor', 'ua.NombreUsuario as AprobadoPorUsuario', 'a.FechaSolicitud',
                'a.FechaResolucion', 'a.ComentarioResolucion', 'a.Usr', 'a.UsrFecha', 'a.UsrHora'
            ])
            ->orderByDesc('a.Aprobacion');

        if ($tnEstado !== null && $tnEstado > 0) {
            $toConsulta->where('a.Estado', $tnEstado);
        }

        if ($tcEntidad !== null && trim($tcEntidad) !== '') {
            $toConsulta->whereRaw('UPPER(a.Entidad) = ?', [strtoupper(trim($tcEntidad))]);
        }

        if ($tcFechaDesde !== null) {
            $toConsulta->whereDate('a.FechaSolicitud', '>=', $tcFechaDesde);
        }

        if ($tcFechaHasta !== null) {
            $toConsulta->whereDate('a.FechaSolicitud', '<=', $tcFechaHasta);
        }

        return $toConsulta->paginate($tnTamanoPagina, ['*'], 'Pagina', max(1, $tnPagina));
    }

    public function Obtener(int $tnAprobacion): ?array
    {
        $lo = DB::connection($this->pcConexion)
            ->table('APROBACION')
            ->where('Aprobacion', $tnAprobacion)
            ->first();

        if (!$lo) {
            return null;
        }

        $la = (array)$lo;
        $la['DatosPropuestos'] = $this->decodificarJson($la['DatosPropuestos'] ?? null);

        return $la;
    }

    private function resolverAprobacion(int $tnAprobacion, int $tnUsuario, ?string $tcMotivo, bool $lbAprobar): array
    {
        return DB::connection($this->pcConexion)->transaction(function () use ($tnAprobacion, $tnUsuario, $tcMotivo, $lbAprobar): array {
            $loActual = DB::connection($this->pcConexion)
                ->table('APROBACION')
                ->where('Aprobacion', $tnAprobacion)
                ->lockForUpdate()
                ->first();

            if (!$loActual) {
                return ['Estado' => 'NO_ENCONTRADO'];
            }

            $tnEstadoObjetivo = $lbAprobar ? 2 : 3;

            if ((int)$loActual->Estado === $tnEstadoObjetivo) {
                return ['Estado' => 'IDEMPOTENTE', 'Datos' => $this->Obtener($tnAprobacion)];
            }

            if ((int)$loActual->Estado !== 1) {
                return ['Estado' => 'TRANSICION_INVALIDA', 'Datos' => $this->Obtener($tnAprobacion)];
            }

            $tdAhora = now();
            DB::connection($this->pcConexion)
                ->table('APROBACION')
                ->where('Aprobacion', $tnAprobacion)
                ->update([
                    'Estado' => $tnEstadoObjetivo,
                    'AprobadoPor' => $tnUsuario > 0 ? $tnUsuario : null,
                    'FechaResolucion' => $tdAhora,
                    'ComentarioResolucion' => $tcMotivo,
                    'Usr' => $tnUsuario,
                    'UsrFecha' => $tdAhora->toDateString(),
                    'UsrHora' => $tdAhora->format('H:i:s'),
                ]);

            $laAntes = (array)$loActual;
            $laDespues = $this->Obtener($tnAprobacion) ?? [];

            $this->toAuditoria->registrar(
                'APROBACION',
                $tnAprobacion,
                $lbAprobar ? 'APROBAR' : 'RECHAZAR',
                $laAntes,
                $laDespues,
                $tnUsuario,
                $tcMotivo
            );

            return ['Estado' => 'OK', 'Datos' => $laDespues];
        });
    }

    /** @return array<string,mixed>|array<int,mixed>|null */
    private function decodificarJson(mixed $tmValor): array|null
    {
        if (!is_string($tmValor) || trim($tmValor) === '') {
            return null;
        }

        $la = json_decode($tmValor, true);
        return is_array($la) ? $la : null;
    }
}

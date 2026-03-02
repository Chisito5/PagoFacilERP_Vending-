<?php

namespace App\Modulos\Reporte\Services;

use App\Jobs\ProcesarReporteGeneradoJob;
use App\Soporte\ArchivoStorageService;
use App\Soporte\AuditoriaService;
use App\Soporte\EstadoNegocioService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class ReporteService
{
    private string $pcConexion = 'mysqlNegocio';

    public function __construct(
        private EstadoNegocioService $toEstadoNegocio,
        private AuditoriaService $toAuditoria,
        private ArchivoStorageService $toArchivoStorage
    ) {
    }

    /** @param array<string,mixed> $taFiltros */
    public function solicitar(string $tcTipoReporte, string $tcFormato, array $taFiltros, ?int $tnEmpresa, int $tnUsuario): array
    {
        $tcFormato = strtoupper(trim($tcFormato));
        if (!in_array($tcFormato, ['CSV', 'XLSX', 'PDF'], true)) {
            return ['Estado' => 'FORMATO_INVALIDO'];
        }

        $tdAhora = now();
        $tnEstadoPendiente = $this->toEstadoNegocio->estado('REPORTE', 1);

        $tnReporte = DB::connection($this->pcConexion)
            ->table('REPORTEGENERADO')
            ->insertGetId([
                'Empresa' => $tnEmpresa,
                'UsuarioSolicitante' => $tnUsuario,
                'TipoReporte' => strtoupper(trim($tcTipoReporte)),
                'Formato' => $tcFormato,
                'Filtros' => json_encode($taFiltros, JSON_UNESCAPED_UNICODE),
                'Estado' => $tnEstadoPendiente,
                'FechaSolicitud' => $tdAhora,
                'FechaInicioProceso' => null,
                'FechaFinProceso' => null,
                'FechaExpiracion' => null,
                'MensajeEstado' => 'Pendiente en cola',
                'Usr' => $tnUsuario,
                'UsrFecha' => $tdAhora->toDateString(),
                'UsrHora' => $tdAhora->format('H:i:s'),
            ]);

        ProcesarReporteGeneradoJob::dispatch($tnReporte);

        $la = $this->obtener($tnReporte);
        $this->toAuditoria->registrar('REPORTEGENERADO', $tnReporte, 'SOLICITAR', null, $la, $tnUsuario, null);

        return ['Estado' => 'OK', 'Datos' => $la];
    }

    public function listar(int $tnUsuario, bool $lbGlobal, int $tnPagina, int $tnTamanoPagina): LengthAwarePaginator
    {
        $tnTamanoPagina = max(1, min($tnTamanoPagina, 200));
        $to = DB::connection($this->pcConexion)
            ->table('REPORTEGENERADO as r')
            ->leftJoin('REPORTEARCHIVO as a', function ($join) {
                $join->on('a.ReporteGenerado', '=', 'r.ReporteGenerado');
                $join->where('a.Estado', '=', DB::raw('r.Estado'));
            })
            ->select([
                'r.ReporteGenerado', 'r.Empresa', 'r.UsuarioSolicitante', 'r.TipoReporte', 'r.Formato',
                'r.Estado', 'r.FechaSolicitud', 'r.FechaInicioProceso', 'r.FechaFinProceso', 'r.FechaExpiracion',
                'r.MensajeEstado', 'r.Usr', 'r.UsrFecha', 'r.UsrHora',
                'a.ReporteArchivo', 'a.NombreArchivo', 'a.RutaArchivo', 'a.TamanoBytes'
            ])
            ->orderByDesc('r.ReporteGenerado');

        if (!$lbGlobal) {
            $to->where('r.UsuarioSolicitante', $tnUsuario);
        }

        return $to->paginate($tnTamanoPagina, ['*'], 'Pagina', max(1, $tnPagina));
    }

    public function obtener(int $tnReporte): ?array
    {
        $lo = DB::connection($this->pcConexion)
            ->table('REPORTEGENERADO')
            ->where('ReporteGenerado', $tnReporte)
            ->first();

        if (!$lo) {
            return null;
        }

        $la = (array)$lo;
        $la['Filtros'] = $this->decodeJson($la['Filtros'] ?? null);
        $la['Archivo'] = DB::connection($this->pcConexion)
            ->table('REPORTEARCHIVO')
            ->where('ReporteGenerado', $tnReporte)
            ->orderByDesc('ReporteArchivo')
            ->first();

        if ($la['Archivo']) {
            $la['Archivo'] = (array)$la['Archivo'];
            $la['Archivo']['UrlDescarga'] = route('reporte.descargar', ['tnReporte' => $tnReporte]);
        }

        return $la;
    }

    public function obtenerRutaDescarga(int $tnReporte, int $tnUsuario, bool $lbGlobal): array
    {
        $la = $this->obtener($tnReporte);
        if (!$la) {
            return ['Estado' => 'NO_ENCONTRADO'];
        }

        if (!$lbGlobal && (int)$la['UsuarioSolicitante'] !== $tnUsuario) {
            return ['Estado' => 'NO_AUTORIZADO'];
        }

        if ((int)$la['Estado'] !== $this->toEstadoNegocio->estado('REPORTE', 3)) {
            return ['Estado' => 'NO_DISPONIBLE'];
        }

        if (empty($la['Archivo']['RutaArchivo'])) {
            return ['Estado' => 'NO_DISPONIBLE'];
        }

        if (!empty($la['FechaExpiracion']) && now()->greaterThan($la['FechaExpiracion'])) {
            return ['Estado' => 'EXPIRADO'];
        }

        return [
            'Estado' => 'OK',
            'Ruta' => (string)$la['Archivo']['RutaArchivo'],
            'Disco' => $this->toArchivoStorage->obtenerDiscoRuta((string)$la['Archivo']['RutaArchivo']),
            'Nombre' => (string)$la['Archivo']['NombreArchivo'],
            'Mime' => (string)($la['Archivo']['MimeArchivo'] ?? 'application/octet-stream'),
        ];
    }

    public function marcarProcesando(int $tnReporte): void
    {
        $tdAhora = now();
        DB::connection($this->pcConexion)
            ->table('REPORTEGENERADO')
            ->where('ReporteGenerado', $tnReporte)
            ->update([
                'Estado' => $this->toEstadoNegocio->estado('REPORTE', 2),
                'FechaInicioProceso' => $tdAhora,
                'MensajeEstado' => 'Procesando',
                'UsrFecha' => $tdAhora->toDateString(),
                'UsrHora' => $tdAhora->format('H:i:s'),
            ]);
    }

    public function marcarError(int $tnReporte, string $tcMensaje): void
    {
        $tdAhora = now();
        DB::connection($this->pcConexion)
            ->table('REPORTEGENERADO')
            ->where('ReporteGenerado', $tnReporte)
            ->update([
                'Estado' => $this->toEstadoNegocio->estado('REPORTE', 4),
                'FechaFinProceso' => $tdAhora,
                'MensajeEstado' => mb_substr($tcMensaje, 0, 255),
                'UsrFecha' => $tdAhora->toDateString(),
                'UsrHora' => $tdAhora->format('H:i:s'),
            ]);
    }

    public function marcarListo(int $tnReporte, string $tcNombreArchivo, string $tcRutaArchivo, string $tcMime, int $tnTamano, int $tnUsuario): void
    {
        $tdAhora = now();
        $tnEstadoListo = $this->toEstadoNegocio->estado('REPORTE', 3);

        DB::connection($this->pcConexion)->transaction(function () use ($tnReporte, $tcNombreArchivo, $tcRutaArchivo, $tcMime, $tnTamano, $tnUsuario, $tdAhora, $tnEstadoListo): void {
            DB::connection($this->pcConexion)
                ->table('REPORTEARCHIVO')
                ->where('ReporteGenerado', $tnReporte)
                ->update([
                    'Estado' => $this->toEstadoNegocio->inactivoGeneral(),
                    'Usr' => $tnUsuario,
                    'UsrFecha' => $tdAhora->toDateString(),
                    'UsrHora' => $tdAhora->format('H:i:s'),
                ]);

            DB::connection($this->pcConexion)
                ->table('REPORTEARCHIVO')
                ->insert([
                    'ReporteGenerado' => $tnReporte,
                    'NombreArchivo' => $tcNombreArchivo,
                    'RutaArchivo' => $tcRutaArchivo,
                    'MimeArchivo' => $tcMime,
                    'TamanoBytes' => $tnTamano,
                    'Estado' => $tnEstadoListo,
                    'Usr' => $tnUsuario,
                    'UsrFecha' => $tdAhora->toDateString(),
                    'UsrHora' => $tdAhora->format('H:i:s'),
                ]);

            DB::connection($this->pcConexion)
                ->table('REPORTEGENERADO')
                ->where('ReporteGenerado', $tnReporte)
                ->update([
                    'Estado' => $tnEstadoListo,
                    'FechaFinProceso' => $tdAhora,
                    'FechaExpiracion' => $tdAhora->copy()->addDays(7),
                    'MensajeEstado' => 'Reporte listo para descarga',
                    'Usr' => $tnUsuario,
                    'UsrFecha' => $tdAhora->toDateString(),
                    'UsrHora' => $tdAhora->format('H:i:s'),
                ]);
        });
    }

    public function limpiarExpirados(int $tnDias = 7): int
    {
        $tnEstadoListo = $this->toEstadoNegocio->estado('REPORTE', 3);
        $tnEstadoExpirado = $this->toEstadoNegocio->estado('REPORTE', 5);
        $tdLimite = now()->subDays($tnDias);

        $laReportes = DB::connection($this->pcConexion)
            ->table('REPORTEGENERADO')
            ->where('Estado', $tnEstadoListo)
            ->whereNotNull('FechaExpiracion')
            ->where('FechaExpiracion', '<', $tdLimite)
            ->get();

        $tnTotal = 0;
        foreach ($laReportes as $toReporte) {
            DB::connection($this->pcConexion)
                ->table('REPORTEGENERADO')
                ->where('ReporteGenerado', (int)$toReporte->ReporteGenerado)
                ->update([
                    'Estado' => $tnEstadoExpirado,
                    'MensajeEstado' => 'Reporte expirado',
                    'UsrFecha' => now()->toDateString(),
                    'UsrHora' => now()->format('H:i:s'),
                ]);

            $laArchivos = DB::connection($this->pcConexion)
                ->table('REPORTEARCHIVO')
                ->where('ReporteGenerado', (int)$toReporte->ReporteGenerado)
                ->get();

            foreach ($laArchivos as $toArchivo) {
                if (!empty($toArchivo->RutaArchivo)) {
                    $this->toArchivoStorage->eliminar((string)$toArchivo->RutaArchivo);
                }
            }

            $tnTotal++;
        }

        return $tnTotal;
    }

    /** @return array<string,mixed>|null */
    private function decodeJson(mixed $tmValor): ?array
    {
        if (!is_string($tmValor) || trim($tmValor) === '') {
            return null;
        }
        $la = json_decode($tmValor, true);
        return is_array($la) ? $la : null;
    }
}

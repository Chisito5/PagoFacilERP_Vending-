<?php

namespace App\Modulos\IntegracionIot\Services;

use App\Jobs\ProcesarEventoIotJob;
use App\Soporte\EstadoNegocioService;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class IntegracionIotService
{
    private string $pcConexion = 'mysqlNegocio';

    public function __construct(private EstadoNegocioService $toEstadoNegocio)
    {
    }

    /** @param array<string,mixed> $taPayload */
    public function registrarWebhook(string $tcOrigen, string $tcEventId, string $tcTimestamp, string $tcFirma, array $taPayload): array
    {
        $tnEstadoRecibido = $this->toEstadoNegocio->estado('IOTEVENTO', 1);
        $tdAhora = now();
        $tdEvento = $this->resolverFechaEvento($tcTimestamp);

        return DB::connection($this->pcConexion)->transaction(function () use ($tcOrigen, $tcEventId, $tcFirma, $taPayload, $tnEstadoRecibido, $tdAhora, $tdEvento): array {
            $loExiste = DB::connection($this->pcConexion)
                ->table('IOTEVENTO')
                ->where('Origen', $tcOrigen)
                ->where('EventId', $tcEventId)
                ->lockForUpdate()
                ->first();

            if ($loExiste) {
                return [
                    'Estado' => 'IDEMPOTENTE',
                    'Datos' => $this->obtener((int)$loExiste->IotEvento),
                ];
            }

            $tnEvento = DB::connection($this->pcConexion)->table('IOTEVENTO')->insertGetId([
                'Origen' => $tcOrigen,
                'EventId' => $tcEventId,
                'FechaEvento' => $tdEvento,
                'Payload' => json_encode($taPayload, JSON_UNESCAPED_UNICODE),
                'Firma' => $tcFirma,
                'IntentosProcesamiento' => 0,
                'Estado' => $tnEstadoRecibido,
                'FechaRecepcion' => $tdAhora,
                'FechaProcesamiento' => null,
                'ErrorUltimo' => null,
                'Usr' => 0,
                'UsrFecha' => $tdAhora->toDateString(),
                'UsrHora' => $tdAhora->format('H:i:s'),
            ]);

            DB::connection($this->pcConexion)->table('IOTEVENTOINTENTO')->insert([
                'IotEvento' => $tnEvento,
                'NumeroIntento' => 1,
                'FechaIntento' => $tdAhora,
                'Estado' => $tnEstadoRecibido,
                'Mensaje' => 'Evento recibido',
                'Usr' => 0,
                'UsrFecha' => $tdAhora->toDateString(),
                'UsrHora' => $tdAhora->format('H:i:s'),
            ]);

            ProcesarEventoIotJob::dispatch($tnEvento);

            return [
                'Estado' => 'OK',
                'Datos' => $this->obtener($tnEvento),
            ];
        });
    }

    public function listar(?string $tcOrigen, ?int $tnEstado, int $tnPagina, int $tnTamanoPagina): LengthAwarePaginator
    {
        $tnTamanoPagina = max(1, min($tnTamanoPagina, 200));
        $to = DB::connection($this->pcConexion)->table('IOTEVENTO')->orderByDesc('IotEvento');
        if ($tcOrigen !== null && trim($tcOrigen) !== '') {
            $to->where('Origen', trim($tcOrigen));
        }
        if ($tnEstado !== null && $tnEstado > 0) {
            $to->where('Estado', $tnEstado);
        }
        return $to->paginate($tnTamanoPagina, ['*'], 'Pagina', max(1, $tnPagina));
    }

    public function obtener(int $tnEvento): ?array
    {
        $lo = DB::connection($this->pcConexion)->table('IOTEVENTO')->where('IotEvento', $tnEvento)->first();
        if (!$lo) {
            return null;
        }

        $la = (array)$lo;
        $la['Payload'] = $this->decodeJson($la['Payload'] ?? null);
        $la['Intentos'] = DB::connection($this->pcConexion)
            ->table('IOTEVENTOINTENTO')
            ->where('IotEvento', $tnEvento)
            ->orderBy('IotEventoIntento')
            ->get()
            ->toArray();

        return $la;
    }

    public function procesarEvento(int $tnEvento): void
    {
        DB::connection($this->pcConexion)->transaction(function () use ($tnEvento): void {
            $lo = DB::connection($this->pcConexion)
                ->table('IOTEVENTO')
                ->where('IotEvento', $tnEvento)
                ->lockForUpdate()
                ->first();

            if (!$lo) {
                return;
            }

            $tnEstadoProcesado = $this->toEstadoNegocio->estado('IOTEVENTO', 2);
            $tnEstadoError = $this->toEstadoNegocio->estado('IOTEVENTO', 3);
            $tdAhora = now();

            if ((int)$lo->Estado === $tnEstadoProcesado) {
                return;
            }

            try {
                $laPayload = $this->decodeJson($lo->Payload ?? null);
                if (!is_array($laPayload)) {
                    throw new \RuntimeException('Payload IoT invalido');
                }

                DB::connection($this->pcConexion)->table('IOTEVENTO')->where('IotEvento', $tnEvento)->update([
                    'IntentosProcesamiento' => (int)$lo->IntentosProcesamiento + 1,
                    'Estado' => $tnEstadoProcesado,
                    'FechaProcesamiento' => $tdAhora,
                    'ErrorUltimo' => null,
                    'UsrFecha' => $tdAhora->toDateString(),
                    'UsrHora' => $tdAhora->format('H:i:s'),
                ]);

                DB::connection($this->pcConexion)->table('IOTEVENTOINTENTO')->insert([
                    'IotEvento' => $tnEvento,
                    'NumeroIntento' => (int)$lo->IntentosProcesamiento + 1,
                    'FechaIntento' => $tdAhora,
                    'Estado' => $tnEstadoProcesado,
                    'Mensaje' => 'Evento procesado',
                    'Usr' => 0,
                    'UsrFecha' => $tdAhora->toDateString(),
                    'UsrHora' => $tdAhora->format('H:i:s'),
                ]);
            } catch (\Throwable $toEx) {
                DB::connection($this->pcConexion)->table('IOTEVENTO')->where('IotEvento', $tnEvento)->update([
                    'IntentosProcesamiento' => (int)$lo->IntentosProcesamiento + 1,
                    'Estado' => $tnEstadoError,
                    'FechaProcesamiento' => $tdAhora,
                    'ErrorUltimo' => mb_substr($toEx->getMessage(), 0, 255),
                    'UsrFecha' => $tdAhora->toDateString(),
                    'UsrHora' => $tdAhora->format('H:i:s'),
                ]);

                DB::connection($this->pcConexion)->table('IOTEVENTOINTENTO')->insert([
                    'IotEvento' => $tnEvento,
                    'NumeroIntento' => (int)$lo->IntentosProcesamiento + 1,
                    'FechaIntento' => $tdAhora,
                    'Estado' => $tnEstadoError,
                    'Mensaje' => mb_substr($toEx->getMessage(), 0, 255),
                    'Usr' => 0,
                    'UsrFecha' => $tdAhora->toDateString(),
                    'UsrHora' => $tdAhora->format('H:i:s'),
                ]);

                throw $toEx;
            }
        });
    }

    public function reencolarPendientes(int $tnMaxIntentos = 5, int $tnLimite = 100): int
    {
        $tnEstadoRecibido = $this->toEstadoNegocio->estado('IOTEVENTO', 1);
        $tnEstadoError = $this->toEstadoNegocio->estado('IOTEVENTO', 3);

        $laPendientes = DB::connection($this->pcConexion)
            ->table('IOTEVENTO')
            ->select('IotEvento')
            ->whereIn('Estado', [$tnEstadoRecibido, $tnEstadoError])
            ->where('IntentosProcesamiento', '<', max(1, $tnMaxIntentos))
            ->orderBy('IotEvento')
            ->limit(max(1, $tnLimite))
            ->get();

        foreach ($laPendientes as $toEvento) {
            ProcesarEventoIotJob::dispatch((int)$toEvento->IotEvento);
        }

        return count($laPendientes);
    }

    /** @return array<string,mixed>|null */
    private function decodeJson(mixed $tm): ?array
    {
        if (!is_string($tm) || trim($tm) === '') {
            return null;
        }
        $la = json_decode($tm, true);
        return is_array($la) ? $la : null;
    }

    private function resolverFechaEvento(string $tcTimestamp): string
    {
        $tcTimestamp = trim($tcTimestamp);
        if ($tcTimestamp !== '' && ctype_digit($tcTimestamp)) {
            return date('Y-m-d H:i:s', (int)$tcTimestamp);
        }

        try {
            return Carbon::parse($tcTimestamp)->format('Y-m-d H:i:s');
        } catch (\Throwable) {
            return now()->format('Y-m-d H:i:s');
        }
    }
}

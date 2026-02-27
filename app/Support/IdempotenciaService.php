<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class IdempotenciaService
{
    public const ESTADO_FALTA_CLAVE = 'falta_clave';
    public const ESTADO_NUEVA = 'nueva';
    public const ESTADO_REPETICION = 'repeticion';
    public const ESTADO_CONFLICTO = 'conflicto';
    public const ESTADO_EN_PROCESO = 'en_proceso';

    /**
     * @return array{
     *   estado:string,
     *   llave:?string,
     *   ruta:string,
     *   metodo:string,
     *   hash_cuerpo:string,
     *   id_idempotencia:?int,
     *   codigo_respuesta:?int,
     *   respuesta:?array
     * }
     */
    public function iniciar(Request $toRequest): array
    {
        $tcLlave = trim((string)$toRequest->header('Clave-Idempotencia', ''));
        $tcRuta = '/' . ltrim($toRequest->path(), '/');
        $tcMetodo = strtoupper((string)$toRequest->method());
        $tcBodyRaw = (string)$toRequest->getContent();
        $tcHashBody = hash('sha256', $tcBodyRaw);

        if ($tcLlave === '') {
            Log::warning('idempotencia_clave_faltante', [
                'Ruta' => $tcRuta,
                'Metodo' => $tcMetodo,
            ]);

            return [
                'estado' => self::ESTADO_FALTA_CLAVE,
                'llave' => null,
                'ruta' => $tcRuta,
                'metodo' => $tcMetodo,
                'hash_cuerpo' => $tcHashBody,
                'id_idempotencia' => null,
                'codigo_respuesta' => null,
                'respuesta' => null,
            ];
        }

        $loRegistro = DB::connection('mysqlNegocio')
            ->table('IDEMPOTENCIA')
            ->where('Llave', $tcLlave)
            ->where('Metodo', $tcMetodo)
            ->where('Ruta', $tcRuta)
            ->lockForUpdate()
            ->first();

        if ($loRegistro) {
            return $this->resolverRegistroExistente($loRegistro, $tcLlave, $tcRuta, $tcMetodo, $tcHashBody);
        }

        $tdAhora = now();
        try {
            $tnIdempotencia = DB::connection('mysqlNegocio')
                ->table('IDEMPOTENCIA')
                ->insertGetId([
                    'Llave' => $tcLlave,
                    'Ruta' => $tcRuta,
                    'Metodo' => $tcMetodo,
                    'HashCuerpo' => $tcHashBody,
                    'CodigoRespuesta' => null,
                    'Respuesta' => null,
                    'Procesado' => 0,
                    'CreadoEn' => $tdAhora,
                    'Usr' => 0,
                    'UsrFecha' => $tdAhora->toDateString(),
                    'UsrHora' => $tdAhora->format('H:i:s'),
                ]);
        } catch (QueryException $toEx) {
            // Condicion de carrera: otra solicitud ya insertó la misma llave.
            $loRegistro = DB::connection('mysqlNegocio')
                ->table('IDEMPOTENCIA')
                ->where('Llave', $tcLlave)
                ->where('Metodo', $tcMetodo)
                ->where('Ruta', $tcRuta)
                ->lockForUpdate()
                ->first();

            if ($loRegistro) {
                return $this->resolverRegistroExistente($loRegistro, $tcLlave, $tcRuta, $tcMetodo, $tcHashBody);
            }

            throw $toEx;
        }

        return [
            'estado' => self::ESTADO_NUEVA,
            'llave' => $tcLlave,
            'ruta' => $tcRuta,
            'metodo' => $tcMetodo,
            'hash_cuerpo' => $tcHashBody,
            'id_idempotencia' => (int)$tnIdempotencia,
            'codigo_respuesta' => null,
            'respuesta' => null,
        ];
    }

    /**
     * @param array{
     *   estado:string,
     *   id_idempotencia:?int
     * } $taInicio
     * @param array<string,mixed> $laRespuesta
     */
    public function finalizar(array $taInicio, int $tnCodigoRespuesta, array $laRespuesta): void
    {
        if (($taInicio['estado'] ?? null) !== self::ESTADO_NUEVA) {
            return;
        }

        $tnIdempotencia = (int)($taInicio['id_idempotencia'] ?? 0);
        if ($tnIdempotencia <= 0) {
            return;
        }

        $tcRespuestaJson = json_encode($laRespuesta, JSON_UNESCAPED_UNICODE);
        if ($tcRespuestaJson === false) {
            $tcRespuestaJson = json_encode([
                'Ok' => false,
                'Mensaje' => 'No se pudo serializar respuesta de idempotencia'
            ], JSON_UNESCAPED_UNICODE);
        }

        $tnActualizados = DB::connection('mysqlNegocio')
            ->table('IDEMPOTENCIA')
            ->where('Idempotencia', $tnIdempotencia)
            ->update([
                'CodigoRespuesta' => $tnCodigoRespuesta,
                'Respuesta' => $tcRespuestaJson,
                'Procesado' => 1,
            ]);

        if ($tnActualizados <= 0) {
            Log::warning('idempotencia_error_guardado', [
                'Idempotencia' => $tnIdempotencia,
                'CodigoRespuesta' => $tnCodigoRespuesta,
            ]);
        }
    }

    /**
     * @param object $loRegistro
     * @return array{
     *   estado:string,
     *   llave:?string,
     *   ruta:string,
     *   metodo:string,
     *   hash_cuerpo:string,
     *   id_idempotencia:?int,
     *   codigo_respuesta:?int,
     *   respuesta:?array
     * }
     */
    private function resolverRegistroExistente($loRegistro, string $tcLlave, string $tcRuta, string $tcMetodo, string $tcHashBody): array
    {
        if ((string)$loRegistro->HashCuerpo !== $tcHashBody) {
            Log::warning('idempotencia_conflicto_cuerpo', [
                'Llave' => $tcLlave,
                'Ruta' => $tcRuta,
                'Metodo' => $tcMetodo,
            ]);

            return [
                'estado' => self::ESTADO_CONFLICTO,
                'llave' => $tcLlave,
                'ruta' => $tcRuta,
                'metodo' => $tcMetodo,
                'hash_cuerpo' => $tcHashBody,
                'id_idempotencia' => (int)$loRegistro->Idempotencia,
                'codigo_respuesta' => null,
                'respuesta' => null,
            ];
        }

        if (!(bool)$loRegistro->Procesado) {
            return [
                'estado' => self::ESTADO_EN_PROCESO,
                'llave' => $tcLlave,
                'ruta' => $tcRuta,
                'metodo' => $tcMetodo,
                'hash_cuerpo' => $tcHashBody,
                'id_idempotencia' => (int)$loRegistro->Idempotencia,
                'codigo_respuesta' => null,
                'respuesta' => null,
            ];
        }

        $laRespuesta = [];
        if (!empty($loRegistro->Respuesta)) {
            $laDecode = json_decode((string)$loRegistro->Respuesta, true);
            $laRespuesta = is_array($laDecode) ? $laDecode : [];
        }

        Log::info('idempotencia_repeticion', [
            'Llave' => $tcLlave,
            'Ruta' => $tcRuta,
            'Metodo' => $tcMetodo,
            'CodigoRespuesta' => (int)$loRegistro->CodigoRespuesta,
        ]);

        return [
            'estado' => self::ESTADO_REPETICION,
            'llave' => $tcLlave,
            'ruta' => $tcRuta,
            'metodo' => $tcMetodo,
            'hash_cuerpo' => $tcHashBody,
            'id_idempotencia' => (int)$loRegistro->Idempotencia,
            'codigo_respuesta' => (int)$loRegistro->CodigoRespuesta,
            'respuesta' => $laRespuesta,
        ];
    }
}

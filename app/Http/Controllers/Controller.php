<?php

namespace App\Http\Controllers;

use App\Support\IdempotenciaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

abstract class Controller
{
    /**
     * Envuelve operaciones POST críticas para idempotencia transversal.
     *
     * @param callable():JsonResponse $tfOperacion
     */
    protected function ejecutarIdempotente(Request $toRequest, callable $tfOperacion): JsonResponse
    {
        /** @var IdempotenciaService $toIdempotencia */
        $toIdempotencia = app(IdempotenciaService::class);

        return DB::connection('mysqlNegocio')->transaction(function () use ($toRequest, $tfOperacion, $toIdempotencia) {
            $taInicio = $toIdempotencia->iniciar($toRequest);

            if ($taInicio['estado'] === IdempotenciaService::ESTADO_FALTA_CLAVE) {
                return response()->json([
                    'Ok' => false,
                    'Mensaje' => 'Clave-Idempotencia es obligatoria',
                    'Datos' => [],
                    'Errores' => [[
                        'Codigo' => 'REQ_400',
                        'Campo' => 'Clave-Idempotencia',
                        'Detalle' => 'Debe enviar la clave de idempotencia',
                    ]],
                    'Meta' => [],
                ], 400);
            }

            if ($taInicio['estado'] === IdempotenciaService::ESTADO_CONFLICTO) {
                return response()->json([
                    'Ok' => false,
                    'Mensaje' => 'Clave-Idempotencia ya fue usada con otro cuerpo',
                    'Datos' => [],
                    'Errores' => [[
                        'Codigo' => 'IDEMPOTENCY_CONFLICT_409',
                        'Campo' => 'Clave-Idempotencia',
                        'Detalle' => 'La clave no puede reutilizarse con payload distinto',
                    ]],
                    'Meta' => [],
                ], 409);
            }

            if ($taInicio['estado'] === IdempotenciaService::ESTADO_EN_PROCESO) {
                return response()->json([
                    'Ok' => false,
                    'Mensaje' => 'Solicitud con Clave-Idempotencia en proceso, reintente',
                    'Datos' => [],
                    'Errores' => [[
                        'Codigo' => 'IDEMPOTENCY_IN_PROGRESS_409',
                        'Campo' => 'Clave-Idempotencia',
                        'Detalle' => 'Existe una solicitud en proceso con la misma clave',
                    ]],
                    'Meta' => [],
                ], 409);
            }

            if ($taInicio['estado'] === IdempotenciaService::ESTADO_REPETICION) {
                return response()->json(
                    $taInicio['respuesta'] ?? [],
                    (int)($taInicio['codigo_respuesta'] ?? 200),
                    [
                        'X-Repeticion-Idempotencia' => 'si',
                        'X-Idempotent-Replay' => 'true',
                    ]
                );
            }

            $toResponse = $tfOperacion();
            $laBody = $toResponse->getData(true);
            if (!is_array($laBody)) {
                $laBody = ['Ok' => true, 'Datos' => $laBody];
            }

            $toIdempotencia->finalizar($taInicio, $toResponse->getStatusCode(), $laBody);

            return $toResponse;
        });
    }
}

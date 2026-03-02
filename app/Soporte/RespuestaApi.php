<?php

namespace App\Soporte;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;

class RespuestaApi
{
    /**
     * @param array<string,mixed>|array<int,mixed> $taDatos
     * @param array<string,mixed> $taMeta
     * @param array<string,string> $taHeaders
     */
    public static function exito(
        string $tcMensaje,
        array $taDatos = [],
        int $tnCodigo = 200,
        array $taMeta = [],
        array $taHeaders = []
    ): JsonResponse {
        return response()->json([
            'Ok' => true,
            'Mensaje' => $tcMensaje,
            'Datos' => $taDatos,
            'Errores' => [],
            'Meta' => $taMeta,
        ], $tnCodigo, $taHeaders);
    }

    /**
     * @param array<int,array{Codigo:string,Campo:?string,Detalle:string}> $taErrores
     * @param array<string,mixed>|array<int,mixed> $taDatos
     * @param array<string,mixed> $taMeta
     */
    public static function error(
        string $tcMensaje,
        int $tnCodigo = 400,
        array $taErrores = [],
        array $taDatos = [],
        array $taMeta = []
    ): JsonResponse {
        return response()->json([
            'Ok' => false,
            'Mensaje' => $tcMensaje,
            'Datos' => $taDatos,
            'Errores' => $taErrores,
            'Meta' => $taMeta,
        ], $tnCodigo);
    }

    /**
     * @param callable(mixed):mixed|null $tfTransformador
     */
    public static function paginado(
        string $tcMensaje,
        LengthAwarePaginator $toPaginador,
        ?callable $tfTransformador = null
    ): JsonResponse {
        $laItems = $toPaginador->items();
        if ($tfTransformador !== null) {
            $laItems = array_map($tfTransformador, $laItems);
        }

        return response()->json([
            'Ok' => true,
            'Mensaje' => $tcMensaje,
            'Datos' => $laItems,
            'Errores' => [],
            'Meta' => [
                'PaginaActual' => $toPaginador->currentPage(),
                'TamanoPagina' => $toPaginador->perPage(),
                'TotalRegistros' => $toPaginador->total(),
                'TotalPaginas' => $toPaginador->lastPage(),
            ],
        ]);
    }
}

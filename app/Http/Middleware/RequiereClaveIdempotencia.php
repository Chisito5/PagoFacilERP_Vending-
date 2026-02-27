<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class RequiereClaveIdempotencia
{
    public function handle(Request $toRequest, Closure $tnNext): Response
    {
        $tcLlave = trim((string)$toRequest->header('Clave-Idempotencia', ''));
        if ($tcLlave === '') {
            Log::warning('idempotencia_clave_faltante', [
                'Ruta' => '/' . ltrim($toRequest->path(), '/'),
                'Metodo' => strtoupper((string)$toRequest->method()),
            ]);

            return response()->json([
                'Ok' => false,
                'Mensaje' => 'Clave-Idempotencia es obligatoria'
            ], 400);
        }

        return $tnNext($toRequest);
    }
}

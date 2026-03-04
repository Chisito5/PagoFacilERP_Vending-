<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class BloquearErpV1Deshabilitado
{
    public function handle(Request $toRequest, Closure $tfNext): Response
    {
        $lbHabilitado = (bool)config('erp.v1_habilitado', true);
        if ($lbHabilitado) {
            return $tfNext($toRequest);
        }

        return response()->json([
            'Ok' => false,
            'Mensaje' => 'ERP v1 temporalmente deshabilitado',
            'Datos' => [],
            'Errores' => [
                [
                    'Codigo' => 'ERP_V1_503',
                    'Campo' => null,
                    'Detalle' => 'La capa ERP v1 se encuentra deshabilitada por configuracion',
                ]
            ],
            'Meta' => [],
        ], 503);
    }
}

<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class NormalizarContratoApi
{
    public function handle(Request $toRequest, Closure $tfNext): Response
    {
        $toResponse = $tfNext($toRequest);

        if (!$toRequest->is('api/*')) {
            return $toResponse;
        }

        if (!$toResponse instanceof JsonResponse) {
            return $toResponse;
        }

        $laBody = $toResponse->getData(true);
        if (!is_array($laBody)) {
            $laBody = ['Datos' => $laBody];
        }

        $lbOk = $toResponse->getStatusCode() < 400;
        $tcMensajeDefault = $lbOk ? 'Operacion ejecutada correctamente' : 'No se pudo completar la operacion';

        $laNormalizado = [
            'Ok' => array_key_exists('Ok', $laBody) ? (bool)$laBody['Ok'] : $lbOk,
            'Mensaje' => array_key_exists('Mensaje', $laBody) ? (string)$laBody['Mensaje'] : $tcMensajeDefault,
            'Datos' => array_key_exists('Datos', $laBody) ? $laBody['Datos'] : [],
            'Errores' => [],
            'Meta' => [],
        ];

        if (array_key_exists('Errores', $laBody) && is_array($laBody['Errores'])) {
            $laNormalizado['Errores'] = array_values($laBody['Errores']);
        }

        if (array_key_exists('Meta', $laBody) && is_array($laBody['Meta'])) {
            $laNormalizado['Meta'] = $laBody['Meta'];
        }

        $toResponse->setData($laNormalizado);

        return $toResponse;
    }
}

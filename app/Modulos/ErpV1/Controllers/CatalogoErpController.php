<?php

namespace App\Modulos\ErpV1\Controllers;

use App\Http\Controllers\Controller;
use App\Modulos\ErpV1\Services\CatalogoErpService;
use App\Soporte\RespuestaApi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CatalogoErpController extends Controller
{
    public function __construct(private CatalogoErpService $toCatalogoErpService)
    {
    }

    public function Maquina(Request $toRequest): JsonResponse
    {
        $toErr = $this->validarPaginacion($toRequest);
        if ($toErr) {
            return $toErr;
        }

        return RespuestaApi::paginado(
            'Catalogo de maquinas',
            $this->toCatalogoErpService->maquinas((int)$toRequest->query('Pagina', 1), (int)$toRequest->query('TamanoPagina', 20))
        );
    }

    public function Producto(Request $toRequest): JsonResponse
    {
        $toErr = $this->validarPaginacion($toRequest);
        if ($toErr) {
            return $toErr;
        }

        return RespuestaApi::paginado(
            'Catalogo de productos',
            $this->toCatalogoErpService->productos((int)$toRequest->query('Pagina', 1), (int)$toRequest->query('TamanoPagina', 20))
        );
    }

    public function Oferta(): JsonResponse
    {
        return RespuestaApi::exito('Catalogo de ofertas', $this->toCatalogoErpService->ofertas());
    }

    public function MetodoPago(): JsonResponse
    {
        return RespuestaApi::exito('Catalogo de metodos de pago', $this->toCatalogoErpService->metodosPago());
    }

    public function EstadoTransaccion(): JsonResponse
    {
        return RespuestaApi::exito('Catalogo de estado transaccion', $this->toCatalogoErpService->estadosTransaccion());
    }

    private function validarPaginacion(Request $toRequest): ?JsonResponse
    {
        if ($toRequest->query('TamanoPagina') !== null && (int)$toRequest->query('TamanoPagina') > 200) {
            return RespuestaApi::error('Error de validacion', 422, [[
                'Codigo' => 'VAL_422',
                'Campo' => 'TamanoPagina',
                'Detalle' => 'TamanoPagina no puede ser mayor a 200'
            ]]);
        }

        return null;
    }
}


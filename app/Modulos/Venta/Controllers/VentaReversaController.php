<?php

namespace App\Modulos\Venta\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Modulos\Venta\Services\VentaReversaService;

class VentaReversaController extends Controller
{
    /**
     * SYSCOOP
     * category: Controller
     * package: App\Modulos\Venta\Controllers
     * author: Vladimir Meriles velasquez
     * fecha: 27-02-2026
     * param: Request $toRequest
     * param: VentaReversaService $toService
     * return: \Illuminate\Http\JsonResponse
     *
     * Revierte una venta y devuelve stock (para reembolso/falla).
     */
    public function Reversa(Request $toRequest, VentaReversaService $toService)
    {
        $toRequest->validate([
            'Venta' => ['required', 'integer', 'min:1'],
            'Motivo' => ['required', 'string', 'max:255'],
        ]);

        $tnVenta = (int)$toRequest->input('Venta');
        $tcMotivo = (string)$toRequest->input('Motivo');

        return $toService->Reversa($tnVenta, $tcMotivo);
    }
}
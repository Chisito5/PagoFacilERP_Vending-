<?php

namespace App\Modulos\Reserva\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Modulos\Reserva\Services\ReservaService;

class ReservaController extends Controller
{
    private ReservaService $loService;

    /**
     * SYSCOOP
     * category: Controller
     * package: App\Modulos\Reserva\Controllers
     * author: Vladimir Meriles velasquez
     * fecha: 27-02-2026
     * param: ReservaService $loService
     * return: void
     */
    public function __construct(ReservaService $loService)
    {
        $this->loService = $loService;
    }

    /**
     * SYSCOOP
     * category: Controller
     * package: App\Modulos\Reserva\Controllers
     * author: Vladimir Meriles velasquez
     * fecha: 27-02-2026
     * param: Request $toRequest
     * return: \Illuminate\Http\JsonResponse
     *
     * POST /api/reserva
     * Body: { Maquina, CodigoSeleccion, Cantidad, ExpiraSegundos }
     */
    public function Reservar(Request $toRequest)
    {
        $toRequest->validate([
            'Maquina' => ['required', 'integer', 'min:1'],
            'CodigoSeleccion' => ['required', 'string', 'max:10'],
            'Cantidad' => ['required', 'integer', 'min:1'],
            'ExpiraSegundos' => ['nullable', 'integer', 'min:30', 'max:3600'],
        ]);

        $tnMaquina = (int)$toRequest->input('Maquina');
        $tcCodigoSeleccion = (string)$toRequest->input('CodigoSeleccion');
        $tnCantidad = (int)$toRequest->input('Cantidad');
        $tnExpiraSegundos = (int)($toRequest->input('ExpiraSegundos') ?? 120);

        return $this->loService->Reservar($tnMaquina, $tcCodigoSeleccion, $tnCantidad, $tnExpiraSegundos);
    }

    /**
     * SYSCOOP
     * category: Controller
     * package: App\Modulos\Reserva\Controllers
     * author: Vladimir Meriles velasquez
     * fecha: 27-02-2026
     * param: Request $toRequest
     * return: \Illuminate\Http\JsonResponse
     *
     * POST /api/reserva/cancelar
     * Body: { Reserva } o { ReservaExterna }
     */
    public function Cancelar(Request $toRequest)
    {
        $toRequest->validate([
            'Reserva' => ['nullable', 'integer', 'min:1'],
            'ReservaExterna' => ['nullable', 'string', 'max:80'],
            'Motivo' => ['nullable', 'string', 'max:255'],
        ]);

        $tnReserva = $toRequest->filled('Reserva') ? (int)$toRequest->input('Reserva') : 0;
        $tcReservaExterna = $toRequest->filled('ReservaExterna') ? (string)$toRequest->input('ReservaExterna') : null;
        $tcMotivo = $toRequest->filled('Motivo') ? (string)$toRequest->input('Motivo') : null;

        return $this->loService->Cancelar($tnReserva, $tcReservaExterna, $tcMotivo);
    }

    /**
     * SYSCOOP
     * category: Controller
     * package: App\Modulos\Reserva\Controllers
     * author: Vladimir Meriles velasquez
     * fecha: 27-02-2026
     * param: Request $toRequest
     * return: \Illuminate\Http\JsonResponse
     *
     * POST /api/reserva/confirmar
     * Body: { Reserva } o { ReservaExterna }
     *
     * Nota: Confirmar NO descuenta disponible (ya se descontó al reservar),
     * solo baja CantidadReservada y registra venta (si quieres).
     */
    public function Confirmar(Request $toRequest)
    {
        $toRequest->validate([
            'Reserva' => ['nullable', 'integer', 'min:1'],
            'ReservaExterna' => ['nullable', 'string', 'max:80'],
        ]);

        $tnReserva = $toRequest->filled('Reserva') ? (int)$toRequest->input('Reserva') : 0;
        $tcReservaExterna = $toRequest->filled('ReservaExterna') ? (string)$toRequest->input('ReservaExterna') : null;

        return $this->loService->Confirmar($tnReserva, $tcReservaExterna);
    }
}
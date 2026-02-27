<?php

namespace App\Modulos\Reposicion\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Modulos\Reposicion\Services\ReposicionService;

class ReposicionController extends Controller
{
    private ReposicionService $loService;

    public function __construct(ReposicionService $loService)
    {
        $this->loService = $loService;
    }

    /**
     * SYSCOOP
     * category: Controller
     * package: App\Modulos\Reposicion\Controllers
     * author: Vladimir Meriles velasquez
     * fecha: 27-02-2026
     * return: \Illuminate\Http\JsonResponse
     *
     * Lista reposiciones (últimas primero).
     */
    public function Listar()
    {
        return $this->loService->Listar();
    }

    /**
     * SYSCOOP
     * category: Controller
     * package: App\Modulos\Reposicion\Controllers
     * author: Vladimir Meriles velasquez
     * fecha: 27-02-2026
     * param: int $tnMaquina
     * return: \Illuminate\Http\JsonResponse
     *
     * Lista reposiciones por máquina.
     */
    public function ListarPorMaquina(int $tnMaquina)
    {
        return $this->loService->ListarPorMaquina($tnMaquina);
    }

    /**
     * SYSCOOP
     * category: Controller
     * package: App\Modulos\Reposicion\Controllers
     * author: Vladimir Meriles velasquez
     * fecha: 27-02-2026
     * param: int $tnReposicion
     * return: \Illuminate\Http\JsonResponse
     *
     * Obtiene una reposición por ID.
     */
    public function Obtener(int $tnReposicion)
    {
        return $this->loService->Obtener($tnReposicion);
    }

    /**
     * SYSCOOP
     * category: Controller
     * package: App\Modulos\Reposicion\Controllers
     * author: Vladimir Meriles velasquez
     * fecha: 27-02-2026
     * param: Request $toRequest
     * return: \Illuminate\Http\JsonResponse
     *
     * Recarga stock por (Maquina + CodigoSeleccion).
     */
    public function RecargarPorSeleccion(Request $toRequest)
    {
        $toRequest->validate([
            'Maquina' => ['required', 'integer', 'min:1'],
            'CodigoSeleccion' => ['required', 'string', 'max:10'],
            'Cantidad' => ['required', 'integer', 'min:1'],

            // requerido por FK en REPOSICION (se valida también en el Service)
            'UsuarioOperador' => ['required', 'integer', 'min:1'],

            // opcionales (si no los mandas, se usa la existencia actual)
            'ProductoEmpresa' => ['nullable', 'integer', 'min:1'],
            'Lote' => ['nullable', 'integer', 'min:1'],
            'Observacion' => ['nullable', 'string', 'max:255'],
        ]);

        $tnMaquina = (int)$toRequest->input('Maquina');
        $tcCodigoSeleccion = (string)$toRequest->input('CodigoSeleccion');
        $tnCantidad = (int)$toRequest->input('Cantidad');

        $tnUsuarioOperador = (int)$toRequest->input('UsuarioOperador');

        $tnProductoEmpresa = $toRequest->filled('ProductoEmpresa') ? (int)$toRequest->input('ProductoEmpresa') : null;
        $tnLote = $toRequest->filled('Lote') ? (int)$toRequest->input('Lote') : null;
        $tcObservacion = $toRequest->filled('Observacion') ? (string)$toRequest->input('Observacion') : null;

        return $this->loService->RecargarPorSeleccion(
            $tnMaquina,
            $tcCodigoSeleccion,
            $tnCantidad,
            $tnUsuarioOperador,
            $tnProductoEmpresa,
            $tnLote,
            $tcObservacion
        );
    }
}
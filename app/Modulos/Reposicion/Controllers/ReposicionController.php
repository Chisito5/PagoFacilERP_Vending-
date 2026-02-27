<?php

namespace App\Modulos\Reposicion\Controllers;

use App\Http\Controllers\Controller;
use App\Modulos\Reposicion\Services\ReposicionService;
use Illuminate\Http\Request;

class ReposicionController extends Controller
{
    private ReposicionService $loService;

    public function __construct(ReposicionService $loService)
    {
        $this->loService = $loService;
    }

    public function Listar()
    {
        return $this->loService->Listar();
    }

    public function ListarPorMaquina(int $tnMaquina)
    {
        return $this->loService->ListarPorMaquina($tnMaquina);
    }

    public function Obtener(int $tnReposicion)
    {
        return $this->loService->Obtener($tnReposicion);
    }

    public function RecargarPorSeleccion(Request $toRequest)
    {
        return $this->ejecutarIdempotente($toRequest, function () use ($toRequest) {
            $toRequest->validate([
                'Maquina' => ['required', 'integer', 'min:1'],
                'CodigoSeleccion' => ['required', 'string', 'max:10'],
                'Cantidad' => ['required', 'integer', 'min:1'],
                'UsuarioOperador' => ['required', 'integer', 'min:1'],
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
        });
    }
}

<?php

namespace App\Modulos\Reposicion\Controllers;

use App\Http\Controllers\Controller;
use App\Modulos\Reposicion\Services\ReposicionService;
use App\Soporte\RespuestaApi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReposicionController extends Controller
{
    public function __construct(private ReposicionService $toService)
    {
    }

    public function Listar(Request $toRequest): JsonResponse
    {
        $tnPagina = (int)$toRequest->query('Pagina', 1);
        $tnTamanoPagina = (int)$toRequest->query('TamanoPagina', 20);

        $toPaginador = $this->toService->Listar($tnPagina, $tnTamanoPagina);

        return RespuestaApi::paginado('Listado de reposiciones', $toPaginador);
    }

    public function ListarPorMaquina(int $tnMaquina, Request $toRequest): JsonResponse
    {
        $tnPagina = (int)$toRequest->query('Pagina', 1);
        $tnTamanoPagina = (int)$toRequest->query('TamanoPagina', 20);

        $toPaginador = $this->toService->ListarPorMaquina($tnMaquina, $tnPagina, $tnTamanoPagina);

        return RespuestaApi::paginado('Listado de reposiciones por maquina', $toPaginador);
    }

    public function Obtener(int $tnReposicion): JsonResponse
    {
        $laDatos = $this->toService->Obtener($tnReposicion);
        if (!$laDatos) {
            return RespuestaApi::error('Reposicion no encontrada', 404, [
                ['Codigo' => 'REPO_404', 'Campo' => 'Reposicion', 'Detalle' => 'No existe la reposicion solicitada']
            ]);
        }

        return RespuestaApi::exito('Reposicion encontrada', $laDatos);
    }

    public function Prevalidar(Request $toRequest): JsonResponse
    {
        $toRequest->validate([
            'Maquina' => ['required', 'integer', 'min:1'],
            'CodigoSeleccion' => ['required', 'string', 'max:10'],
            'Cantidad' => ['required', 'integer', 'min:1'],
            'UsuarioOperador' => ['required', 'integer', 'min:1'],
            'ProductoEmpresa' => ['nullable', 'integer', 'min:1'],
            'Lote' => ['nullable', 'integer', 'min:1'],
        ]);

        return $this->toService->PrevalidarPorSeleccion(
            (int)$toRequest->input('Maquina'),
            (string)$toRequest->input('CodigoSeleccion'),
            (int)$toRequest->input('Cantidad'),
            (int)$toRequest->input('UsuarioOperador'),
            $toRequest->filled('ProductoEmpresa') ? (int)$toRequest->input('ProductoEmpresa') : null,
            $toRequest->filled('Lote') ? (int)$toRequest->input('Lote') : null
        );
    }

    public function RecargarPorSeleccion(Request $toRequest): JsonResponse
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

            return $this->toService->RecargarPorSeleccion(
                (int)$toRequest->input('Maquina'),
                (string)$toRequest->input('CodigoSeleccion'),
                (int)$toRequest->input('Cantidad'),
                (int)$toRequest->input('UsuarioOperador'),
                $toRequest->filled('ProductoEmpresa') ? (int)$toRequest->input('ProductoEmpresa') : null,
                $toRequest->filled('Lote') ? (int)$toRequest->input('Lote') : null,
                $toRequest->filled('Observacion') ? (string)$toRequest->input('Observacion') : null
            );
        });
    }
}

<?php

namespace App\Modulos\Reporte\Controllers;

use App\Http\Controllers\Controller;
use App\Modulos\Reporte\Services\ReporteService;
use App\Soporte\AutorizacionNegocioService;
use App\Soporte\RespuestaApi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ReporteController extends Controller
{
    public function __construct(
        private ReporteService $toService,
        private AutorizacionNegocioService $toAutorizacion
    ) {
    }

    public function Generar(Request $toRequest): JsonResponse
    {
        $toRequest->validate([
            'TipoReporte' => ['required', 'string', 'max:80'],
            'Formato' => ['required', 'string', 'max:10'],
            'Filtros' => ['nullable', 'array'],
            'Empresa' => ['nullable', 'integer', 'min:1'],
        ]);

        $tnUsuario = (int)(auth()->id() ?? 0);
        $tnEmpresa = $toRequest->filled('Empresa') ? (int)$toRequest->input('Empresa') : null;

        $la = $this->toService->solicitar(
            (string)$toRequest->input('TipoReporte'),
            (string)$toRequest->input('Formato'),
            (array)($toRequest->input('Filtros') ?? []),
            $tnEmpresa,
            $tnUsuario
        );

        if (($la['Estado'] ?? '') === 'FORMATO_INVALIDO') {
            return RespuestaApi::error('Formato de reporte invalido', 400);
        }

        return RespuestaApi::exito('Reporte encolado correctamente', $la['Datos'] ?? [], 202);
    }

    public function Listar(Request $toRequest): JsonResponse
    {
        $tnUsuario = (int)(auth()->id() ?? 0);
        $lbGlobal = $this->toAutorizacion->esDueno($tnUsuario);

        $to = $this->toService->listar(
            $tnUsuario,
            $lbGlobal,
            (int)$toRequest->query('Pagina', 1),
            (int)$toRequest->query('TamanoPagina', 20)
        );

        return RespuestaApi::paginado('Listado de reportes generados', $to);
    }

    public function Obtener(int $tnReporte): JsonResponse
    {
        $tnUsuario = (int)(auth()->id() ?? 0);
        $lbGlobal = $this->toAutorizacion->esDueno($tnUsuario);

        $la = $this->toService->obtener($tnReporte);
        if (!$la) {
            return RespuestaApi::error('Reporte no encontrado', 404);
        }

        if (!$lbGlobal && (int)$la['UsuarioSolicitante'] !== $tnUsuario) {
            return RespuestaApi::error('No autorizado para consultar este reporte', 403);
        }

        return RespuestaApi::exito('Detalle de reporte', $la);
    }

    public function Descargar(int $tnReporte)
    {
        $tnUsuario = (int)(auth()->id() ?? 0);
        $lbGlobal = $this->toAutorizacion->esDueno($tnUsuario);

        $la = $this->toService->obtenerRutaDescarga($tnReporte, $tnUsuario, $lbGlobal);
        if (($la['Estado'] ?? '') === 'NO_ENCONTRADO') {
            return RespuestaApi::error('Reporte no encontrado', 404);
        }
        if (($la['Estado'] ?? '') === 'NO_AUTORIZADO') {
            return RespuestaApi::error('No autorizado para descargar este reporte', 403);
        }
        if (($la['Estado'] ?? '') === 'NO_DISPONIBLE') {
            return RespuestaApi::error('Reporte aun no disponible para descarga', 409);
        }
        if (($la['Estado'] ?? '') === 'EXPIRADO') {
            return RespuestaApi::error('Reporte expirado', 410);
        }

        return Storage::disk('public')->download((string)$la['Ruta'], (string)$la['Nombre']);
    }
}

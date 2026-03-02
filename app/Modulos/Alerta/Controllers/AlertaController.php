<?php

namespace App\Modulos\Alerta\Controllers;

use App\Http\Controllers\Controller;
use App\Modulos\Alerta\Services\AlertaService;
use App\Soporte\RespuestaApi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AlertaController extends Controller
{
    public function __construct(private AlertaService $toService)
    {
    }

    public function ListarReglas(Request $toRequest): JsonResponse
    {
        $to = $this->toService->listarReglas(
            $toRequest->filled('Empresa') ? (int)$toRequest->query('Empresa') : null,
            $toRequest->filled('Estado') ? (int)$toRequest->query('Estado') : null,
            (int)$toRequest->query('Pagina', 1),
            (int)$toRequest->query('TamanoPagina', 20)
        );

        return RespuestaApi::paginado('Listado de reglas de alerta', $to);
    }

    public function ObtenerRegla(int $tnRegla): JsonResponse
    {
        $la = $this->toService->obtenerRegla($tnRegla);
        if (!$la) {
            return RespuestaApi::error('Regla de alerta no encontrada', 404);
        }
        return RespuestaApi::exito('Regla de alerta encontrada', $la);
    }

    public function CrearRegla(Request $toRequest): JsonResponse
    {
        $toRequest->validate([
            'Empresa' => ['nullable', 'integer', 'min:1'],
            'Maquina' => ['nullable', 'integer', 'min:1'],
            'Celda' => ['nullable', 'integer', 'min:1'],
            'TipoAlerta' => ['nullable', 'integer', 'min:1'],
            'TipoTelemetria' => ['nullable', 'integer', 'min:1'],
            'TipoRegla' => ['required', 'string', 'max:40'],
            'UmbralMinimo' => ['nullable', 'numeric'],
            'UmbralMaximo' => ['nullable', 'numeric'],
            'Prioridad' => ['nullable', 'integer', 'min:1', 'max:5'],
            'MensajeRegla' => ['nullable', 'string', 'max:255'],
            'Estado' => ['nullable', 'integer', 'min:1'],
            'Motivo' => ['nullable', 'string', 'max:255'],
        ]);

        return RespuestaApi::exito('Regla de alerta creada correctamente', $this->toService->crearRegla($toRequest->all(), (int)(auth()->id() ?? 0)), 201);
    }

    public function ActualizarRegla(Request $toRequest, int $tnRegla): JsonResponse
    {
        $toRequest->validate([
            'Version' => ['required', 'string', 'max:30'],
            'Empresa' => ['nullable', 'integer', 'min:1'],
            'Maquina' => ['nullable', 'integer', 'min:1'],
            'Celda' => ['nullable', 'integer', 'min:1'],
            'TipoAlerta' => ['nullable', 'integer', 'min:1'],
            'TipoTelemetria' => ['nullable', 'integer', 'min:1'],
            'TipoRegla' => ['required', 'string', 'max:40'],
            'UmbralMinimo' => ['nullable', 'numeric'],
            'UmbralMaximo' => ['nullable', 'numeric'],
            'Prioridad' => ['required', 'integer', 'min:1', 'max:5'],
            'MensajeRegla' => ['nullable', 'string', 'max:255'],
            'Estado' => ['required', 'integer', 'min:1'],
            'Motivo' => ['nullable', 'string', 'max:255'],
        ]);

        return $this->resolverMutacion($this->toService->actualizarRegla($tnRegla, $toRequest->all(), (string)$toRequest->input('Version'), (int)(auth()->id() ?? 0), false), 'Regla de alerta actualizada correctamente');
    }

    public function ActualizarReglaParcial(Request $toRequest, int $tnRegla): JsonResponse
    {
        $toRequest->validate([
            'Version' => ['required', 'string', 'max:30'],
            'Empresa' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'Maquina' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'Celda' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'TipoAlerta' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'TipoTelemetria' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'TipoRegla' => ['sometimes', 'string', 'max:40'],
            'UmbralMinimo' => ['sometimes', 'nullable', 'numeric'],
            'UmbralMaximo' => ['sometimes', 'nullable', 'numeric'],
            'Prioridad' => ['sometimes', 'integer', 'min:1', 'max:5'],
            'MensajeRegla' => ['sometimes', 'nullable', 'string', 'max:255'],
            'Estado' => ['sometimes', 'integer', 'min:1'],
            'Motivo' => ['nullable', 'string', 'max:255'],
        ]);

        return $this->resolverMutacion($this->toService->actualizarRegla($tnRegla, $toRequest->all(), (string)$toRequest->input('Version'), (int)(auth()->id() ?? 0), true), 'Regla de alerta actualizada correctamente');
    }

    public function EliminarRegla(Request $toRequest, int $tnRegla): JsonResponse
    {
        $toRequest->validate(['Version' => ['required', 'string', 'max:30'], 'Motivo' => ['nullable', 'string', 'max:255']]);
        return $this->resolverMutacion($this->toService->eliminarRegla($tnRegla, (string)$toRequest->input('Version'), (int)(auth()->id() ?? 0), $toRequest->input('Motivo')), 'Regla de alerta eliminada logicamente');
    }

    public function ListarAlertas(Request $toRequest): JsonResponse
    {
        $to = $this->toService->listarAlertas(
            $toRequest->filled('Maquina') ? (int)$toRequest->query('Maquina') : null,
            $toRequest->filled('Estado') ? (int)$toRequest->query('Estado') : null,
            (int)$toRequest->query('Pagina', 1),
            (int)$toRequest->query('TamanoPagina', 20)
        );

        return RespuestaApi::paginado('Listado de alertas', $to);
    }

    public function ObtenerAlerta(int $tnAlerta): JsonResponse
    {
        $la = $this->toService->obtenerAlerta($tnAlerta);
        if (!$la) {
            return RespuestaApi::error('Alerta no encontrada', 404);
        }
        return RespuestaApi::exito('Alerta encontrada', $la);
    }

    public function AtenderAlerta(Request $toRequest, int $tnAlerta): JsonResponse
    {
        $toRequest->validate(['Motivo' => ['nullable', 'string', 'max:255']]);
        return $this->resolverMutacion($this->toService->atenderAlerta($tnAlerta, (int)(auth()->id() ?? 0), $toRequest->input('Motivo')), 'Alerta atendida correctamente', 'Alerta ya atendida');
    }

    public function EscalarAlerta(Request $toRequest, int $tnAlerta): JsonResponse
    {
        $toRequest->validate(['Motivo' => ['nullable', 'string', 'max:255']]);
        return $this->resolverMutacion($this->toService->escalarAlerta($tnAlerta, (int)(auth()->id() ?? 0), $toRequest->input('Motivo')), 'Alerta escalada correctamente', 'Alerta ya escalada');
    }

    public function CerrarAlerta(Request $toRequest, int $tnAlerta): JsonResponse
    {
        $toRequest->validate(['Motivo' => ['nullable', 'string', 'max:255']]);
        return $this->resolverMutacion($this->toService->cerrarAlerta($tnAlerta, (int)(auth()->id() ?? 0), $toRequest->input('Motivo')), 'Alerta cerrada correctamente', 'Alerta ya cerrada');
    }

    /** @param array<string,mixed> $la */
    private function resolverMutacion(array $la, string $tcMensajeOk, string $tcMensajeIdempotente = 'Operacion ya aplicada'): JsonResponse
    {
        if (($la['Estado'] ?? '') === 'NO_ENCONTRADO') {
            return RespuestaApi::error('Registro no encontrado', 404);
        }
        if (($la['Estado'] ?? '') === 'CONFLICTO_VERSION') {
            return RespuestaApi::error('Conflicto de version', 409, [], $la['Actual'] ?? []);
        }
        if (($la['Estado'] ?? '') === 'IDEMPOTENTE') {
            return RespuestaApi::exito($tcMensajeIdempotente, $la['Datos'] ?? []);
        }
        if (($la['Estado'] ?? '') === 'TRANSICION_INVALIDA') {
            return RespuestaApi::error('Transicion de estado invalida', 409, [], $la['Datos'] ?? []);
        }

        return RespuestaApi::exito($tcMensajeOk, $la['Datos'] ?? []);
    }
}

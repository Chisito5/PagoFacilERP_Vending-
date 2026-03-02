<?php

namespace App\Modulos\Maquina\Controllers;

use App\Http\Controllers\Controller;
use App\Modulos\Maquina\Services\MaquinaService;
use App\Soporte\ValidacionAprobacionService;
use App\Soporte\RespuestaApi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Http\Request;

class MaquinaController extends Controller
{
    public function __construct(private MaquinaService $toMaquinaService)
    {
    }

    public function Listar(Request $toRequest): JsonResponse
    {
        $tnEmpresa = $toRequest->filled('Empresa') ? (int)$toRequest->query('Empresa') : null;
        $tnEstado = $toRequest->filled('Estado') ? (int)$toRequest->query('Estado') : null;
        $tcBusqueda = $toRequest->filled('Busqueda') ? (string)$toRequest->query('Busqueda') : null;
        $tnPagina = (int)$toRequest->query('Pagina', 1);
        $tnTamanoPagina = (int)$toRequest->query('TamanoPagina', 20);

        $toPaginador = $this->toMaquinaService->Listar($tnEmpresa, $tnEstado, $tcBusqueda, $tnPagina, $tnTamanoPagina);

        return RespuestaApi::paginado('Listado de maquinas', $toPaginador);
    }

    public function Obtener(int $IdMaquina): JsonResponse
    {
        $laDatos = $this->toMaquinaService->Obtener($IdMaquina);
        if (!$laDatos) {
            return RespuestaApi::error('Maquina no encontrada', 404);
        }

        return RespuestaApi::exito('Maquina encontrada', $laDatos);
    }

    public function ListarCeldas(int $IdMaquina, Request $toRequest): JsonResponse
    {
        $tnPagina = (int)$toRequest->query('Pagina', 1);
        $tnTamanoPagina = (int)$toRequest->query('TamanoPagina', 50);

        $toPaginador = $this->toMaquinaService->ListarCeldas($IdMaquina, $tnPagina, $tnTamanoPagina);

        return RespuestaApi::paginado('Listado de celdas por maquina', $toPaginador);
    }

    public function Crear(Request $toRequest): JsonResponse
    {
        $toRequest->validate([
            'CodigoMaquina' => ['required', 'string', 'max:40'],
            'NumeroSerie' => ['nullable', 'string', 'max:80'],
            'Marca' => ['nullable', 'string', 'max:80'],
            'Modelo' => ['nullable', 'string', 'max:80'],
            'IdentificadorConexion' => ['required', 'string', 'max:120'],
            'UbicacionActual' => ['nullable', 'integer', 'min:1'],
            'FilasMatriz' => ['nullable', 'integer', 'min:1', 'max:20'],
            'ColumnasMatriz' => ['nullable', 'integer', 'min:1', 'max:20'],
            'Estado' => ['nullable', 'integer', 'min:1'],
            'Motivo' => ['nullable', 'string', 'max:255'],
        ]);

        $la = $this->toMaquinaService->Crear($toRequest->all(), (int)(auth()->id() ?? 0));

        return RespuestaApi::exito('Maquina creada correctamente', $la, 201);
    }

    public function Actualizar(Request $toRequest, int $IdMaquina): JsonResponse
    {
        $toRequest->validate([
            'Version' => ['required', 'string', 'max:30'],
            'CodigoMaquina' => ['required', 'string', 'max:40'],
            'NumeroSerie' => ['nullable', 'string', 'max:80'],
            'Marca' => ['nullable', 'string', 'max:80'],
            'Modelo' => ['nullable', 'string', 'max:80'],
            'IdentificadorConexion' => ['required', 'string', 'max:120'],
            'UbicacionActual' => ['nullable', 'integer', 'min:1'],
            'FilasMatriz' => ['required', 'integer', 'min:1', 'max:20'],
            'ColumnasMatriz' => ['required', 'integer', 'min:1', 'max:20'],
            'Estado' => ['required', 'integer', 'min:1'],
            'Motivo' => ['nullable', 'string', 'max:255'],
            'Aprobacion' => ['required', 'integer', 'min:1'],
        ]);

        $toError = $this->validarAprobacionCambioEstado($toRequest, $IdMaquina);
        if ($toError !== null) {
            return $toError;
        }

        return $this->resolverActualizacion($toRequest, $IdMaquina, false);
    }

    public function ActualizarParcial(Request $toRequest, int $IdMaquina): JsonResponse
    {
        $toRequest->validate([
            'Version' => ['required', 'string', 'max:30'],
            'CodigoMaquina' => ['sometimes', 'string', 'max:40'],
            'NumeroSerie' => ['sometimes', 'nullable', 'string', 'max:80'],
            'Marca' => ['sometimes', 'nullable', 'string', 'max:80'],
            'Modelo' => ['sometimes', 'nullable', 'string', 'max:80'],
            'IdentificadorConexion' => ['sometimes', 'string', 'max:120'],
            'UbicacionActual' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'FilasMatriz' => ['sometimes', 'integer', 'min:1', 'max:20'],
            'ColumnasMatriz' => ['sometimes', 'integer', 'min:1', 'max:20'],
            'Estado' => ['sometimes', 'integer', 'min:1'],
            'Motivo' => ['nullable', 'string', 'max:255'],
            'Aprobacion' => ['sometimes', 'integer', 'min:1'],
        ]);

        $toError = $this->validarAprobacionCambioEstado($toRequest, $IdMaquina);
        if ($toError !== null) {
            return $toError;
        }

        return $this->resolverActualizacion($toRequest, $IdMaquina, true);
    }

    public function EliminarLogico(Request $toRequest, int $IdMaquina): JsonResponse
    {
        $toRequest->validate([
            'Version' => ['required', 'string', 'max:30'],
            'Motivo' => ['nullable', 'string', 'max:255'],
        ]);

        $la = $this->toMaquinaService->EliminarLogico(
            $IdMaquina,
            (string)$toRequest->input('Version'),
            (int)(auth()->id() ?? 0),
            $toRequest->filled('Motivo') ? (string)$toRequest->input('Motivo') : null
        );

        if ($la['Estado'] === 'NO_ENCONTRADO') {
            return RespuestaApi::error('Maquina no encontrada', 404);
        }

        if ($la['Estado'] === 'CONFLICTO_VERSION') {
            return RespuestaApi::error('Conflicto de version', 409, [
                ['Codigo' => 'VERSION_409', 'Campo' => 'Version', 'Detalle' => 'La version enviada no coincide']
            ], $la['Actual'] ?? []);
        }

        return RespuestaApi::exito('Maquina eliminada logicamente', $la['Datos']);
    }

    public function ListarFotos(int $IdMaquina): JsonResponse
    {
        $la = $this->toMaquinaService->ListarFotos($IdMaquina);
        if ($la['Estado'] === 'NO_ENCONTRADO') {
            return RespuestaApi::error('Maquina no encontrada', 404);
        }

        return RespuestaApi::exito('Fotos de maquina obtenidas correctamente', $la['Datos'] ?? []);
    }

    public function SubirFotosLote(Request $toRequest, int $IdMaquina): JsonResponse
    {
        $toRequest->validate([
            'Fotos' => ['nullable', 'array'],
            'Fotos.*.Url' => ['nullable', 'url', 'max:500'],
            'Fotos.*.TipoFoto' => ['nullable', 'string', 'max:40'],
            'Fotos.*.Orden' => ['nullable', 'integer', 'min:1'],
            'Fotos.*.Observacion' => ['nullable', 'string', 'max:255'],
            'Archivos' => ['nullable', 'array'],
            'Archivos.*' => ['file', 'max:10240'],
            'Motivo' => ['nullable', 'string', 'max:255'],
        ]);

        $laArchivos = [];
        if ($toRequest->hasFile('Archivos')) {
            /** @var array<int,UploadedFile> $laArchivos */
            $laArchivos = $toRequest->file('Archivos');
        } elseif ($toRequest->hasFile('Archivo')) {
            /** @var UploadedFile $toArchivo */
            $toArchivo = $toRequest->file('Archivo');
            $laArchivos = [$toArchivo];
        }

        $la = $this->toMaquinaService->SubirFotosLote(
            $IdMaquina,
            (array)$toRequest->input('Fotos', []),
            $laArchivos,
            (int)(auth()->id() ?? 0),
            $toRequest->filled('Motivo') ? (string)$toRequest->input('Motivo') : null
        );

        if ($la['Estado'] === 'NO_ENCONTRADO') {
            return RespuestaApi::error('Maquina no encontrada', 404);
        }
        if ($la['Estado'] === 'MINIMO_FOTOS') {
            return RespuestaApi::error('Debe registrar al menos 3 fotos de la maquina', 409, [
                ['Codigo' => 'MAQUINA_FOTO_MINIMO', 'Campo' => 'Fotos', 'Detalle' => 'La maquina debe tener minimo 3 fotos activas']
            ], [
                'TotalActual' => (int)($la['TotalActual'] ?? 0),
                'TotalPosterior' => (int)($la['TotalPosterior'] ?? 0),
                'MinimoRequerido' => 3,
            ]);
        }

        return RespuestaApi::exito('Fotos de maquina registradas correctamente', $la['Datos'] ?? [], 201);
    }

    public function EliminarFoto(Request $toRequest, int $IdMaquina, int $IdMaquinaFoto): JsonResponse
    {
        $toRequest->validate([
            'Version' => ['required', 'string', 'max:30'],
            'Motivo' => ['nullable', 'string', 'max:255'],
        ]);

        $la = $this->toMaquinaService->EliminarFoto(
            $IdMaquina,
            $IdMaquinaFoto,
            (string)$toRequest->input('Version'),
            (int)(auth()->id() ?? 0),
            $toRequest->filled('Motivo') ? (string)$toRequest->input('Motivo') : null
        );

        if ($la['Estado'] === 'NO_ENCONTRADO') {
            return RespuestaApi::error('Foto de maquina no encontrada', 404);
        }
        if ($la['Estado'] === 'CONFLICTO_VERSION') {
            return RespuestaApi::error('Conflicto de version', 409, [
                ['Codigo' => 'VERSION_409', 'Campo' => 'Version', 'Detalle' => 'La version enviada no coincide']
            ], $la['Actual'] ?? []);
        }
        if ($la['Estado'] === 'MINIMO_FOTOS') {
            return RespuestaApi::error('No se puede eliminar: minimo 3 fotos activas por maquina', 409, [
                ['Codigo' => 'MAQUINA_FOTO_MINIMO', 'Campo' => 'Maquina', 'Detalle' => 'Debe mantener minimo 3 fotos activas']
            ], [
                'TotalActual' => (int)($la['TotalActual'] ?? 0),
                'MinimoRequerido' => 3,
            ]);
        }

        return RespuestaApi::exito('Foto de maquina eliminada logicamente', $la['Datos'] ?? []);
    }

    private function resolverActualizacion(Request $toRequest, int $tnMaquina, bool $lbParcial): JsonResponse
    {
        $la = $this->toMaquinaService->Actualizar(
            $tnMaquina,
            $toRequest->all(),
            (string)$toRequest->input('Version'),
            (int)(auth()->id() ?? 0),
            $lbParcial
        );

        if ($la['Estado'] === 'NO_ENCONTRADO') {
            return RespuestaApi::error('Maquina no encontrada', 404);
        }

        if ($la['Estado'] === 'CONFLICTO_VERSION') {
            return RespuestaApi::error('Conflicto de version', 409, [
                ['Codigo' => 'VERSION_409', 'Campo' => 'Version', 'Detalle' => 'La version enviada no coincide']
            ], $la['Actual'] ?? []);
        }

        return RespuestaApi::exito('Maquina actualizada correctamente', $la['Datos']);
    }

    private function validarAprobacionCambioEstado(Request $toRequest, int $tnMaquina): ?JsonResponse
    {
        if (!$toRequest->has('Estado')) {
            return null;
        }

        if (!$toRequest->filled('Aprobacion')) {
            return RespuestaApi::error('Se requiere aprobacion para cambiar estado de maquina', 409, [
                ['Codigo' => 'APROBACION_REQUERIDA', 'Campo' => 'Aprobacion', 'Detalle' => 'Debe enviar una aprobacion previa']
            ]);
        }

        /** @var ValidacionAprobacionService $toValidador */
        $toValidador = app(ValidacionAprobacionService::class);
        $lbOk = $toValidador->estaAprobadaParaEntidad((int)$toRequest->input('Aprobacion'), 'MAQUINA', $tnMaquina);
        if (!$lbOk) {
            return RespuestaApi::error('Aprobacion invalida para maquina', 409, [
                ['Codigo' => 'APROBACION_INVALIDA', 'Campo' => 'Aprobacion', 'Detalle' => 'La aprobacion no corresponde a esta entidad']
            ]);
        }

        return null;
    }
}

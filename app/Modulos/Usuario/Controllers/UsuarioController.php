<?php

namespace App\Modulos\Usuario\Controllers;

use App\Http\Controllers\Controller;
use App\Modulos\Usuario\Services\UsuarioService;
use App\Soporte\AutorizacionNegocioService;
use App\Soporte\RespuestaApi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UsuarioController extends Controller
{
    public function __construct(
        private UsuarioService $toService,
        private AutorizacionNegocioService $toAutorizacion
    ) {
    }

    public function Listar(Request $toRequest): JsonResponse
    {
        $tnUsuarioSesion = (int)(auth()->id() ?? 0);
        $tnEmpresa = $toRequest->filled('Empresa') ? (int)$toRequest->query('Empresa') : null;
        $tnEstado = $toRequest->filled('Estado') ? (int)$toRequest->query('Estado') : null;
        $tcBusqueda = $toRequest->filled('Busqueda') ? (string)$toRequest->query('Busqueda') : null;
        $tnPagina = (int)$toRequest->query('Pagina', 1);
        $tnTamanoPagina = (int)$toRequest->query('TamanoPagina', 20);

        if ($tnEmpresa !== null && !$this->toAutorizacion->puedeGestionarEmpresa($tnUsuarioSesion, $tnEmpresa) && !$this->toAutorizacion->esDueno($tnUsuarioSesion)) {
            return RespuestaApi::error('No autorizado para listar usuarios de esta empresa', 403);
        }

        $toPaginador = $this->toService->Listar($tnEmpresa, $tnEstado, $tcBusqueda, $tnPagina, $tnTamanoPagina);

        return RespuestaApi::paginado('Listado de usuarios', $toPaginador);
    }

    public function Obtener(int $tnUsuario): JsonResponse
    {
        $tnUsuarioSesion = (int)(auth()->id() ?? 0);
        $la = $this->toService->Obtener($tnUsuario);
        if (!$la) {
            return RespuestaApi::error('Usuario no encontrado', 404);
        }

        if (!$this->toAutorizacion->puedeGestionarUsuario($tnUsuarioSesion, (int)$la['Empresa']) && $tnUsuarioSesion !== $tnUsuario) {
            return RespuestaApi::error('No autorizado para consultar este usuario', 403);
        }

        return RespuestaApi::exito('Usuario encontrado', $la);
    }

    public function Crear(Request $toRequest): JsonResponse
    {
        $toRequest->validate([
            'Empresa' => ['required', 'integer', 'min:1'],
            'NombreUsuario' => ['required', 'string', 'max:80'],
            'Clave' => ['required', 'string', 'min:6', 'max:80'],
            'Nombres' => ['required', 'string', 'max:120'],
            'Apellidos' => ['nullable', 'string', 'max:120'],
            'Correo' => ['nullable', 'string', 'email', 'max:120'],
            'Telefono' => ['nullable', 'string', 'max:30'],
            'DocumentoIdentidad' => ['nullable', 'string', 'max:40'],
            'Estado' => ['nullable', 'integer', 'min:1'],
            'Roles' => ['nullable', 'array'],
            'Roles.*' => ['integer', 'min:1'],
            'Motivo' => ['nullable', 'string', 'max:255'],
        ]);

        $tnUsuarioSesion = (int)(auth()->id() ?? 0);
        $tnEmpresa = (int)$toRequest->input('Empresa');

        if (!$this->toAutorizacion->puedeGestionarUsuario($tnUsuarioSesion, $tnEmpresa)) {
            return RespuestaApi::error('No autorizado para crear usuarios en esta empresa', 403);
        }

        $la = $this->toService->Crear($toRequest->all(), $tnUsuarioSesion);

        return RespuestaApi::exito('Usuario creado correctamente', $la, 201);
    }

    public function Actualizar(Request $toRequest, int $tnUsuario): JsonResponse
    {
        $toRequest->validate([
            'Version' => ['required', 'string', 'max:30'],
            'Empresa' => ['required', 'integer', 'min:1'],
            'NombreUsuario' => ['required', 'string', 'max:80'],
            'Nombres' => ['required', 'string', 'max:120'],
            'Apellidos' => ['nullable', 'string', 'max:120'],
            'Correo' => ['nullable', 'string', 'email', 'max:120'],
            'Telefono' => ['nullable', 'string', 'max:30'],
            'DocumentoIdentidad' => ['nullable', 'string', 'max:40'],
            'Clave' => ['nullable', 'string', 'min:6', 'max:80'],
            'Estado' => ['required', 'integer', 'min:1'],
            'Roles' => ['nullable', 'array'],
            'Roles.*' => ['integer', 'min:1'],
            'Motivo' => ['nullable', 'string', 'max:255'],
        ]);

        return $this->resolverActualizacion($toRequest, $tnUsuario, false);
    }

    public function ActualizarParcial(Request $toRequest, int $tnUsuario): JsonResponse
    {
        $toRequest->validate([
            'Version' => ['required', 'string', 'max:30'],
            'Empresa' => ['sometimes', 'integer', 'min:1'],
            'NombreUsuario' => ['sometimes', 'string', 'max:80'],
            'Nombres' => ['sometimes', 'string', 'max:120'],
            'Apellidos' => ['sometimes', 'nullable', 'string', 'max:120'],
            'Correo' => ['sometimes', 'nullable', 'string', 'email', 'max:120'],
            'Telefono' => ['sometimes', 'nullable', 'string', 'max:30'],
            'DocumentoIdentidad' => ['sometimes', 'nullable', 'string', 'max:40'],
            'Clave' => ['sometimes', 'nullable', 'string', 'min:6', 'max:80'],
            'Estado' => ['sometimes', 'integer', 'min:1'],
            'Roles' => ['sometimes', 'array'],
            'Roles.*' => ['integer', 'min:1'],
            'Motivo' => ['nullable', 'string', 'max:255'],
        ]);

        return $this->resolverActualizacion($toRequest, $tnUsuario, true);
    }

    public function EliminarLogico(Request $toRequest, int $tnUsuario): JsonResponse
    {
        $toRequest->validate([
            'Version' => ['required', 'string', 'max:30'],
            'Motivo' => ['nullable', 'string', 'max:255'],
        ]);

        $tnUsuarioSesion = (int)(auth()->id() ?? 0);
        $laActual = $this->toService->Obtener($tnUsuario);
        if (!$laActual) {
            return RespuestaApi::error('Usuario no encontrado', 404);
        }

        if (!$this->toAutorizacion->puedeGestionarUsuario($tnUsuarioSesion, (int)$laActual['Empresa'])) {
            return RespuestaApi::error('No autorizado para eliminar este usuario', 403);
        }

        $la = $this->toService->EliminarLogico(
            $tnUsuario,
            (string)$toRequest->input('Version'),
            $tnUsuarioSesion,
            $toRequest->filled('Motivo') ? (string)$toRequest->input('Motivo') : null
        );

        if (($la['Estado'] ?? '') === 'CONFLICTO_VERSION') {
            return RespuestaApi::error('Conflicto de version', 409, [], $la['Actual'] ?? []);
        }

        return RespuestaApi::exito('Usuario eliminado logicamente', $la['Datos'] ?? []);
    }

    public function ListarRoles(int $tnUsuario): JsonResponse
    {
        return RespuestaApi::exito('Roles del usuario', $this->toService->listarRoles($tnUsuario));
    }

    public function ActualizarRoles(Request $toRequest, int $tnUsuario): JsonResponse
    {
        $toRequest->validate([
            'Roles' => ['required', 'array', 'min:1'],
            'Roles.*' => ['integer', 'min:1'],
            'Motivo' => ['nullable', 'string', 'max:255'],
        ]);

        $tnUsuarioSesion = (int)(auth()->id() ?? 0);

        $laActual = $this->toService->Obtener($tnUsuario);
        if (!$laActual) {
            return RespuestaApi::error('Usuario no encontrado', 404);
        }

        if (!$this->toAutorizacion->puedeGestionarUsuario($tnUsuarioSesion, (int)$laActual['Empresa'])) {
            return RespuestaApi::error('No autorizado para actualizar roles de este usuario', 403);
        }

        $this->toService->actualizarRoles($tnUsuario, (array)$toRequest->input('Roles'), $tnUsuarioSesion);

        return RespuestaApi::exito('Roles actualizados correctamente', $this->toService->listarRoles($tnUsuario));
    }

    public function ListarMaquinas(int $tnUsuario, Request $toRequest): JsonResponse
    {
        $tcRol = $toRequest->filled('Rol') ? (string)$toRequest->query('Rol') : null;
        return RespuestaApi::exito('Maquinas del usuario', $this->toService->listarMaquinas($tnUsuario, $tcRol));
    }

    public function AsignarMaquina(Request $toRequest, int $tnUsuario): JsonResponse
    {
        $toRequest->validate([
            'Maquina' => ['required', 'integer', 'min:1'],
            'Motivo' => ['nullable', 'string', 'max:255'],
        ]);

        $tnUsuarioSesion = (int)(auth()->id() ?? 0);
        $laActual = $this->toService->Obtener($tnUsuario);
        if (!$laActual) {
            return RespuestaApi::error('Usuario no encontrado', 404);
        }
        if (!$this->toAutorizacion->puedeGestionarUsuario($tnUsuarioSesion, (int)$laActual['Empresa'])) {
            return RespuestaApi::error('No autorizado para asignar maquina', 403);
        }

        $laRoles = array_map(fn ($toRol) => (string)($toRol['NombreRol'] ?? ''), $this->toService->listarRoles($tnUsuario));
        if (!in_array('Admin', $laRoles, true) && !in_array('Operador', $laRoles, true)) {
            return RespuestaApi::error('La asignacion de maquina aplica solo a Admin/Operador', 409);
        }

        $la = $this->toService->asignarMaquina(
            $tnUsuario,
            (int)$toRequest->input('Maquina'),
            $tnUsuarioSesion,
            $toRequest->filled('Motivo') ? (string)$toRequest->input('Motivo') : null
        );

        return RespuestaApi::exito('Maquina asignada correctamente', $la);
    }

    public function QuitarMaquina(Request $toRequest, int $tnUsuario, int $tnMaquina): JsonResponse
    {
        $toRequest->validate([
            'Motivo' => ['nullable', 'string', 'max:255'],
        ]);

        $tnUsuarioSesion = (int)(auth()->id() ?? 0);

        $la = $this->toService->quitarMaquina(
            $tnUsuario,
            $tnMaquina,
            $tnUsuarioSesion,
            $toRequest->filled('Motivo') ? (string)$toRequest->input('Motivo') : null
        );

        if (($la['Estado'] ?? '') === 'NO_ENCONTRADO') {
            return RespuestaApi::error('Asignacion usuario-maquina no encontrada', 404);
        }

        return RespuestaApi::exito('Asignacion eliminada logicamente', $la['Datos'] ?? []);
    }

    public function ListarAdministradoresMaquina(int $tnMaquina): JsonResponse
    {
        return RespuestaApi::exito('Administradores de maquina', $this->toService->listarUsuariosPorMaquinaRol($tnMaquina, 'Admin'));
    }

    public function ListarOperadoresMaquina(int $tnMaquina): JsonResponse
    {
        return RespuestaApi::exito('Operadores de maquina', $this->toService->listarUsuariosPorMaquinaRol($tnMaquina, 'Operador'));
    }

    private function resolverActualizacion(Request $toRequest, int $tnUsuario, bool $lbParcial): JsonResponse
    {
        $tnUsuarioSesion = (int)(auth()->id() ?? 0);
        $laActual = $this->toService->Obtener($tnUsuario);
        if (!$laActual) {
            return RespuestaApi::error('Usuario no encontrado', 404);
        }

        $tnEmpresaObjetivo = $toRequest->filled('Empresa') ? (int)$toRequest->input('Empresa') : (int)$laActual['Empresa'];
        if (!$this->toAutorizacion->puedeGestionarUsuario($tnUsuarioSesion, $tnEmpresaObjetivo)) {
            return RespuestaApi::error('No autorizado para actualizar este usuario', 403);
        }

        $la = $this->toService->Actualizar(
            $tnUsuario,
            $toRequest->all(),
            (string)$toRequest->input('Version'),
            $tnUsuarioSesion,
            $lbParcial
        );

        if (($la['Estado'] ?? '') === 'NO_ENCONTRADO') {
            return RespuestaApi::error('Usuario no encontrado', 404);
        }
        if (($la['Estado'] ?? '') === 'CONFLICTO_VERSION') {
            return RespuestaApi::error('Conflicto de version', 409, [], $la['Actual'] ?? []);
        }

        return RespuestaApi::exito('Usuario actualizado correctamente', $la['Datos'] ?? []);
    }
}

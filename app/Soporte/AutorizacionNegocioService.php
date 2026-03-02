<?php

namespace App\Soporte;

use App\Modulos\Autenticacion\Services\PermisoService;
use Illuminate\Support\Facades\DB;

class AutorizacionNegocioService
{
    public function __construct(private PermisoService $toPermisoService)
    {
    }

    /** @return array<int,string> */
    public function rolesUsuario(int $tnUsuario): array
    {
        return $this->toPermisoService->obtenerRolesUsuario($tnUsuario);
    }

    public function esDueno(int $tnUsuario): bool
    {
        foreach ($this->rolesUsuario($tnUsuario) as $tcRol) {
            $tcNormalizado = mb_strtolower(trim((string)$tcRol));
            $tcSinAcentos = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $tcNormalizado);
            if (!is_string($tcSinAcentos) || $tcSinAcentos === '') {
                $tcSinAcentos = $tcNormalizado;
            }

            $tcClave = preg_replace('/[^a-z]/', '', mb_strtolower($tcSinAcentos));
            if ($tcClave === 'dueno') {
                return true;
            }
        }

        return false;
    }

    public function esAdmin(int $tnUsuario): bool
    {
        return in_array('Admin', $this->rolesUsuario($tnUsuario), true);
    }

    public function esOperador(int $tnUsuario): bool
    {
        return in_array('Operador', $this->rolesUsuario($tnUsuario), true);
    }

    public function puedeGestionarEmpresa(int $tnUsuarioSesion, int $tnEmpresaObjetivo): bool
    {
        if ($this->esDueno($tnUsuarioSesion)) {
            return true;
        }

        if (!$this->esAdmin($tnUsuarioSesion)) {
            return false;
        }

        $loUsuario = DB::connection('mysqlNegocio')
            ->table('USUARIO')
            ->select('Empresa')
            ->where('Usuario', $tnUsuarioSesion)
            ->first();

        return (int)($loUsuario->Empresa ?? 0) === $tnEmpresaObjetivo;
    }

    public function puedeAccederMaquina(int $tnUsuarioSesion, int $tnMaquina): bool
    {
        return $this->toPermisoService->usuarioPerteneceMaquina($tnUsuarioSesion, $tnMaquina);
    }

    public function puedeGestionarUsuario(int $tnUsuarioSesion, int $tnEmpresaObjetivo): bool
    {
        if ($this->esDueno($tnUsuarioSesion)) {
            return true;
        }

        return $this->esAdmin($tnUsuarioSesion) && $this->puedeGestionarEmpresa($tnUsuarioSesion, $tnEmpresaObjetivo);
    }
}
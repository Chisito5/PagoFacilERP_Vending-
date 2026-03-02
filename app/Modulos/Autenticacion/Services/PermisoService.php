<?php

namespace App\Modulos\Autenticacion\Services;

use App\Support\EstadoCatalogo;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PermisoService
{
    private ?int $pnEstadoActivo = null;

    public function __construct(private EstadoCatalogo $toEstadoCatalogo)
    {
    }

    /**
     * @return array<int,string>
     */
    public function obtenerRolesUsuario(int $tnUsuario): array
    {
        $tnEstadoActivo = $this->obtenerEstadoActivo();

        $laRoles = DB::connection('mysqlNegocio')
            ->table('USUARIOROL as ur')
            ->join('ROL as r', 'r.Rol', '=', 'ur.Rol')
            ->where('ur.Usuario', $tnUsuario)
            ->where('ur.Estado', $tnEstadoActivo)
            ->where('r.Estado', $tnEstadoActivo)
            ->orderBy('r.Rol')
            ->pluck('r.NombreRol')
            ->map(fn($tcRol) => (string)$tcRol)
            ->all();

        return array_values(array_unique($laRoles));
    }

    /**
     * @return array<int,string>
     */
    public function obtenerPermisosUsuario(int $tnUsuario): array
    {
        $tnEstadoActivo = $this->obtenerEstadoActivo();

        $laPermisos = DB::connection('mysqlNegocio')
            ->table('USUARIOROL as ur')
            ->join('ROLPERMISO as rp', 'rp.Rol', '=', 'ur.Rol')
            ->join('PERMISO as p', 'p.Permiso', '=', 'rp.Permiso')
            ->where('ur.Usuario', $tnUsuario)
            ->where('ur.Estado', $tnEstadoActivo)
            ->where('rp.Estado', $tnEstadoActivo)
            ->where('p.Estado', $tnEstadoActivo)
            ->orderBy('p.Permiso')
            ->pluck('p.CodigoPermiso')
            ->map(fn($tcPermiso) => (string)$tcPermiso)
            ->all();

        $laPermisos = array_values(array_unique($laPermisos));
        if (in_array('*', $laPermisos, true)) {
            return ['*'];
        }

        return $laPermisos;
    }

    public function obtenerRolPrincipal(int $tnUsuario): string
    {
        $laRoles = $this->obtenerRolesUsuario($tnUsuario);

        return $laRoles[0] ?? 'SinRol';
    }

    public function usuarioTieneAccesoTotal(int $tnUsuario): bool
    {
        return in_array('*', $this->obtenerPermisosUsuario($tnUsuario), true);
    }

    public function usuarioPerteneceEmpresa(int $tnUsuario, int $tnEmpresa): bool
    {
        if ($this->usuarioTieneAccesoTotal($tnUsuario)) {
            return true;
        }

        $loUsuario = DB::connection('mysqlNegocio')
            ->table('USUARIO')
            ->select('Empresa')
            ->where('Usuario', $tnUsuario)
            ->first();

        if (!$loUsuario) {
            return false;
        }

        return (int)$loUsuario->Empresa === $tnEmpresa;
    }

    public function usuarioPerteneceMaquina(int $tnUsuario, int $tnMaquina): bool
    {
        if ($this->usuarioTieneAccesoTotal($tnUsuario)) {
            return true;
        }

        $tnEstadoActivo = $this->obtenerEstadoActivo();

        $lbAsignado = DB::connection('mysqlNegocio')
            ->table('USUARIOMAQUINA')
            ->where('Usuario', $tnUsuario)
            ->where('Maquina', $tnMaquina)
            ->where('Estado', $tnEstadoActivo)
            ->exists();

        if ($lbAsignado) {
            return true;
        }

        $loUsuario = DB::connection('mysqlNegocio')
            ->table('USUARIO')
            ->select('Empresa')
            ->where('Usuario', $tnUsuario)
            ->first();

        if (!$loUsuario) {
            return false;
        }

        $loMaquina = DB::connection('mysqlNegocio')
            ->table('MAQUINA as m')
            ->leftJoin('UBICACION as u', 'u.Ubicacion', '=', 'm.UbicacionActual')
            ->where('m.Maquina', $tnMaquina)
            ->select('u.Empresa as EmpresaMaquina')
            ->first();

        if (!$loMaquina) {
            return false;
        }

        return (int)($loMaquina->EmpresaMaquina ?? 0) > 0
            && (int)$loMaquina->EmpresaMaquina === (int)($loUsuario->Empresa ?? 0);
    }

    private function obtenerEstadoActivo(): int
    {
        if ($this->pnEstadoActivo !== null) {
            return $this->pnEstadoActivo;
        }

        try {
            $this->pnEstadoActivo = $this->toEstadoCatalogo->obtenerId('GENERAL', 1);
        } catch (RuntimeException) {
            $this->pnEstadoActivo = 1;
        }

        return $this->pnEstadoActivo;
    }
}

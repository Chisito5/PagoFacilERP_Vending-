<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

return new class extends Migration
{
    public function up(): void
    {
        DB::connection('mysqlNegocio')->transaction(function (): void {
            $tdAhora = now();
            $tnEstadoActivo = $this->obtenerEstadoActivo();

            $tnEmpresa = $this->obtenerEmpresaDefault($tnEstadoActivo);

            $tnRolAdmin = $this->obtenerOCrearRolAdmin($tnEstadoActivo, $tdAhora);
            $tnPermisoTotal = $this->obtenerOCrearPermisoTotal($tnEstadoActivo, $tdAhora);
            $this->obtenerOCrearRelacionRolPermiso($tnRolAdmin, $tnPermisoTotal, $tnEstadoActivo, $tdAhora);

            $tnUsuario = $this->obtenerOCrearUsuarioQa($tnEmpresa, $tnEstadoActivo, $tdAhora);
            $this->obtenerOCrearRelacionUsuarioRol($tnUsuario, $tnRolAdmin, $tnEstadoActivo, $tdAhora);
        });
    }

    public function down(): void
    {
        // Se deja intencionalmente sin rollback para preservar usuario QA y permisos en entorno local.
    }

    private function obtenerEstadoActivo(): int
    {
        $loEstado = DB::connection('mysqlNegocio')
            ->table('ESTADO')
            ->where('Entidad', 'GENERAL')
            ->where('CodigoEstado', 1)
            ->first();

        if ($loEstado) {
            return (int)$loEstado->Estado;
        }

        return 1;
    }

    private function obtenerEmpresaDefault(int $tnEstadoActivo): int
    {
        $loEmpresaUno = DB::connection('mysqlNegocio')
            ->table('EMPRESA')
            ->where('Empresa', 1)
            ->first();

        if ($loEmpresaUno) {
            return 1;
        }

        $loEmpresaActiva = DB::connection('mysqlNegocio')
            ->table('EMPRESA')
            ->where('Estado', $tnEstadoActivo)
            ->orderBy('Empresa')
            ->first();

        if ($loEmpresaActiva) {
            return (int)$loEmpresaActiva->Empresa;
        }

        $loPrimeraEmpresa = DB::connection('mysqlNegocio')
            ->table('EMPRESA')
            ->orderBy('Empresa')
            ->first();

        if (!$loPrimeraEmpresa) {
            throw new \RuntimeException('No existe EMPRESA para asociar el usuario QA');
        }

        return (int)$loPrimeraEmpresa->Empresa;
    }

    private function obtenerOCrearRolAdmin(int $tnEstadoActivo, $tdAhora): int
    {
        $loRol = DB::connection('mysqlNegocio')
            ->table('ROL')
            ->where('NombreRol', 'Admin')
            ->first();

        if ($loRol) {
            DB::connection('mysqlNegocio')
                ->table('ROL')
                ->where('Rol', (int)$loRol->Rol)
                ->update([
                    'Estado' => $tnEstadoActivo,
                    'Descripcion' => 'Rol administrador global',
                ]);

            return (int)$loRol->Rol;
        }

        return (int)DB::connection('mysqlNegocio')
            ->table('ROL')
            ->insertGetId([
                'NombreRol' => 'Admin',
                'Descripcion' => 'Rol administrador global',
                'Estado' => $tnEstadoActivo,
                'Usr' => 0,
                'UsrFecha' => $tdAhora->toDateString(),
                'UsrHora' => $tdAhora->format('H:i:s'),
            ]);
    }

    private function obtenerOCrearPermisoTotal(int $tnEstadoActivo, $tdAhora): int
    {
        $loPermiso = DB::connection('mysqlNegocio')
            ->table('PERMISO')
            ->where('CodigoPermiso', '*')
            ->first();

        if ($loPermiso) {
            DB::connection('mysqlNegocio')
                ->table('PERMISO')
                ->where('Permiso', (int)$loPermiso->Permiso)
                ->update([
                    'Estado' => $tnEstadoActivo,
                    'NombrePermiso' => 'Acceso total',
                    'Descripcion' => 'Permiso comodin con acceso completo',
                ]);

            return (int)$loPermiso->Permiso;
        }

        return (int)DB::connection('mysqlNegocio')
            ->table('PERMISO')
            ->insertGetId([
                'CodigoPermiso' => '*',
                'NombrePermiso' => 'Acceso total',
                'Descripcion' => 'Permiso comodin con acceso completo',
                'Estado' => $tnEstadoActivo,
                'Usr' => 0,
                'UsrFecha' => $tdAhora->toDateString(),
                'UsrHora' => $tdAhora->format('H:i:s'),
            ]);
    }

    private function obtenerOCrearRelacionRolPermiso(int $tnRol, int $tnPermiso, int $tnEstadoActivo, $tdAhora): void
    {
        $loRelacion = DB::connection('mysqlNegocio')
            ->table('ROLPERMISO')
            ->where('Rol', $tnRol)
            ->where('Permiso', $tnPermiso)
            ->first();

        if ($loRelacion) {
            DB::connection('mysqlNegocio')
                ->table('ROLPERMISO')
                ->where('RolPermiso', (int)$loRelacion->RolPermiso)
                ->update([
                    'Estado' => $tnEstadoActivo,
                ]);
            return;
        }

        DB::connection('mysqlNegocio')
            ->table('ROLPERMISO')
            ->insert([
                'Rol' => $tnRol,
                'Permiso' => $tnPermiso,
                'Estado' => $tnEstadoActivo,
                'Usr' => 0,
                'UsrFecha' => $tdAhora->toDateString(),
                'UsrHora' => $tdAhora->format('H:i:s'),
            ]);
    }

    private function obtenerOCrearUsuarioQa(int $tnEmpresa, int $tnEstadoActivo, $tdAhora): int
    {
        $loUsuario = DB::connection('mysqlNegocio')
            ->table('USUARIO')
            ->where('NombreUsuario', 'Vladimir')
            ->first();

        $laDatos = [
            'Empresa' => $tnEmpresa,
            'NombreUsuario' => 'Vladimir',
            'ClaveCifrada' => Hash::make('Asadito7'),
            'Nombres' => 'Vladimir',
            'Apellidos' => 'QA',
            'Correo' => null,
            'Telefono' => null,
            'DocumentoIdentidad' => null,
            'Estado' => $tnEstadoActivo,
            'Usr' => 0,
            'UsrFecha' => $tdAhora->toDateString(),
            'UsrHora' => $tdAhora->format('H:i:s'),
        ];

        if ($loUsuario) {
            DB::connection('mysqlNegocio')
                ->table('USUARIO')
                ->where('Usuario', (int)$loUsuario->Usuario)
                ->update($laDatos);

            return (int)$loUsuario->Usuario;
        }

        return (int)DB::connection('mysqlNegocio')
            ->table('USUARIO')
            ->insertGetId($laDatos);
    }

    private function obtenerOCrearRelacionUsuarioRol(int $tnUsuario, int $tnRol, int $tnEstadoActivo, $tdAhora): void
    {
        $loRelacion = DB::connection('mysqlNegocio')
            ->table('USUARIOROL')
            ->where('Usuario', $tnUsuario)
            ->where('Rol', $tnRol)
            ->first();

        if ($loRelacion) {
            DB::connection('mysqlNegocio')
                ->table('USUARIOROL')
                ->where('UsuarioRol', (int)$loRelacion->UsuarioRol)
                ->update([
                    'Estado' => $tnEstadoActivo,
                ]);
            return;
        }

        DB::connection('mysqlNegocio')
            ->table('USUARIOROL')
            ->insert([
                'Usuario' => $tnUsuario,
                'Rol' => $tnRol,
                'Estado' => $tnEstadoActivo,
                'Usr' => 0,
                'UsrFecha' => $tdAhora->toDateString(),
                'UsrHora' => $tdAhora->format('H:i:s'),
            ]);
    }
};

<?php

namespace App\Modulos\Usuario\Services;

use App\Soporte\AuditoriaService;
use App\Soporte\ControlVersionService;
use App\Soporte\EstadoNegocioService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UsuarioService
{
    private string $pcConexion = 'mysqlNegocio';

    public function __construct(
        private EstadoNegocioService $toEstadoNegocio,
        private ControlVersionService $toControlVersion,
        private AuditoriaService $toAuditoria
    ) {
    }

    public function Listar(?int $tnEmpresa, ?int $tnEstado, ?string $tcBusqueda, int $tnPagina, int $tnTamanoPagina): LengthAwarePaginator
    {
        $tnTamanoPagina = max(1, min($tnTamanoPagina, 200));

        $toConsulta = DB::connection($this->pcConexion)
            ->table('USUARIO as u')
            ->select([
                'u.Usuario', 'u.Empresa', 'u.NombreUsuario', 'u.Nombres', 'u.Apellidos', 'u.Correo',
                'u.Telefono', 'u.DocumentoIdentidad', 'u.Estado', 'u.Usr', 'u.UsrFecha', 'u.UsrHora'
            ])
            ->orderByDesc('u.Usuario');

        if ($tnEmpresa !== null && $tnEmpresa > 0) {
            $toConsulta->where('u.Empresa', $tnEmpresa);
        }
        if ($tnEstado !== null && $tnEstado > 0) {
            $toConsulta->where('u.Estado', $tnEstado);
        }
        if ($tcBusqueda !== null && trim($tcBusqueda) !== '') {
            $tcBusqueda = trim($tcBusqueda);
            $toConsulta->where(function ($toWhere) use ($tcBusqueda): void {
                $toWhere->where('u.NombreUsuario', 'like', '%' . $tcBusqueda . '%')
                    ->orWhere('u.Nombres', 'like', '%' . $tcBusqueda . '%')
                    ->orWhere('u.Apellidos', 'like', '%' . $tcBusqueda . '%')
                    ->orWhere('u.Correo', 'like', '%' . $tcBusqueda . '%');
            });
        }

        return $toConsulta->paginate($tnTamanoPagina, ['*'], 'Pagina', max(1, $tnPagina));
    }

    public function Obtener(int $tnUsuario): ?array
    {
        $lo = DB::connection($this->pcConexion)
            ->table('USUARIO')
            ->select([
                'Usuario', 'Empresa', 'NombreUsuario', 'Nombres', 'Apellidos', 'Correo',
                'Telefono', 'DocumentoIdentidad', 'Estado', 'Usr', 'UsrFecha', 'UsrHora'
            ])
            ->where('Usuario', $tnUsuario)
            ->first();

        if (!$lo) {
            return null;
        }

        $la = (array)$lo;
        $la['Version'] = $this->toControlVersion->versionDesdeFila($lo);
        $la['Roles'] = $this->listarRoles($tnUsuario);

        return $la;
    }

    /** @param array<string,mixed> $taDatos */
    public function Crear(array $taDatos, int $tnUsuarioSesion): array
    {
        $tdAhora = now();

        $tnEstadoActivo = $this->toEstadoNegocio->activoGeneral();

        $tnUsuario = DB::connection($this->pcConexion)
            ->table('USUARIO')
            ->insertGetId([
                'Empresa' => (int)$taDatos['Empresa'],
                'NombreUsuario' => (string)$taDatos['NombreUsuario'],
                'ClaveCifrada' => Hash::make((string)$taDatos['Clave']),
                'Nombres' => (string)$taDatos['Nombres'],
                'Apellidos' => $taDatos['Apellidos'] ?? null,
                'Correo' => $taDatos['Correo'] ?? null,
                'Telefono' => $taDatos['Telefono'] ?? null,
                'DocumentoIdentidad' => $taDatos['DocumentoIdentidad'] ?? null,
                'Estado' => (int)($taDatos['Estado'] ?? $tnEstadoActivo),
                'Usr' => $tnUsuarioSesion,
                'UsrFecha' => $tdAhora->toDateString(),
                'UsrHora' => $tdAhora->format('H:i:s'),
            ]);

        if (!empty($taDatos['Roles']) && is_array($taDatos['Roles'])) {
            $this->actualizarRoles($tnUsuario, $taDatos['Roles'], $tnUsuarioSesion);
        }

        $la = $this->Obtener($tnUsuario) ?? [];
        $this->toAuditoria->registrar('USUARIO', $tnUsuario, 'CREAR', null, $la, $tnUsuarioSesion, $taDatos['Motivo'] ?? null);

        return $la;
    }

    /** @param array<string,mixed> $taDatos */
    public function Actualizar(int $tnUsuario, array $taDatos, string $tcVersion, int $tnUsuarioSesion, bool $lbParcial): array
    {
        return DB::connection($this->pcConexion)->transaction(function () use ($tnUsuario, $taDatos, $tcVersion, $tnUsuarioSesion, $lbParcial): array {
            $loActual = DB::connection($this->pcConexion)
                ->table('USUARIO')
                ->where('Usuario', $tnUsuario)
                ->lockForUpdate()
                ->first();

            if (!$loActual) {
                return ['Estado' => 'NO_ENCONTRADO'];
            }

            if (!$this->toControlVersion->coincide($tcVersion, $loActual)) {
                return ['Estado' => 'CONFLICTO_VERSION', 'Actual' => $this->Obtener($tnUsuario)];
            }

            $laCampos = ['Empresa', 'NombreUsuario', 'Nombres', 'Apellidos', 'Correo', 'Telefono', 'DocumentoIdentidad', 'Estado'];
            $laUpdate = [];
            foreach ($laCampos as $tcCampo) {
                if (array_key_exists($tcCampo, $taDatos)) {
                    $laUpdate[$tcCampo] = $taDatos[$tcCampo];
                } elseif (!$lbParcial) {
                    $laUpdate[$tcCampo] = $loActual->{$tcCampo};
                }
            }

            if (!empty($taDatos['Clave'])) {
                $laUpdate['ClaveCifrada'] = Hash::make((string)$taDatos['Clave']);
            }

            $tdAhora = now();
            $laUpdate['Usr'] = $tnUsuarioSesion;
            $laUpdate['UsrFecha'] = $tdAhora->toDateString();
            $laUpdate['UsrHora'] = $tdAhora->format('H:i:s');

            DB::connection($this->pcConexion)->table('USUARIO')->where('Usuario', $tnUsuario)->update($laUpdate);

            if (array_key_exists('Roles', $taDatos) && is_array($taDatos['Roles'])) {
                $this->actualizarRoles($tnUsuario, $taDatos['Roles'], $tnUsuarioSesion);
            }

            $laAntes = $this->Obtener($tnUsuario);
            $laDespues = $this->Obtener($tnUsuario);
            $this->toAuditoria->registrar('USUARIO', $tnUsuario, 'ACTUALIZAR', $laAntes, $laDespues, $tnUsuarioSesion, $taDatos['Motivo'] ?? null);

            return ['Estado' => 'OK', 'Datos' => $laDespues];
        });
    }

    public function EliminarLogico(int $tnUsuario, string $tcVersion, int $tnUsuarioSesion, ?string $tcMotivo): array
    {
        $tnEstadoInactivo = $this->toEstadoNegocio->inactivoGeneral();

        return DB::connection($this->pcConexion)->transaction(function () use ($tnUsuario, $tcVersion, $tnUsuarioSesion, $tnEstadoInactivo, $tcMotivo): array {
            $loActual = DB::connection($this->pcConexion)
                ->table('USUARIO')
                ->where('Usuario', $tnUsuario)
                ->lockForUpdate()
                ->first();

            if (!$loActual) {
                return ['Estado' => 'NO_ENCONTRADO'];
            }

            if (!$this->toControlVersion->coincide($tcVersion, $loActual)) {
                return ['Estado' => 'CONFLICTO_VERSION', 'Actual' => $this->Obtener($tnUsuario)];
            }

            $tdAhora = now();
            DB::connection($this->pcConexion)
                ->table('USUARIO')
                ->where('Usuario', $tnUsuario)
                ->update([
                    'Estado' => $tnEstadoInactivo,
                    'Usr' => $tnUsuarioSesion,
                    'UsrFecha' => $tdAhora->toDateString(),
                    'UsrHora' => $tdAhora->format('H:i:s'),
                ]);

            DB::connection($this->pcConexion)->table('USUARIOROL')->where('Usuario', $tnUsuario)->update([
                'Estado' => $tnEstadoInactivo,
                'Usr' => $tnUsuarioSesion,
                'UsrFecha' => $tdAhora->toDateString(),
                'UsrHora' => $tdAhora->format('H:i:s'),
            ]);

            DB::connection($this->pcConexion)->table('USUARIOMAQUINA')->where('Usuario', $tnUsuario)->update([
                'Estado' => $tnEstadoInactivo,
                'Usr' => $tnUsuarioSesion,
                'UsrFecha' => $tdAhora->toDateString(),
                'UsrHora' => $tdAhora->format('H:i:s'),
            ]);

            $laDespues = $this->Obtener($tnUsuario);
            $this->toAuditoria->registrar('USUARIO', $tnUsuario, 'ELIMINAR_LOGICO', (array)$loActual, $laDespues, $tnUsuarioSesion, $tcMotivo);

            return ['Estado' => 'OK', 'Datos' => $laDespues];
        });
    }

    /** @return array<int,array<string,mixed>> */
    public function listarRoles(int $tnUsuario): array
    {
        return DB::connection($this->pcConexion)
            ->table('USUARIOROL as ur')
            ->join('ROL as r', 'r.Rol', '=', 'ur.Rol')
            ->select(['ur.UsuarioRol', 'ur.Usuario', 'ur.Rol', 'r.NombreRol', 'ur.Estado', 'ur.UsrFecha', 'ur.UsrHora'])
            ->where('ur.Usuario', $tnUsuario)
            ->orderBy('ur.UsuarioRol')
            ->get()
            ->map(fn ($toFila) => array_merge((array)$toFila, ['Version' => $this->toControlVersion->versionDesdeFila($toFila)]))
            ->all();
    }

    /** @param array<int,int|string> $taRoles */
    public function actualizarRoles(int $tnUsuario, array $taRoles, int $tnUsuarioSesion): void
    {
        $tdAhora = now();
        $tnEstadoActivo = $this->toEstadoNegocio->activoGeneral();

        $laRoles = [];
        foreach ($taRoles as $tmRol) {
            if (is_numeric($tmRol)) {
                $laRoles[] = (int)$tmRol;
                continue;
            }
            $loRol = DB::connection($this->pcConexion)->table('ROL')->where('NombreRol', (string)$tmRol)->first();
            if ($loRol) {
                $laRoles[] = (int)$loRol->Rol;
            }
        }
        $laRoles = array_values(array_unique(array_filter($laRoles, fn ($x) => $x > 0)));

        DB::connection($this->pcConexion)->table('USUARIOROL')->where('Usuario', $tnUsuario)->update([
            'Estado' => $this->toEstadoNegocio->inactivoGeneral(),
            'Usr' => $tnUsuarioSesion,
            'UsrFecha' => $tdAhora->toDateString(),
            'UsrHora' => $tdAhora->format('H:i:s'),
        ]);

        foreach ($laRoles as $tnRol) {
            $loExiste = DB::connection($this->pcConexion)
                ->table('USUARIOROL')
                ->where('Usuario', $tnUsuario)
                ->where('Rol', $tnRol)
                ->first();

            if ($loExiste) {
                DB::connection($this->pcConexion)->table('USUARIOROL')->where('UsuarioRol', (int)$loExiste->UsuarioRol)->update([
                    'Estado' => $tnEstadoActivo,
                    'Usr' => $tnUsuarioSesion,
                    'UsrFecha' => $tdAhora->toDateString(),
                    'UsrHora' => $tdAhora->format('H:i:s'),
                ]);
                continue;
            }

            DB::connection($this->pcConexion)->table('USUARIOROL')->insert([
                'Usuario' => $tnUsuario,
                'Rol' => $tnRol,
                'Estado' => $tnEstadoActivo,
                'Usr' => $tnUsuarioSesion,
                'UsrFecha' => $tdAhora->toDateString(),
                'UsrHora' => $tdAhora->format('H:i:s'),
            ]);
        }
    }

    /** @return array<int,array<string,mixed>> */
    public function listarMaquinas(int $tnUsuario, ?string $tcRol): array
    {
        $toConsulta = DB::connection($this->pcConexion)
            ->table('USUARIOMAQUINA as um')
            ->join('MAQUINA as m', 'm.Maquina', '=', 'um.Maquina')
            ->leftJoin('USUARIOROL as ur', function ($join) {
                $join->on('ur.Usuario', '=', 'um.Usuario');
                $join->on('ur.Estado', '=', 'um.Estado');
            })
            ->leftJoin('ROL as r', 'r.Rol', '=', 'ur.Rol')
            ->select([
                'um.UsuarioMaquina', 'um.Usuario', 'um.Maquina', 'm.CodigoMaquina', 'um.Estado',
                'um.UsrFecha', 'um.UsrHora', 'r.NombreRol'
            ])
            ->where('um.Usuario', $tnUsuario)
            ->orderBy('um.UsuarioMaquina');

        if ($tcRol !== null && trim($tcRol) !== '') {
            $toConsulta->where('r.NombreRol', trim($tcRol));
        }

        return $toConsulta->get()->map(fn ($toFila) => array_merge((array)$toFila, ['Version' => $this->toControlVersion->versionDesdeFila($toFila)]))->all();
    }

    public function asignarMaquina(int $tnUsuario, int $tnMaquina, int $tnUsuarioSesion, ?string $tcMotivo): array
    {
        $tdAhora = now();
        $tnEstadoActivo = $this->toEstadoNegocio->activoGeneral();

        $loExiste = DB::connection($this->pcConexion)
            ->table('USUARIOMAQUINA')
            ->where('Usuario', $tnUsuario)
            ->where('Maquina', $tnMaquina)
            ->first();

        if ($loExiste) {
            DB::connection($this->pcConexion)
                ->table('USUARIOMAQUINA')
                ->where('UsuarioMaquina', (int)$loExiste->UsuarioMaquina)
                ->update([
                    'Estado' => $tnEstadoActivo,
                    'Usr' => $tnUsuarioSesion,
                    'UsrFecha' => $tdAhora->toDateString(),
                    'UsrHora' => $tdAhora->format('H:i:s'),
                ]);
            $la = (array)DB::connection($this->pcConexion)->table('USUARIOMAQUINA')->where('UsuarioMaquina', (int)$loExiste->UsuarioMaquina)->first();
            $this->toAuditoria->registrar('USUARIOMAQUINA', (int)$loExiste->UsuarioMaquina, 'ASIGNAR', (array)$loExiste, $la, $tnUsuarioSesion, $tcMotivo);
            return $la;
        }

        $tnUsuarioMaquina = DB::connection($this->pcConexion)->table('USUARIOMAQUINA')->insertGetId([
            'Usuario' => $tnUsuario,
            'Maquina' => $tnMaquina,
            'Estado' => $tnEstadoActivo,
            'Usr' => $tnUsuarioSesion,
            'UsrFecha' => $tdAhora->toDateString(),
            'UsrHora' => $tdAhora->format('H:i:s'),
        ]);

        $la = (array)DB::connection($this->pcConexion)->table('USUARIOMAQUINA')->where('UsuarioMaquina', $tnUsuarioMaquina)->first();
        $this->toAuditoria->registrar('USUARIOMAQUINA', $tnUsuarioMaquina, 'ASIGNAR', null, $la, $tnUsuarioSesion, $tcMotivo);

        return $la;
    }

    public function quitarMaquina(int $tnUsuario, int $tnMaquina, int $tnUsuarioSesion, ?string $tcMotivo): array
    {
        $tdAhora = now();
        $tnEstadoInactivo = $this->toEstadoNegocio->inactivoGeneral();

        $lo = DB::connection($this->pcConexion)
            ->table('USUARIOMAQUINA')
            ->where('Usuario', $tnUsuario)
            ->where('Maquina', $tnMaquina)
            ->lockForUpdate()
            ->first();

        if (!$lo) {
            return ['Estado' => 'NO_ENCONTRADO'];
        }

        DB::connection($this->pcConexion)
            ->table('USUARIOMAQUINA')
            ->where('UsuarioMaquina', (int)$lo->UsuarioMaquina)
            ->update([
                'Estado' => $tnEstadoInactivo,
                'Usr' => $tnUsuarioSesion,
                'UsrFecha' => $tdAhora->toDateString(),
                'UsrHora' => $tdAhora->format('H:i:s'),
            ]);

        $la = (array)DB::connection($this->pcConexion)->table('USUARIOMAQUINA')->where('UsuarioMaquina', (int)$lo->UsuarioMaquina)->first();
        $this->toAuditoria->registrar('USUARIOMAQUINA', (int)$lo->UsuarioMaquina, 'ELIMINAR_LOGICO', (array)$lo, $la, $tnUsuarioSesion, $tcMotivo);

        return ['Estado' => 'OK', 'Datos' => $la];
    }

    /** @return array<int,array<string,mixed>> */
    public function listarUsuariosPorMaquinaRol(int $tnMaquina, string $tcRol): array
    {
        return DB::connection($this->pcConexion)
            ->table('USUARIOMAQUINA as um')
            ->join('USUARIO as u', 'u.Usuario', '=', 'um.Usuario')
            ->join('USUARIOROL as ur', 'ur.Usuario', '=', 'u.Usuario')
            ->join('ROL as r', 'r.Rol', '=', 'ur.Rol')
            ->select([
                'u.Usuario', 'u.NombreUsuario', 'u.Nombres', 'u.Apellidos', 'u.Correo', 'u.Telefono',
                'u.Empresa', 'um.Maquina', 'r.NombreRol', 'u.Estado', 'u.UsrFecha', 'u.UsrHora'
            ])
            ->where('um.Maquina', $tnMaquina)
            ->where('r.NombreRol', $tcRol)
            ->where('um.Estado', $this->toEstadoNegocio->activoGeneral())
            ->where('ur.Estado', $this->toEstadoNegocio->activoGeneral())
            ->where('u.Estado', $this->toEstadoNegocio->activoGeneral())
            ->orderBy('u.Usuario')
            ->get()
            ->map(fn ($toFila) => array_merge((array)$toFila, ['Version' => $this->toControlVersion->versionDesdeFila($toFila)]))
            ->all();
    }
}

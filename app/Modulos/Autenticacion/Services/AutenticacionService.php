<?php

namespace App\Modulos\Autenticacion\Services;

use App\Models\Usuario;
use App\Support\EstadoCatalogo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class AutenticacionService
{
    private const MINUTOS_TOKEN_ACCESO = 10;
    private const DIAS_TOKEN_REFRESCO = 7;

    private ?int $pnEstadoActivo = null;

    public function __construct(
        private EstadoCatalogo $toEstadoCatalogo,
        private PermisoService $toPermisoService
    ) {
    }

    /**
     * @return array{codigo:int,mensaje:string,datos:array<string,mixed>,errores:array<int,array<string,mixed>>}
     */
    public function Login(string $tcUsuario, string $tcClave, ?string $tcIp, ?string $tcAgente): array
    {
        $tnEstadoActivo = $this->obtenerEstadoActivo();

        $loUsuario = DB::connection('mysqlNegocio')
            ->table('USUARIO')
            ->where('NombreUsuario', $tcUsuario)
            ->where('Estado', $tnEstadoActivo)
            ->first();

        if (!$loUsuario || !Hash::check($tcClave, (string)$loUsuario->ClaveCifrada)) {
            return [
                'codigo' => 401,
                'mensaje' => 'Credenciales invalidas',
                'datos' => [],
                'errores' => [
                    ['Codigo' => 'AUTH_001', 'Campo' => 'Usuario', 'Detalle' => 'Usuario o clave incorrectos']
                ],
            ];
        }

        $laTokens = $this->crearSesion((int)$loUsuario->Usuario, $tnEstadoActivo, $tcIp, $tcAgente);
        $laSesion = $this->construirSesionUsuario((int)$loUsuario->Usuario);

        return [
            'codigo' => 200,
            'mensaje' => 'Sesion iniciada correctamente',
            'datos' => array_merge($laTokens, $laSesion),
            'errores' => [],
        ];
    }

    /**
     * @return array{codigo:int,mensaje:string,datos:array<string,mixed>,errores:array<int,array<string,mixed>>}
     */
    public function Refresh(string $tcRefreshToken, ?string $tcIp, ?string $tcAgente): array
    {
        if (trim($tcRefreshToken) === '') {
            return [
                'codigo' => 400,
                'mensaje' => 'RefreshToken es obligatorio',
                'datos' => [],
                'errores' => [
                    ['Codigo' => 'AUTH_002', 'Campo' => 'RefreshToken', 'Detalle' => 'Debe enviar RefreshToken']
                ],
            ];
        }

        $tnEstadoActivo = $this->obtenerEstadoActivo();
        $tcHashRefresh = hash('sha256', $tcRefreshToken);

        return DB::connection('mysqlNegocio')->transaction(function () use ($tcHashRefresh, $tnEstadoActivo, $tcIp, $tcAgente) {
            $tdAhora = now();

            $loSesion = DB::connection('mysqlNegocio')
                ->table('SESIONAPI')
                ->where('HashTokenRefresco', $tcHashRefresh)
                ->where('Estado', $tnEstadoActivo)
                ->whereNull('FechaHoraRevocacion')
                ->where('ExpiraRefrescoEn', '>', $tdAhora)
                ->lockForUpdate()
                ->first();

            if (!$loSesion) {
                return [
                    'codigo' => 401,
                    'mensaje' => 'RefreshToken invalido o expirado',
                    'datos' => [],
                    'errores' => [
                        ['Codigo' => 'AUTH_003', 'Campo' => 'RefreshToken', 'Detalle' => 'Token no valido']
                    ],
                ];
            }

            $loUsuario = DB::connection('mysqlNegocio')
                ->table('USUARIO')
                ->where('Usuario', (int)$loSesion->Usuario)
                ->where('Estado', $tnEstadoActivo)
                ->first();

            if (!$loUsuario) {
                return [
                    'codigo' => 401,
                    'mensaje' => 'Usuario no autorizado',
                    'datos' => [],
                    'errores' => [
                        ['Codigo' => 'AUTH_004', 'Campo' => 'Usuario', 'Detalle' => 'Usuario inactivo o inexistente']
                    ],
                ];
            }

            $tcNuevoTokenAcceso = $this->generarTokenSeguro();
            $tcNuevoTokenRefresco = $this->generarTokenSeguro();

            DB::connection('mysqlNegocio')
                ->table('SESIONAPI')
                ->where('SesionApi', (int)$loSesion->SesionApi)
                ->update([
                    'HashTokenAcceso' => hash('sha256', $tcNuevoTokenAcceso),
                    'HashTokenRefresco' => hash('sha256', $tcNuevoTokenRefresco),
                    'ExpiraAccesoEn' => $tdAhora->copy()->addMinutes(self::MINUTOS_TOKEN_ACCESO),
                    'ExpiraRefrescoEn' => $tdAhora->copy()->addDays(self::DIAS_TOKEN_REFRESCO),
                    'UltimoUsoEn' => $tdAhora,
                    'Ip' => $tcIp,
                    'Agente' => $tcAgente,
                ]);

            return [
                'codigo' => 200,
                'mensaje' => 'Token refrescado correctamente',
                'datos' => [
                    'Token' => $tcNuevoTokenAcceso,
                    'RefreshToken' => $tcNuevoTokenRefresco,
                ],
                'errores' => [],
            ];
        });
    }

    /**
     * @return array{codigo:int,mensaje:string,datos:array<string,mixed>,errores:array<int,array<string,mixed>>}
     */
    public function Logout(?Usuario $toUsuario, ?string $tcTokenAcceso): array
    {
        if (!$toUsuario) {
            return [
                'codigo' => 401,
                'mensaje' => 'No autenticado',
                'datos' => [],
                'errores' => [
                    ['Codigo' => 'AUTH_005', 'Campo' => null, 'Detalle' => 'No se encontro sesion activa']
                ],
            ];
        }

        if (!$tcTokenAcceso || trim($tcTokenAcceso) === '') {
            return [
                'codigo' => 400,
                'mensaje' => 'Token de acceso faltante',
                'datos' => [],
                'errores' => [
                    ['Codigo' => 'AUTH_006', 'Campo' => 'Authorization', 'Detalle' => 'Debe enviar Bearer token']
                ],
            ];
        }

        $tdAhora = now();
        $tcHashAcceso = hash('sha256', $tcTokenAcceso);

        $tnAfectadas = DB::connection('mysqlNegocio')
            ->table('SESIONAPI')
            ->where('Usuario', (int)$toUsuario->Usuario)
            ->where('HashTokenAcceso', $tcHashAcceso)
            ->whereNull('FechaHoraRevocacion')
            ->update([
                'FechaHoraRevocacion' => $tdAhora,
                'UltimoUsoEn' => $tdAhora,
            ]);

        if ($tnAfectadas <= 0) {
            return [
                'codigo' => 200,
                'mensaje' => 'Sesion ya cerrada',
                'datos' => [],
                'errores' => [],
            ];
        }

        return [
            'codigo' => 200,
            'mensaje' => 'Sesion cerrada correctamente',
            'datos' => [],
            'errores' => [],
        ];
    }

    /**
     * @return array{codigo:int,mensaje:string,datos:array<string,mixed>,errores:array<int,array<string,mixed>>}
     */
    public function Me(?Usuario $toUsuario): array
    {
        if (!$toUsuario) {
            return [
                'codigo' => 401,
                'mensaje' => 'No autenticado',
                'datos' => [],
                'errores' => [
                    ['Codigo' => 'AUTH_005', 'Campo' => null, 'Detalle' => 'No se encontro sesion activa']
                ],
            ];
        }

        return [
            'codigo' => 200,
            'mensaje' => 'Sesion actual',
            'datos' => $this->construirSesionUsuario((int)$toUsuario->Usuario),
            'errores' => [],
        ];
    }

    /**
     * @return array{codigo:int,mensaje:string,datos:array<string,mixed>,errores:array<int,array<string,mixed>>}
     */
    public function Permisos(?Usuario $toUsuario): array
    {
        if (!$toUsuario) {
            return [
                'codigo' => 401,
                'mensaje' => 'No autenticado',
                'datos' => [],
                'errores' => [
                    ['Codigo' => 'AUTH_005', 'Campo' => null, 'Detalle' => 'No se encontro sesion activa']
                ],
            ];
        }

        $laPermisos = $this->toPermisoService->obtenerPermisosUsuario((int)$toUsuario->Usuario);

        return [
            'codigo' => 200,
            'mensaje' => 'Permisos efectivos',
            'datos' => ['Permisos' => $laPermisos],
            'errores' => [],
        ];
    }

    /**
     * @return array{Token:string,RefreshToken:string}
     */
    private function crearSesion(int $tnUsuario, int $tnEstadoActivo, ?string $tcIp, ?string $tcAgente): array
    {
        $tdAhora = now();
        $tcTokenAcceso = $this->generarTokenSeguro();
        $tcTokenRefresco = $this->generarTokenSeguro();

        DB::connection('mysqlNegocio')
            ->table('SESIONAPI')
            ->insert([
                'Usuario' => $tnUsuario,
                'HashTokenAcceso' => hash('sha256', $tcTokenAcceso),
                'HashTokenRefresco' => hash('sha256', $tcTokenRefresco),
                'ExpiraAccesoEn' => $tdAhora->copy()->addMinutes(self::MINUTOS_TOKEN_ACCESO),
                'ExpiraRefrescoEn' => $tdAhora->copy()->addDays(self::DIAS_TOKEN_REFRESCO),
                'FechaHoraRevocacion' => null,
                'Ip' => $tcIp,
                'Agente' => $tcAgente,
                'UltimoUsoEn' => $tdAhora,
                'Estado' => $tnEstadoActivo,
                'Usr' => $tnUsuario,
                'UsrFecha' => $tdAhora->toDateString(),
                'UsrHora' => $tdAhora->format('H:i:s'),
            ]);

        return [
            'Token' => $tcTokenAcceso,
            'RefreshToken' => $tcTokenRefresco,
        ];
    }

    /**
     * @return array{Usuario:string,Rol:string,Permisos:array<int,string>,EmpresaDefault:int}
     */
    private function construirSesionUsuario(int $tnUsuario): array
    {
        $loUsuario = DB::connection('mysqlNegocio')
            ->table('USUARIO')
            ->select('NombreUsuario', 'Empresa')
            ->where('Usuario', $tnUsuario)
            ->first();

        return [
            'Usuario' => (string)($loUsuario->NombreUsuario ?? ''),
            'Rol' => $this->toPermisoService->obtenerRolPrincipal($tnUsuario),
            'Permisos' => $this->toPermisoService->obtenerPermisosUsuario($tnUsuario),
            'EmpresaDefault' => (int)($loUsuario->Empresa ?? 0),
        ];
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

    private function generarTokenSeguro(): string
    {
        return bin2hex(random_bytes(48));
    }
}

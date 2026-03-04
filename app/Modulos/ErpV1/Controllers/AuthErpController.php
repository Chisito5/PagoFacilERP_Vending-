<?php

namespace App\Modulos\ErpV1\Controllers;

use App\Http\Controllers\Controller;
use App\Modulos\Autenticacion\Services\AutenticacionService;
use App\Soporte\RespuestaApi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthErpController extends Controller
{
    public function __construct(private AutenticacionService $toAutenticacionService)
    {
    }

    public function Login(Request $toRequest): JsonResponse
    {
        $toRequest->validate([
            'Usuario' => ['required', 'string', 'max:80'],
            'Clave' => ['required', 'string', 'max:255'],
        ]);

        return $this->respuestaServicio($this->toAutenticacionService->Login(
            (string)$toRequest->input('Usuario'),
            (string)$toRequest->input('Clave'),
            $toRequest->ip(),
            (string)$toRequest->userAgent()
        ));
    }

    public function Refresh(Request $toRequest): JsonResponse
    {
        $toRequest->validate(['RefreshToken' => ['required', 'string']]);

        return $this->respuestaServicio($this->toAutenticacionService->Refresh(
            (string)$toRequest->input('RefreshToken'),
            $toRequest->ip(),
            (string)$toRequest->userAgent()
        ));
    }

    public function Logout(Request $toRequest): JsonResponse
    {
        return $this->respuestaServicio(
            $this->toAutenticacionService->Logout(auth('api_negocio')->user(), $toRequest->bearerToken())
        );
    }

    public function Perfil(): JsonResponse
    {
        return $this->respuestaServicio($this->toAutenticacionService->Me(auth('api_negocio')->user()));
    }

    public function Permisos(): JsonResponse
    {
        return $this->respuestaServicio($this->toAutenticacionService->Permisos(auth('api_negocio')->user()));
    }

    /**
     * @param array{codigo:int,mensaje:string,datos:array<string,mixed>,errores:array<int,array<string,mixed>>} $laResultado
     */
    private function respuestaServicio(array $laResultado): JsonResponse
    {
        if (($laResultado['codigo'] ?? 500) >= 400) {
            return RespuestaApi::error(
                (string)($laResultado['mensaje'] ?? 'Operacion fallida'),
                (int)($laResultado['codigo'] ?? 500),
                $laResultado['errores'] ?? [],
                $laResultado['datos'] ?? []
            );
        }

        return RespuestaApi::exito(
            (string)($laResultado['mensaje'] ?? 'Operacion exitosa'),
            $laResultado['datos'] ?? [],
            (int)($laResultado['codigo'] ?? 200)
        );
    }
}


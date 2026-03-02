<?php

use App\Models\Usuario;
use App\Modulos\Autenticacion\Services\PermisoService;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('empresa.{Empresa}', function (Usuario $toUsuario, int $tnEmpresa): bool {
    /** @var PermisoService $toPermisoService */
    $toPermisoService = app(PermisoService::class);

    return $toPermisoService->usuarioPerteneceEmpresa((int)$toUsuario->Usuario, $tnEmpresa);
});

Broadcast::channel('maquina.{Maquina}', function (Usuario $toUsuario, int $tnMaquina): bool {
    /** @var PermisoService $toPermisoService */
    $toPermisoService = app(PermisoService::class);

    return $toPermisoService->usuarioPerteneceMaquina((int)$toUsuario->Usuario, $tnMaquina);
});

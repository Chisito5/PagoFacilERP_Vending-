<?php

namespace App\Modulos\Reposicion\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ReposicionService
{
    /**
     * SYSCOOP
     * category: Service
     * package: App\Modulos\Reposicion\Services
     * author: Vladimir Meriles velasquez
     * fecha: 27-02-2026
     * param: int $tnMaquina
     * param: string $tcCodigoSeleccion
     * param: int $tnCantidad
     * param: int $tnUsuarioOperador
     * param: ?int $tnProductoEmpresa
     * param: ?int $tnLote
     * param: ?string $tcObservacion
     * return: \Illuminate\Http\JsonResponse
     *
     * Recarga stock en base a (Maquina + CodigoSeleccion) y registra REPOSICION.
     */
    public function RecargarPorSeleccion(
    int $tnMaquina,
    string $tcCodigoSeleccion,
    int $tnCantidad,
    int $tnUsuarioOperador,
    ?int $tnProductoEmpresa = null,
    ?int $tnLote = null,
    ?string $tcObservacion = null
) {
    return DB::connection('mysqlNegocio')->transaction(function () use (
        $tnMaquina,
        $tcCodigoSeleccion,
        $tnCantidad,
        $tnUsuarioOperador,
        $tnProductoEmpresa,
        $tnLote,
        $tcObservacion
    ) {

        /**
         * SYSCOOP
         * category: Service
         * package: App\Modulos\Reposicion\Services
         * author: Vladimir Meriles velasquez
         * fecha: 27-02-2026
         * param: int $tnMaquina
         * param: string $tcCodigoSeleccion
         * param: int $tnCantidad
         * param: int $tnUsuarioOperador
         * param: ?int $tnProductoEmpresa
         * param: ?int $tnLote
         * param: ?string $tcObservacion
         * return: \Illuminate\Http\JsonResponse
         *
         * Recarga stock en base a (Maquina + CodigoSeleccion),
         * registra REPOSICION (cabecera) y REPOSICIONDETALLE (detalle).
         */

        // 0) Validar UsuarioOperador ANTES de insertar REPOSICION (evita FK 1452)
        $loUsuarioOperador = DB::connection('mysqlNegocio')
            ->table('USUARIO')
            ->where('Usuario', $tnUsuarioOperador)
            ->where('Estado', 1)
            ->first();

        if (!$loUsuarioOperador) {
            return response()->json([
                'Ok' => false,
                'Mensaje' => 'UsuarioOperador no existe o está inactivo'
            ], 400);
        }

        // 1) Buscar CELDA por (Maquina + CodigoSeleccion)
        $loCelda = DB::connection('mysqlNegocio')
            ->table('CELDA')
            ->where('Maquina', $tnMaquina)
            ->where('CodigoSeleccion', $tcCodigoSeleccion)
            ->where('Estado', 1)
            ->first();

        if (!$loCelda) {
            return response()->json([
                'Ok' => false,
                'Mensaje' => 'La celda no existe para esta máquina o está inactiva'
            ], 400);
        }

        $tnCelda = (int)$loCelda->Celda;

        // 2) Bloquear EXISTENCIACELDA para recargar
        $loExistencia = DB::connection('mysqlNegocio')
            ->table('EXISTENCIACELDA')
            ->where('Celda', $tnCelda)
            ->where('Estado', 1)
            ->lockForUpdate()
            ->first();

        if (!$loExistencia) {
            return response()->json([
                'Ok' => false,
                'Mensaje' => 'No existe existencia configurada para esta celda'
            ], 400);
        }

        // 3) Resolver ProductoEmpresa / Lote (si vienen nulos => usar los actuales)
        $tnProductoEmpresaFinal = $tnProductoEmpresa !== null ? $tnProductoEmpresa : (int)$loExistencia->ProductoEmpresa;
        $tnLoteFinal = $tnLote !== null ? $tnLote : (int)$loExistencia->Lote;

        // 4) Actualizar stock
        $tnNuevaCantidad = (int)$loExistencia->CantidadDisponible + $tnCantidad;

        DB::connection('mysqlNegocio')
            ->table('EXISTENCIACELDA')
            ->where('ExistenciaCelda', (int)$loExistencia->ExistenciaCelda)
            ->update([
                'ProductoEmpresa' => $tnProductoEmpresaFinal,
                'Lote' => $tnLoteFinal,
                'CantidadDisponible' => $tnNuevaCantidad
            ]);

        // 5) Registrar cabecera REPOSICION
        $tnReposicion = DB::connection('mysqlNegocio')
            ->table('REPOSICION')
            ->insertGetId([
                'Maquina' => $tnMaquina,
                'UsuarioOperador' => $tnUsuarioOperador,
                'FechaHoraInicio' => now(),
                'FechaHoraFin' => now(),
                'Observacion' => $tcObservacion,
                'Estado' => 1,
                'Usr' => 0,
                'UsrFecha' => now()->toDateString(),
                'UsrHora' => now()->format('H:i:s'),
            ]);

        // 6) Registrar detalle REPOSICIONDETALLE (tu columna es CantidadAgregada ✅)
        if (Schema::connection('mysqlNegocio')->hasTable('REPOSICIONDETALLE')) {
            DB::connection('mysqlNegocio')
                ->table('REPOSICIONDETALLE')
                ->insert([
                    'Reposicion' => $tnReposicion,
                    'Celda' => $tnCelda,
                    'ProductoEmpresa' => $tnProductoEmpresaFinal,
                    'Lote' => $tnLoteFinal,
                    'CantidadAgregada' => $tnCantidad,
                    'Estado' => 1,
                    'Usr' => 0,
                    'UsrFecha' => now()->toDateString(),
                    'UsrHora' => now()->format('H:i:s'),
                ]);
        }

        return response()->json([
            'Ok' => true,
            'Mensaje' => 'Stock recargado correctamente',
            'Datos' => [
                'Reposicion' => $tnReposicion,
                'Maquina' => $tnMaquina,
                'Celda' => $tnCelda,
                'CodigoSeleccion' => $tcCodigoSeleccion,
                'ExistenciaCelda' => (int)$loExistencia->ExistenciaCelda,
                'ProductoEmpresa' => $tnProductoEmpresaFinal,
                'Lote' => $tnLoteFinal,
                'CantidadAgregada' => $tnCantidad,
                'CantidadDisponible' => $tnNuevaCantidad,
            ]
        ]);
    });
}

    /**
     * SYSCOOP
     * category: Service
     * package: App\Modulos\Reposicion\Services
     * author: Vladimir Meriles velasquez
     * fecha: 27-02-2026
     * return: \Illuminate\Http\JsonResponse
     *
     * Lista reposiciones (últimas primero).
     */
    public function Listar()
    {
        $loDatos = DB::connection('mysqlNegocio')
            ->table('REPOSICION')
            ->orderByDesc('Reposicion')
            ->limit(200)
            ->get();

        return response()->json([
            'Ok' => true,
            'Mensaje' => 'Listado de reposiciones',
            'Datos' => $loDatos
        ]);
    }

    /**
     * SYSCOOP
     * category: Service
     * package: App\Modulos\Reposicion\Services
     * author: Vladimir Meriles velasquez
     * fecha: 27-02-2026
     * param: int $tnMaquina
     * return: \Illuminate\Http\JsonResponse
     *
     * Lista reposiciones por máquina.
     */
    public function ListarPorMaquina(int $tnMaquina)
    {
        $loDatos = DB::connection('mysqlNegocio')
            ->table('REPOSICION')
            ->where('Maquina', $tnMaquina)
            ->orderByDesc('Reposicion')
            ->limit(200)
            ->get();

        return response()->json([
            'Ok' => true,
            'Mensaje' => 'Listado de reposiciones por máquina',
            'Datos' => $loDatos
        ]);
    }

    /**
     * SYSCOOP
     * category: Service
     * package: App\Modulos\Reposicion\Services
     * author: Vladimir Meriles velasquez
     * fecha: 27-02-2026
     * param: int $tnReposicion
     * return: \Illuminate\Http\JsonResponse
     *
     * Obtiene una reposición por ID.
     */
    public function Obtener(int $tnReposicion)
    {
        $loDato = DB::connection('mysqlNegocio')
            ->table('REPOSICION')
            ->where('Reposicion', $tnReposicion)
            ->first();

        if (!$loDato) {
            return response()->json([
                'Ok' => false,
                'Mensaje' => 'Reposición no encontrada'
            ], 404);
        }

        return response()->json([
            'Ok' => true,
            'Mensaje' => 'Reposición encontrada',
            'Datos' => $loDato
        ]);
    }
}

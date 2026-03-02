<?php

namespace App\Soporte;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class AuditoriaService
{
    private string $pcConexion = 'mysqlNegocio';

    /**
     * @param array<string,mixed>|null $laAntes
     * @param array<string,mixed>|null $laDespues
     */
    public function registrar(
        string $tcEntidad,
        string|int $tmEntidadId,
        string $tcAccion,
        ?array $laAntes,
        ?array $laDespues,
        int $tnUsuario,
        ?string $tcMotivo = null
    ): void {
        $tdAhora = now();

        DB::connection($this->pcConexion)
            ->table('BITACORA')
            ->insert([
                'TablaAfectada' => strtoupper($tcEntidad),
                'RegistroAfectado' => (string)$tmEntidadId,
                'Accion' => strtoupper($tcAccion),
                'Motivo' => $tcMotivo,
                'DatosAntes' => $laAntes ? json_encode($laAntes, JSON_UNESCAPED_UNICODE) : null,
                'DatosDespues' => $laDespues ? json_encode($laDespues, JSON_UNESCAPED_UNICODE) : null,
                'FechaHora' => $tdAhora,
                'Usuario' => $tnUsuario > 0 ? $tnUsuario : null,
            ]);
    }

    public function historialPorEntidad(
        string $tcEntidad,
        string|int $tmEntidadId,
        int $tnPagina = 1,
        int $tnTamanoPagina = 20
    ): LengthAwarePaginator {
        $tnTamanoPagina = max(1, min($tnTamanoPagina, 200));

        return DB::connection($this->pcConexion)
            ->table('BITACORA as b')
            ->leftJoin('USUARIO as u', 'u.Usuario', '=', 'b.Usuario')
            ->select([
                'b.Bitacora',
                'b.TablaAfectada as Entidad',
                'b.RegistroAfectado as EntidadId',
                'b.Accion',
                'b.Motivo',
                'b.DatosAntes',
                'b.DatosDespues',
                'b.FechaHora',
                'b.Usuario',
                'u.NombreUsuario',
            ])
            ->whereRaw('UPPER(b.TablaAfectada) = ?', [strtoupper($tcEntidad)])
            ->where('b.RegistroAfectado', (string)$tmEntidadId)
            ->orderByDesc('b.Bitacora')
            ->paginate($tnTamanoPagina, ['*'], 'Pagina', max(1, $tnPagina));
    }
}

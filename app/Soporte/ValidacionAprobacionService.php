<?php

namespace App\Soporte;

use Illuminate\Support\Facades\DB;

class ValidacionAprobacionService
{
    private string $pcConexion = 'mysqlNegocio';

    public function estaAprobadaParaEntidad(int $tnAprobacion, string $tcEntidad, string|int|null $tmEntidadId = null): bool
    {
        $toConsulta = DB::connection($this->pcConexion)
            ->table('APROBACION')
            ->where('Aprobacion', $tnAprobacion)
            ->where('Estado', 2)
            ->whereRaw('UPPER(Entidad) = ?', [strtoupper($tcEntidad)]);

        if ($tmEntidadId !== null && (string)$tmEntidadId !== '') {
            $toConsulta->where('EntidadId', (string)$tmEntidadId);
        }

        return $toConsulta->exists();
    }
}

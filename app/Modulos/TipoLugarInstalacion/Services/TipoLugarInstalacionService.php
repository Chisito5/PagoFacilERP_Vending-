<?php

namespace App\Modulos\TipoLugarInstalacion\Services;

use Illuminate\Support\Facades\DB;

class TipoLugarInstalacionService
{
    private string $pcConexion = 'mysqlNegocio';

    public function listar(): array
    {
        return DB::connection($this->pcConexion)
            ->table('TIPOLUGARINSTALACION')
            ->orderBy('NombreTipoLugar')
            ->get()
            ->map(fn(object $toFila): array => [
                'TipoLugarInstalacion' => (int)$toFila->TipoLugarInstalacion,
                'CodigoTipoLugar' => (string)$toFila->CodigoTipoLugar,
                'NombreTipoLugar' => (string)$toFila->NombreTipoLugar,
                'Descripcion' => $toFila->Descripcion !== null ? (string)$toFila->Descripcion : null,
                'Estado' => (int)$toFila->Estado,
                'Usr' => (int)$toFila->Usr,
                'UsrFecha' => (string)$toFila->UsrFecha,
                'UsrHora' => (string)$toFila->UsrHora,
            ])
            ->all();
    }
}

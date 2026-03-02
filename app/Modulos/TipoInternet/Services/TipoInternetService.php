<?php

namespace App\Modulos\TipoInternet\Services;

use Illuminate\Support\Facades\DB;

class TipoInternetService
{
    private string $pcConexion = 'mysqlNegocio';

    public function listar(): array
    {
        return DB::connection($this->pcConexion)
            ->table('TIPOINTERNET')
            ->orderBy('NombreTipoInternet')
            ->get()
            ->map(fn(object $toFila): array => [
                'TipoInternet' => (int)$toFila->TipoInternet,
                'CodigoTipoInternet' => (string)$toFila->CodigoTipoInternet,
                'NombreTipoInternet' => (string)$toFila->NombreTipoInternet,
                'Descripcion' => $toFila->Descripcion !== null ? (string)$toFila->Descripcion : null,
                'Estado' => (int)$toFila->Estado,
                'Usr' => (int)$toFila->Usr,
                'UsrFecha' => (string)$toFila->UsrFecha,
                'UsrHora' => (string)$toFila->UsrHora,
            ])
            ->all();
    }
}

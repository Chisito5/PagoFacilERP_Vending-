<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use RuntimeException;

class EstadoCatalogo
{
    /**
     * Cache por request para evitar consultas repetidas.
     *
     * @var array<string, int>
     */
    private array $laCache = [];

    public function obtenerId(string $tcEntidad, int $tnCodigoEstado): int
    {
        $tcEntidad = strtoupper(trim($tcEntidad));
        $tcKey = $tcEntidad . ':' . $tnCodigoEstado;

        if (isset($this->laCache[$tcKey])) {
            return $this->laCache[$tcKey];
        }

        $loEstado = DB::connection('mysqlNegocio')
            ->table('ESTADO')
            ->select('Estado')
            ->whereRaw('UPPER(Entidad) = ?', [$tcEntidad])
            ->where('CodigoEstado', $tnCodigoEstado)
            ->first();

        if (!$loEstado) {
            throw new RuntimeException("Estado no configurado para Entidad={$tcEntidad}, CodigoEstado={$tnCodigoEstado}");
        }

        $this->laCache[$tcKey] = (int)$loEstado->Estado;

        return $this->laCache[$tcKey];
    }
}

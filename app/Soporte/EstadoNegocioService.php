<?php

namespace App\Soporte;

use App\Support\EstadoCatalogo;

class EstadoNegocioService
{
    public function __construct(private EstadoCatalogo $toEstadoCatalogo)
    {
    }

    public function activoGeneral(): int
    {
        return $this->toEstadoCatalogo->obtenerId('GENERAL', 1);
    }

    public function inactivoGeneral(): int
    {
        return $this->toEstadoCatalogo->obtenerId('GENERAL', 2);
    }

    public function estado(string $tcEntidad, int $tnCodigo): int
    {
        return $this->toEstadoCatalogo->obtenerId($tcEntidad, $tnCodigo);
    }
}

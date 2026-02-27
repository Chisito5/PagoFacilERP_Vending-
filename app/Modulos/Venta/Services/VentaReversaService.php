<?php

namespace App\Modulos\Venta\Services;

class VentaReversaService
{
    public function __construct(private VentaService $toVentaService)
    {
    }

    public function Reversa(int $tnVenta, string $tcMotivo)
    {
        return $this->toVentaService->Reversar($tnVenta, $tcMotivo);
    }
}

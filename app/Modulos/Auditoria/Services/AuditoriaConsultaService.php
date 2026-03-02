<?php

namespace App\Modulos\Auditoria\Services;

use App\Soporte\AuditoriaService as SoporteAuditoriaService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class AuditoriaConsultaService
{
    public function __construct(private SoporteAuditoriaService $toAuditoria)
    {
    }

    public function Historial(string $tcEntidad, string|int $tmEntidadId, int $tnPagina, int $tnTamanoPagina): LengthAwarePaginator
    {
        return $this->toAuditoria->historialPorEntidad($tcEntidad, $tmEntidadId, $tnPagina, $tnTamanoPagina);
    }
}

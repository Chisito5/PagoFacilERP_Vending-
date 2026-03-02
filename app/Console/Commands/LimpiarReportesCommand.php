<?php

namespace App\Console\Commands;

use App\Modulos\Reporte\Services\ReporteService;
use Illuminate\Console\Command;

class LimpiarReportesCommand extends Command
{
    protected $signature = 'reportes:limpiar {--dias=7}';

    protected $description = 'Marca y elimina archivos de reportes expirados';

    public function handle(ReporteService $toService): int
    {
        $tnDias = (int)$this->option('dias');
        if ($tnDias < 1) {
            $tnDias = 7;
        }

        $tn = $toService->limpiarExpirados($tnDias);
        $this->info('Reportes expirados limpiados: ' . $tn);

        return self::SUCCESS;
    }
}

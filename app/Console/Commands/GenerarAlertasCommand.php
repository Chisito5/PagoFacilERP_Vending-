<?php

namespace App\Console\Commands;

use App\Modulos\Alerta\Services\AlertaService;
use Illuminate\Console\Command;

class GenerarAlertasCommand extends Command
{
    protected $signature = 'alertas:generar';

    protected $description = 'Genera alertas operativas en base a reglas de umbral';

    public function handle(AlertaService $toService): int
    {
        $la = $toService->generarAutomaticas();

        $this->info('Reglas: ' . (int)($la['Reglas'] ?? 0));
        $this->info('Generadas: ' . (int)($la['Generadas'] ?? 0));
        $this->info('Saltadas: ' . (int)($la['Saltadas'] ?? 0));
        $this->info('Errores: ' . (int)($la['Errores'] ?? 0));

        return self::SUCCESS;
    }
}

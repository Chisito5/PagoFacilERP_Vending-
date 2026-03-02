<?php

namespace App\Console\Commands;

use App\Modulos\IntegracionIot\Services\IntegracionIotService;
use Illuminate\Console\Command;

class ReencolarEventosIotCommand extends Command
{
    protected $signature = 'iot:evento:reencolar {--max-intentos=5} {--limite=100}';

    protected $description = 'Reencola eventos IoT pendientes o con error para reintento controlado';

    public function handle(IntegracionIotService $toService): int
    {
        $tnMaxIntentos = max(1, (int)$this->option('max-intentos'));
        $tnLimite = max(1, min((int)$this->option('limite'), 1000));

        $tn = $toService->reencolarPendientes($tnMaxIntentos, $tnLimite);
        $this->info('Eventos IoT reencolados: ' . $tn);

        return self::SUCCESS;
    }
}

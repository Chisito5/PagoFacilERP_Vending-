<?php

namespace App\Console\Commands;

use App\Modulos\Reserva\Services\ReservaService;
use Illuminate\Console\Command;

class ExpirarReservasCommand extends Command
{
    protected $signature = 'reservas:expirar {--lote=100 : Cantidad maxima de reservas por ejecucion}';

    protected $description = 'Expira reservas vencidas y libera stock reservado';

    public function handle(ReservaService $toReservaService): int
    {
        $tnLote = (int)$this->option('lote');
        if ($tnLote <= 0) {
            $this->error('El parametro --lote debe ser mayor a 0');
            return self::FAILURE;
        }

        $laTotales = $toReservaService->ExpirarVencidas($tnLote);

        $this->info('Ejecucion de reservas:expirar finalizada');
        $this->line('Procesadas: ' . $laTotales['procesadas']);
        $this->line('Expiradas: ' . $laTotales['expiradas']);
        $this->line('Saltadas: ' . $laTotales['saltadas']);
        $this->line('Errores: ' . $laTotales['errores']);

        return self::SUCCESS;
    }
}

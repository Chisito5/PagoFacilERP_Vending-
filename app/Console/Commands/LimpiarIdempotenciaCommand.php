<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class LimpiarIdempotenciaCommand extends Command
{
    protected $signature = 'idempotencia:limpiar {--dias=7 : Dias de retencion}';

    protected $description = 'Limpia registros antiguos de IDEMPOTENCIA';

    public function handle(): int
    {
        $tnDias = (int)$this->option('dias');
        if ($tnDias <= 0) {
            $this->error('El valor --dias debe ser mayor a 0');
            return self::FAILURE;
        }

        $tdLimite = now()->subDays($tnDias);

        $tnEliminados = DB::connection('mysqlNegocio')
            ->table('IDEMPOTENCIA')
            ->where('CreadoEn', '<', $tdLimite)
            ->delete();

        $this->info("Registros eliminados de IDEMPOTENCIA: {$tnEliminados}");
        return self::SUCCESS;
    }
}

<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillLoteCantidadInicialCommand extends Command
{
    protected $signature = 'lotes:backfill-cantidad-inicial {--solo-cero=1 : 1=solo lotes con CantidadInicial en 0, 0=forzar recalculo total}';

    protected $description = 'Backfill de LOTE.CantidadInicial usando asignacion actual en EXISTENCIACELDA';

    public function handle(): int
    {
        $lbSoloCero = ((int)$this->option('solo-cero')) === 1;

        $lsSql = "
            UPDATE LOTE l
            LEFT JOIN (
                SELECT Lote, SUM(CantidadDisponible + CantidadReservada) AS Asignado
                FROM EXISTENCIACELDA
                WHERE Lote IS NOT NULL
                GROUP BY Lote
            ) x ON x.Lote = l.Lote
            SET l.CantidadInicial = COALESCE(x.Asignado, 0)
        ";

        if ($lbSoloCero) {
            $lsSql .= " WHERE l.CantidadInicial = 0";
        }

        $tnAfectadas = DB::connection('mysqlNegocio')->affectingStatement($lsSql);
        $this->info('Backfill completado. Filas actualizadas: ' . $tnAfectadas);

        return self::SUCCESS;
    }
}

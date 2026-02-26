<?php

namespace App\Modulos\PlanogramaCelda\Services;

use Illuminate\Support\Facades\DB;

class PlanogramaCeldaService
{
    private string $conn = 'mysqlNegocio';

    public function Listar()
    {
        return DB::connection($this->conn)
            ->table('PLANOGRAMACELDA')
            ->orderBy('PlanogramaCelda', 'desc')
            ->get();
    }

    public function ListarPorCelda(int $IdCelda)
    {
        return DB::connection($this->conn)
            ->table('PLANOGRAMACELDA')
            ->where('Celda', $IdCelda)
            ->orderBy('PlanogramaCelda', 'desc')
            ->get();
    }

    public function ListarPorPlanograma(int $IdPlanograma)
    {
        return DB::connection($this->conn)
            ->table('PLANOGRAMACELDA')
            ->where('Planograma', $IdPlanograma)
            ->orderBy('PlanogramaCelda', 'desc')
            ->get();
    }
}

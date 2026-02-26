<?php

namespace App\Modulos\ExistenciaCelda\Services;

use Illuminate\Support\Facades\DB;

class ExistenciaCeldaService
{
    private string $conn = 'mysqlNegocio';

    public function Listar()
    {
        return DB::connection($this->conn)
            ->table('EXISTENCIACELDA')
            ->orderBy('ExistenciaCelda', 'desc')
            ->get();
    }

    public function ListarPorCelda(int $IdCelda)
    {
        return DB::connection($this->conn)
            ->table('EXISTENCIACELDA')
            ->where('Celda', $IdCelda)
            ->orderBy('ExistenciaCelda', 'desc')
            ->get();
    }
}

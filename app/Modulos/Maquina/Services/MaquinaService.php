<?php

namespace App\Modulos\Maquina\Services;

use Illuminate\Support\Facades\DB;

class MaquinaService
{
    private string $conn = 'mysqlNegocio';

    public function Listar()
    {
        return DB::connection($this->conn)
            ->table('MAQUINA')
            ->orderBy('Maquina', 'desc')
            ->get();
    }

    public function ListarCeldas(int $IdMaquina)
    {
        return DB::connection($this->conn)
            ->table('CELDA')
            ->where('Maquina', $IdMaquina)
            ->orderBy('Celda', 'asc')
            ->get();
    }
}

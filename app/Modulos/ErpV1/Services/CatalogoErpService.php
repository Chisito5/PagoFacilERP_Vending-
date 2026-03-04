<?php

namespace App\Modulos\ErpV1\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CatalogoErpService
{
    private string $pcConexion = 'mysqlNegocio';

    public function maquinas(int $tnPagina, int $tnTamanoPagina): LengthAwarePaginator
    {
        return DB::connection($this->pcConexion)
            ->table('MAQUINA')
            ->select(['Maquina as IdMaquina', 'CodigoMaquina', 'Marca', 'Modelo', 'Estado'])
            ->orderByDesc('Maquina')
            ->paginate($tnTamanoPagina, ['*'], 'Pagina', $tnPagina);
    }

    public function productos(int $tnPagina, int $tnTamanoPagina): LengthAwarePaginator
    {
        return DB::connection($this->pcConexion)
            ->table('PRODUCTO')
            ->select(['Producto as IdProducto', 'CodigoProducto', 'NombreProducto', 'Precio', 'Estado'])
            ->orderByDesc('Producto')
            ->paginate($tnTamanoPagina, ['*'], 'Pagina', $tnPagina);
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function ofertas(): array
    {
        if (!Schema::connection($this->pcConexion)->hasTable('OFERTA')) {
            return [];
        }

        return DB::connection($this->pcConexion)
            ->table('OFERTA')
            ->select(['Oferta as IdOferta', 'NombreOferta', 'Estado'])
            ->orderByDesc('Oferta')
            ->get()
            ->map(fn ($toFila) => [
                'IdOferta' => (int)$toFila->IdOferta,
                'NombreOferta' => (string)$toFila->NombreOferta,
                'Estado' => (int)$toFila->Estado,
            ])
            ->all();
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function metodosPago(): array
    {
        return DB::connection($this->pcConexion)
            ->table('METODOPAGO')
            ->select(['MetodoPago as IdMetodoPago', 'NombreMetodoPago', 'Estado'])
            ->orderBy('MetodoPago')
            ->get()
            ->map(fn ($toFila) => [
                'IdMetodoPago' => (int)$toFila->IdMetodoPago,
                'NombreMetodoPago' => (string)$toFila->NombreMetodoPago,
                'Estado' => (int)$toFila->Estado,
            ])
            ->all();
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function estadosTransaccion(): array
    {
        return DB::connection($this->pcConexion)
            ->table('ESTADO')
            ->where('Entidad', 'VENTA')
            ->orderBy('CodigoEstado')
            ->get()
            ->map(fn ($toFila) => [
                'Estado' => (int)$toFila->Estado,
                'CodigoEstado' => (int)$toFila->CodigoEstado,
                'NombreEstado' => (string)$toFila->NombreEstado,
            ])
            ->all();
    }
}


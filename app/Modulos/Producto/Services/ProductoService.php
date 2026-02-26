<?php

namespace App\Modulos\Producto\Services;

use Illuminate\Support\Facades\DB;

class ProductoService
{
    private string $conn = 'mysqlNegocio';

    public function Listar(?int $empresa = null)
    {
        $q = DB::connection($this->conn)
            ->table('PRODUCTO')
            ->select([
                'Producto',
                'Empresa',
                'CodigoSku',
                'CodigoBarra',
                'NombreProducto',
                'Descripcion',
                'Marca',
                'ContenidoCantidad',
                'UnidadMedidaContenido',
                'PesoGramos',
                'SubgrupoProducto',
                'Estado',
                'Usr',
                'UsrFecha',
                'UsrHora',
            ]);

        if (!is_null($empresa)) {
            $q->where('Empresa', $empresa);
        }

        return $q->orderBy('Producto', 'desc')->get();
    }
}

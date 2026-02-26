<?php

namespace App\Modulos\Producto\Controllers;

use App\Http\Controllers\Controller;
use App\Modulos\Producto\Services\ProductoService;
use Illuminate\Http\Request;

class ProductoController extends Controller
{
    public function __construct(private ProductoService $service) {}

    public function Listar(Request $request)
    {
        $empresa = $request->query('Empresa');
        $empresa = is_null($empresa) ? null : (int)$empresa;

        $datos = $this->service->Listar($empresa);

        return response()->json([
            'Ok' => true,
            'Mensaje' => $empresa ? "Listado de productos (filtrado por Empresa=$empresa)" : 'Listado de productos',
            'Datos' => $datos
        ]);
    }
}

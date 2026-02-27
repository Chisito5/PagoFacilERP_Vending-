<?php

namespace App\Modulos\Producto\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Modulos\Producto\Services\ProductoService;

/**
 *
 * Controlador que gestiona la consulta de Productos.
 *
 * @category     PagoFacil
 * @package      Producto
 * @author       Vladimir Meriles Velasquez
 * @fecha        26-02-2026
 */
class ProductoController extends Controller
{
    protected ProductoService $poProductoService;

    public function __construct(ProductoService $toProductoService)
    {
        $this->poProductoService = $toProductoService;
    }

    /**
     * Lista productos (opcionalmente filtrando por Empresa).
     *
     * @method      Listar()
     * @author      Vladimir Meriles Velasquez
     * @fecha       26-02-2026
     * @param       Request $toRequest
     */
    public function Listar(Request $toRequest)
    {
        $tnEmpresa = $toRequest->query('Empresa');
        $tnEmpresa = is_null($tnEmpresa) ? null : (int)$tnEmpresa;

        $loDatos = $this->poProductoService->Listar($tnEmpresa);

        return response()->json([
            'Ok' => true,
            'Mensaje' => $tnEmpresa ? "Listado de productos (filtrado por Empresa=$tnEmpresa)" : 'Listado de productos',
            'Datos' => $loDatos
        ]);
    }
}

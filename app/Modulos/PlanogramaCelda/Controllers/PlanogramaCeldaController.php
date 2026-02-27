<?php

namespace App\Modulos\PlanogramaCelda\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Modulos\PlanogramaCelda\Services\PlanogramaCeldaService;

/**
 *
 * Controlador que gestiona la consulta del Planograma por Celda.
 *
 * @category     PagoFacil
 * @package      PlanogramaCelda
 * @author       Vladimir Meriles Velasquez
 * @fecha        26-02-2026
 */
class PlanogramaCeldaController extends Controller
{
    protected PlanogramaCeldaService $poPlanogramaCeldaService;

    public function __construct(PlanogramaCeldaService $toPlanogramaCeldaService)
    {
        $this->poPlanogramaCeldaService = $toPlanogramaCeldaService;
    }

    /**
     * Lista planograma celda.
     *
     * @method      Listar()
     * @author      Vladimir Meriles Velasquez
     * @fecha       26-02-2026
     */
    public function Listar()
    {
        $loDatos = $this->poPlanogramaCeldaService->Listar();

        return response()->json([
            'Ok' => true,
            'Mensaje' => 'Listado de planograma por celda',
            'Datos' => $loDatos
        ]);
    }

    /**
     * Lista planograma celda por Celda.
     *
     * @method      ListarPorCelda()
     * @author      Vladimir Meriles Velasquez
     * @fecha       26-02-2026
     * @param       int $tnCelda
     */
    public function ListarPorCelda(int $tnCelda)
    {
        $loDatos = $this->poPlanogramaCeldaService->ListarPorCelda($tnCelda);

        return response()->json([
            'Ok' => true,
            'Mensaje' => 'Listado de planograma por celda (filtrado)',
            'Datos' => $loDatos
        ]);
    }

    /**
     * Lista planograma celda por Planograma.
     *
     * @method      ListarPorPlanograma()
     * @author      Vladimir Meriles Velasquez
     * @fecha       26-02-2026
     * @param       int $tnPlanograma
     */
    public function ListarPorPlanograma(int $tnPlanograma)
    {
        $loDatos = $this->poPlanogramaCeldaService->ListarPorPlanograma($tnPlanograma);

        return response()->json([
            'Ok' => true,
            'Mensaje' => 'Listado de planograma por Planograma (filtrado)',
            'Datos' => $loDatos
        ]);
    }

    /**
     * SYSCOOP
     * category: Controller
     * package: App\Modulos\PlanogramaCelda\Controllers
     * author: Vladimir Meriles velasquez
     * fecha: 27-02-2026
     * param: Request $toRequest
     * param: int $tnPlanogramaCelda
     * return: \Illuminate\Http\JsonResponse
     *
     * Actualiza el PrecioVenta de una fila PLANOGRAMACELDA.
     */
    public function ActualizarPrecio(Request $toRequest, int $tnPlanogramaCelda)
    {
        $toRequest->validate([
            'PrecioVenta' => ['required', 'numeric', 'min:0'],
        ]);

        $tdPrecioVenta = (float)$toRequest->input('PrecioVenta');

        $loService = new PlanogramaCeldaService();
        return $loService->ActualizarPrecio($tnPlanogramaCelda, $tdPrecioVenta);
    }
}

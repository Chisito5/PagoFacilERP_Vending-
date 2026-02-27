<?php

namespace App\Modulos\PlanogramaCelda\Services;

use Illuminate\Support\Facades\DB;

/**
 *
 * Servicio que gestiona la consulta de Planograma por Celda.
 *
 * @category     PagoFacil
 * @package      PlanogramaCelda
 * @author       Vladimir Meriles Velasquez
 * @fecha        26-02-2026
 */
class PlanogramaCeldaService
{
    private string $pcConexion = 'mysqlNegocio';

    /**
     * Lista planograma celda.
     *
     * @method      Listar()
     * @author      Vladimir Meriles Velasquez
     * @fecha       26-02-2026
     */
    public function Listar()
    {
        return DB::connection($this->pcConexion)
            ->table('PLANOGRAMACELDA')
            ->orderBy('PlanogramaCelda', 'desc')
            ->get();
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
        return DB::connection($this->pcConexion)
            ->table('PLANOGRAMACELDA')
            ->where('Celda', $tnCelda)
            ->orderBy('PlanogramaCelda', 'desc')
            ->get();
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
        return DB::connection($this->pcConexion)
            ->table('PLANOGRAMACELDA')
            ->where('Planograma', $tnPlanograma)
            ->orderBy('PlanogramaCelda', 'desc')
            ->get();
    }
    /**
     * SYSCOOP
     * category: Service
     * package: App\Modulos\PlanogramaCelda\Services
     * author: Vladimir Meriles velasquez
     * fecha: 27-02-2026
     * param: int $tnPlanogramaCelda
     * param: float $tdPrecioVenta
     * return: \Illuminate\Http\JsonResponse
     *
     * Actualiza PrecioVenta en PLANOGRAMACELDA por ID (PlanogramaCelda).
     */
    public function ActualizarPrecio(int $tnPlanogramaCelda, float $tdPrecioVenta)
    {
        $lnAhoraFecha = now()->toDateString();
        $lcAhoraHora = now()->format('H:i:s');

        $lnAfectadas = DB::connection('mysqlNegocio')
            ->table('PLANOGRAMACELDA')
            ->where('PlanogramaCelda', $tnPlanogramaCelda)
            ->where('Estado', 1)
            ->update([
                'PrecioVenta' => $tdPrecioVenta,
                'Usr' => 0,
                'UsrFecha' => $lnAhoraFecha,
                'UsrHora' => $lcAhoraHora,
            ]);

        if ($lnAfectadas <= 0) {
            return response()->json([
                'Ok' => false,
                'Mensaje' => 'No se encontró el registro activo de PLANOGRAMACELDA'
            ], 404);
        }

        $loActualizado = DB::connection('mysqlNegocio')
            ->table('PLANOGRAMACELDA')
            ->where('PlanogramaCelda', $tnPlanogramaCelda)
            ->first();

        return response()->json([
            'Ok' => true,
            'Mensaje' => 'Precio actualizado correctamente',
            'Datos' => $loActualizado
        ]);
    }
}

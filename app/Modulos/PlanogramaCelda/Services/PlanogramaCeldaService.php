<?php

namespace App\Modulos\PlanogramaCelda\Services;

use App\Soporte\AuditoriaService;
use App\Soporte\ControlVersionService;
use App\Support\EstadoCatalogo;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class PlanogramaCeldaService
{
    private string $pcConexion = 'mysqlNegocio';

    public function __construct(
        private EstadoCatalogo $toEstadoCatalogo,
        private ControlVersionService $toControlVersion,
        private AuditoriaService $toAuditoria
    ) {
    }

    public function Listar(?int $tnPlanograma, ?int $tnCelda, ?int $tnEstado, int $tnPagina, int $tnTamanoPagina): LengthAwarePaginator
    {
        $tnTamanoPagina = max(1, min($tnTamanoPagina, 200));

        $toConsulta = DB::connection($this->pcConexion)
            ->table('PLANOGRAMACELDA')
            ->select([
                'PlanogramaCelda', 'Planograma', 'Celda', 'ProductoEmpresa', 'PrecioVenta',
                'StockMinimo', 'StockMaximo', 'PlanogramaCeldaPrincipal', 'Estado', 'Usr', 'UsrFecha', 'UsrHora'
            ])
            ->orderByDesc('PlanogramaCelda');

        if ($tnPlanograma !== null && $tnPlanograma > 0) {
            $toConsulta->where('Planograma', $tnPlanograma);
        }
        if ($tnCelda !== null && $tnCelda > 0) {
            $toConsulta->where('Celda', $tnCelda);
        }
        if ($tnEstado !== null && $tnEstado > 0) {
            $toConsulta->where('Estado', $tnEstado);
        }

        return $toConsulta->paginate($tnTamanoPagina, ['*'], 'Pagina', max(1, $tnPagina));
    }

    public function Obtener(int $tnPlanogramaCelda): ?array
    {
        $loFila = DB::connection($this->pcConexion)
            ->table('PLANOGRAMACELDA')
            ->where('PlanogramaCelda', $tnPlanogramaCelda)
            ->first();

        return $loFila ? $this->normalizarFila($loFila) : null;
    }

    /** @param array<string,mixed> $taDatos */
    public function Crear(array $taDatos, int $tnUsuario): array
    {
        $tdAhora = now();
        $tnEstadoActivo = $this->toEstadoCatalogo->obtenerId('GENERAL', 1);

        $tnId = DB::connection($this->pcConexion)->table('PLANOGRAMACELDA')->insertGetId([
            'Planograma' => (int)$taDatos['Planograma'],
            'Celda' => (int)$taDatos['Celda'],
            'ProductoEmpresa' => $taDatos['ProductoEmpresa'] ?? null,
            'PrecioVenta' => $taDatos['PrecioVenta'] ?? null,
            'StockMinimo' => (int)($taDatos['StockMinimo'] ?? 0),
            'StockMaximo' => (int)($taDatos['StockMaximo'] ?? 0),
            'PlanogramaCeldaPrincipal' => $taDatos['PlanogramaCeldaPrincipal'] ?? null,
            'Estado' => (int)($taDatos['Estado'] ?? $tnEstadoActivo),
            'Usr' => $tnUsuario,
            'UsrFecha' => $tdAhora->toDateString(),
            'UsrHora' => $tdAhora->format('H:i:s'),
        ]);

        $loNuevo = DB::connection($this->pcConexion)->table('PLANOGRAMACELDA')->where('PlanogramaCelda', $tnId)->first();
        $laNuevo = $this->normalizarFila($loNuevo);
        $this->toAuditoria->registrar('PLANOGRAMACELDA', $tnId, 'CREAR', null, $laNuevo, $tnUsuario, $taDatos['Motivo'] ?? null);

        return $laNuevo;
    }

    /** @param array<string,mixed> $taDatos */
    public function Actualizar(int $tnId, array $taDatos, string $tcVersion, int $tnUsuario, bool $lbParcial): array
    {
        return DB::connection($this->pcConexion)->transaction(function () use ($tnId, $taDatos, $tcVersion, $tnUsuario, $lbParcial): array {
            $loActual = DB::connection($this->pcConexion)->table('PLANOGRAMACELDA')->where('PlanogramaCelda', $tnId)->lockForUpdate()->first();
            if (!$loActual) {
                return ['Estado' => 'NO_ENCONTRADO'];
            }
            if (!$this->toControlVersion->coincide($tcVersion, $loActual)) {
                return ['Estado' => 'CONFLICTO_VERSION', 'Actual' => $this->normalizarFila($loActual)];
            }

            $laCampos = ['Planograma', 'Celda', 'ProductoEmpresa', 'PrecioVenta', 'StockMinimo', 'StockMaximo', 'PlanogramaCeldaPrincipal', 'Estado'];
            $laUpdate = [];
            foreach ($laCampos as $tcCampo) {
                if (array_key_exists($tcCampo, $taDatos)) {
                    $laUpdate[$tcCampo] = $taDatos[$tcCampo];
                } elseif (!$lbParcial) {
                    $laUpdate[$tcCampo] = $loActual->{$tcCampo};
                }
            }

            $tdAhora = now();
            $laUpdate['Usr'] = $tnUsuario;
            $laUpdate['UsrFecha'] = $tdAhora->toDateString();
            $laUpdate['UsrHora'] = $tdAhora->format('H:i:s');

            DB::connection($this->pcConexion)->table('PLANOGRAMACELDA')->where('PlanogramaCelda', $tnId)->update($laUpdate);

            $loNuevo = DB::connection($this->pcConexion)->table('PLANOGRAMACELDA')->where('PlanogramaCelda', $tnId)->first();
            $laAntes = $this->normalizarFila($loActual);
            $laDespues = $this->normalizarFila($loNuevo);
            $this->toAuditoria->registrar('PLANOGRAMACELDA', $tnId, 'ACTUALIZAR', $laAntes, $laDespues, $tnUsuario, $taDatos['Motivo'] ?? null);

            return ['Estado' => 'OK', 'Datos' => $laDespues];
        });
    }

    public function EliminarLogico(int $tnId, string $tcVersion, int $tnUsuario, ?string $tcMotivo): array
    {
        $tnEstadoInactivo = $this->toEstadoCatalogo->obtenerId('GENERAL', 2);

        return DB::connection($this->pcConexion)->transaction(function () use ($tnId, $tcVersion, $tnUsuario, $tnEstadoInactivo, $tcMotivo): array {
            $loActual = DB::connection($this->pcConexion)->table('PLANOGRAMACELDA')->where('PlanogramaCelda', $tnId)->lockForUpdate()->first();
            if (!$loActual) {
                return ['Estado' => 'NO_ENCONTRADO'];
            }
            if (!$this->toControlVersion->coincide($tcVersion, $loActual)) {
                return ['Estado' => 'CONFLICTO_VERSION', 'Actual' => $this->normalizarFila($loActual)];
            }

            $tdAhora = now();
            DB::connection($this->pcConexion)->table('PLANOGRAMACELDA')->where('PlanogramaCelda', $tnId)->update([
                'Estado' => $tnEstadoInactivo,
                'Usr' => $tnUsuario,
                'UsrFecha' => $tdAhora->toDateString(),
                'UsrHora' => $tdAhora->format('H:i:s'),
            ]);

            $loNuevo = DB::connection($this->pcConexion)->table('PLANOGRAMACELDA')->where('PlanogramaCelda', $tnId)->first();
            $laAntes = $this->normalizarFila($loActual);
            $laDespues = $this->normalizarFila($loNuevo);
            $this->toAuditoria->registrar('PLANOGRAMACELDA', $tnId, 'ELIMINAR_LOGICO', $laAntes, $laDespues, $tnUsuario, $tcMotivo);

            return ['Estado' => 'OK', 'Datos' => $laDespues];
        });
    }

    public function ActualizarPrecio(int $tnPlanogramaCelda, float $tdPrecioVenta, string $tcVersion, int $tnUsuario, ?string $tcMotivo): array
    {
        return $this->Actualizar(
            $tnPlanogramaCelda,
            ['PrecioVenta' => $tdPrecioVenta, 'Motivo' => $tcMotivo],
            $tcVersion,
            $tnUsuario,
            true
        );
    }

    private function normalizarFila(object $toFila): array
    {
        $la = (array)$toFila;
        $la['Version'] = $this->toControlVersion->versionDesdeFila($toFila);
        return $la;
    }
}

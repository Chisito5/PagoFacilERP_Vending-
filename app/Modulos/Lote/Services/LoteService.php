<?php

namespace App\Modulos\Lote\Services;

use App\Soporte\AuditoriaService;
use App\Soporte\ControlVersionService;
use App\Support\EstadoCatalogo;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class LoteService
{
    private string $pcConexion = 'mysqlNegocio';

    public function __construct(
        private EstadoCatalogo $toEstadoCatalogo,
        private ControlVersionService $toControlVersion,
        private AuditoriaService $toAuditoria
    ) {
    }

    public function Listar(?int $tnProducto, ?int $tnEstado, ?string $tcBusqueda, int $tnPagina, int $tnTamanoPagina): LengthAwarePaginator
    {
        $tnTamanoPagina = max(1, min($tnTamanoPagina, 200));

        $toConsulta = DB::connection($this->pcConexion)
            ->table('LOTE')
            ->select([
                'Lote', 'Producto', 'CodigoLote', 'FechaVencimiento', 'FechaRegistro', 'CantidadInicial',
                'Estado', 'Usr', 'UsrFecha', 'UsrHora'
            ])
            ->orderByDesc('Lote');

        if ($tnProducto !== null && $tnProducto > 0) {
            $toConsulta->where('Producto', $tnProducto);
        }

        if ($tnEstado !== null && $tnEstado > 0) {
            $toConsulta->where('Estado', $tnEstado);
        }

        if ($tcBusqueda !== null && trim($tcBusqueda) !== '') {
            $toConsulta->where('CodigoLote', 'like', '%' . trim($tcBusqueda) . '%');
        }

        return $toConsulta->paginate($tnTamanoPagina, ['*'], 'Pagina', max(1, $tnPagina));
    }

    public function Obtener(int $tnLote): ?array
    {
        $loFila = DB::connection($this->pcConexion)->table('LOTE')->where('Lote', $tnLote)->first();
        return $loFila ? $this->normalizarFila($loFila) : null;
    }

    /** @param array<string,mixed> $taDatos */
    public function Crear(array $taDatos, int $tnUsuario): array
    {
        $tdAhora = now();
        $tnEstadoActivo = $this->toEstadoCatalogo->obtenerId('GENERAL', 1);

        $tnId = DB::connection($this->pcConexion)->table('LOTE')->insertGetId([
            'Producto' => (int)$taDatos['Producto'],
            'CodigoLote' => $taDatos['CodigoLote'] ?? null,
            'FechaVencimiento' => $taDatos['FechaVencimiento'] ?? null,
            'FechaRegistro' => $taDatos['FechaRegistro'] ?? $tdAhora->toDateString(),
            'CantidadInicial' => (int)$taDatos['CantidadInicial'],
            'Estado' => (int)($taDatos['Estado'] ?? $tnEstadoActivo),
            'Usr' => $tnUsuario,
            'UsrFecha' => $tdAhora->toDateString(),
            'UsrHora' => $tdAhora->format('H:i:s'),
        ]);

        $loNuevo = DB::connection($this->pcConexion)->table('LOTE')->where('Lote', $tnId)->first();
        $laNuevo = $this->normalizarFila($loNuevo);
        $this->toAuditoria->registrar('LOTE', $tnId, 'CREAR', null, $laNuevo, $tnUsuario, $taDatos['Motivo'] ?? null);

        return $laNuevo;
    }

    /** @param array<string,mixed> $taDatos */
    public function Actualizar(int $tnLote, array $taDatos, string $tcVersion, int $tnUsuario, bool $lbParcial): array
    {
        return DB::connection($this->pcConexion)->transaction(function () use ($tnLote, $taDatos, $tcVersion, $tnUsuario, $lbParcial): array {
            $loActual = DB::connection($this->pcConexion)->table('LOTE')->where('Lote', $tnLote)->lockForUpdate()->first();
            if (!$loActual) {
                return ['Estado' => 'NO_ENCONTRADO'];
            }
            if (!$this->toControlVersion->coincide($tcVersion, $loActual)) {
                return ['Estado' => 'CONFLICTO_VERSION', 'Actual' => $this->normalizarFila($loActual)];
            }

            $laCampos = ['Producto', 'CodigoLote', 'FechaVencimiento', 'FechaRegistro', 'CantidadInicial', 'Estado'];
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

            DB::connection($this->pcConexion)->table('LOTE')->where('Lote', $tnLote)->update($laUpdate);

            $loNuevo = DB::connection($this->pcConexion)->table('LOTE')->where('Lote', $tnLote)->first();
            $laAntes = $this->normalizarFila($loActual);
            $laDespues = $this->normalizarFila($loNuevo);
            $this->toAuditoria->registrar('LOTE', $tnLote, 'ACTUALIZAR', $laAntes, $laDespues, $tnUsuario, $taDatos['Motivo'] ?? null);

            return ['Estado' => 'OK', 'Datos' => $laDespues];
        });
    }

    public function EliminarLogico(int $tnLote, string $tcVersion, int $tnUsuario, ?string $tcMotivo): array
    {
        $tnEstadoInactivo = $this->toEstadoCatalogo->obtenerId('GENERAL', 2);

        return DB::connection($this->pcConexion)->transaction(function () use ($tnLote, $tcVersion, $tnUsuario, $tnEstadoInactivo, $tcMotivo): array {
            $loActual = DB::connection($this->pcConexion)->table('LOTE')->where('Lote', $tnLote)->lockForUpdate()->first();
            if (!$loActual) {
                return ['Estado' => 'NO_ENCONTRADO'];
            }
            if (!$this->toControlVersion->coincide($tcVersion, $loActual)) {
                return ['Estado' => 'CONFLICTO_VERSION', 'Actual' => $this->normalizarFila($loActual)];
            }

            $tdAhora = now();
            DB::connection($this->pcConexion)->table('LOTE')->where('Lote', $tnLote)->update([
                'Estado' => $tnEstadoInactivo,
                'Usr' => $tnUsuario,
                'UsrFecha' => $tdAhora->toDateString(),
                'UsrHora' => $tdAhora->format('H:i:s'),
            ]);

            $loNuevo = DB::connection($this->pcConexion)->table('LOTE')->where('Lote', $tnLote)->first();
            $laAntes = $this->normalizarFila($loActual);
            $laDespues = $this->normalizarFila($loNuevo);
            $this->toAuditoria->registrar('LOTE', $tnLote, 'ELIMINAR_LOGICO', $laAntes, $laDespues, $tnUsuario, $tcMotivo);

            return ['Estado' => 'OK', 'Datos' => $laDespues];
        });
    }

    private function normalizarFila(object $toFila): array
    {
        $la = (array)$toFila;
        $la['Version'] = $this->toControlVersion->versionDesdeFila($toFila);
        return $la;
    }
}

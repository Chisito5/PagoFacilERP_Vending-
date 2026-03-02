<?php

namespace App\Modulos\Celda\Services;

use App\Soporte\AuditoriaService;
use App\Soporte\ControlVersionService;
use App\Support\EstadoCatalogo;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class CeldaService
{
    private string $pcConexion = 'mysqlNegocio';

    public function __construct(
        private EstadoCatalogo $toEstadoCatalogo,
        private ControlVersionService $toControlVersion,
        private AuditoriaService $toAuditoria
    ) {
    }

    public function Listar(?int $tnMaquina, ?int $tnEstado, ?string $tcBusqueda, int $tnPagina, int $tnTamanoPagina): LengthAwarePaginator
    {
        $tnTamanoPagina = max(1, min($tnTamanoPagina, 200));

        $toConsulta = DB::connection($this->pcConexion)
            ->table('CELDA as c')
            ->leftJoin('EXISTENCIACELDA as ec', 'ec.Celda', '=', 'c.Celda')
            ->select([
                'c.Celda', 'c.Maquina', 'c.CodigoSeleccion', 'c.Fila', 'c.Columna', 'c.CapacidadMaxima',
                'c.Estado', 'c.Usr', 'c.UsrFecha', 'c.UsrHora',
                DB::raw('COALESCE(ec.CantidadDisponible,0) as CantidadDisponible'),
                DB::raw('COALESCE(ec.CantidadReservada,0) as CantidadReservada'),
            ])
            ->orderByDesc('c.Celda');

        if ($tnMaquina !== null && $tnMaquina > 0) {
            $toConsulta->where('c.Maquina', $tnMaquina);
        }
        if ($tnEstado !== null && $tnEstado > 0) {
            $toConsulta->where('c.Estado', $tnEstado);
        }
        if ($tcBusqueda !== null && trim($tcBusqueda) !== '') {
            $tcBusqueda = trim($tcBusqueda);
            $toConsulta->where('c.CodigoSeleccion', 'like', '%' . $tcBusqueda . '%');
        }

        return $toConsulta->paginate($tnTamanoPagina, ['*'], 'Pagina', max(1, $tnPagina));
    }

    public function Obtener(int $tnCelda): ?array
    {
        $loFila = DB::connection($this->pcConexion)
            ->table('CELDA as c')
            ->leftJoin('EXISTENCIACELDA as ec', 'ec.Celda', '=', 'c.Celda')
            ->select([
                'c.Celda', 'c.Maquina', 'c.CodigoSeleccion', 'c.Fila', 'c.Columna', 'c.CapacidadMaxima',
                'c.Estado', 'c.Usr', 'c.UsrFecha', 'c.UsrHora',
                DB::raw('COALESCE(ec.CantidadDisponible,0) as CantidadDisponible'),
                DB::raw('COALESCE(ec.CantidadReservada,0) as CantidadReservada'),
            ])
            ->where('c.Celda', $tnCelda)
            ->first();

        return $loFila ? $this->normalizarFila($loFila) : null;
    }

    /** @param array<string,mixed> $taDatos */
    public function Crear(array $taDatos, int $tnUsuario): array
    {
        $tdAhora = now();
        $tnEstadoActivo = $this->toEstadoCatalogo->obtenerId('GENERAL', 1);

        return DB::connection($this->pcConexion)->transaction(function () use ($taDatos, $tnUsuario, $tdAhora, $tnEstadoActivo): array {
            $tnCelda = DB::connection($this->pcConexion)->table('CELDA')->insertGetId([
                'Maquina' => (int)$taDatos['Maquina'],
                'CodigoSeleccion' => $taDatos['CodigoSeleccion'],
                'Fila' => $taDatos['Fila'] ?? null,
                'Columna' => $taDatos['Columna'] ?? null,
                'CapacidadMaxima' => (int)$taDatos['CapacidadMaxima'],
                'Estado' => (int)($taDatos['Estado'] ?? $tnEstadoActivo),
                'Usr' => $tnUsuario,
                'UsrFecha' => $tdAhora->toDateString(),
                'UsrHora' => $tdAhora->format('H:i:s'),
            ]);

            DB::connection($this->pcConexion)->table('EXISTENCIACELDA')->insert([
                'Celda' => $tnCelda,
                'ProductoEmpresa' => $taDatos['ProductoEmpresa'] ?? null,
                'Lote' => $taDatos['Lote'] ?? null,
                'CantidadDisponible' => (int)($taDatos['CantidadDisponible'] ?? 0),
                'CantidadReservada' => (int)($taDatos['CantidadReservada'] ?? 0),
                'Estado' => (int)($taDatos['Estado'] ?? $tnEstadoActivo),
                'Usr' => $tnUsuario,
                'UsrFecha' => $tdAhora->toDateString(),
                'UsrHora' => $tdAhora->format('H:i:s'),
            ]);

            $laNueva = $this->Obtener($tnCelda) ?? [];
            $this->toAuditoria->registrar('CELDA', $tnCelda, 'CREAR', null, $laNueva, $tnUsuario, $taDatos['Motivo'] ?? null);

            return $laNueva;
        });
    }

    /** @param array<string,mixed> $taDatos */
    public function Actualizar(int $tnCelda, array $taDatos, string $tcVersion, int $tnUsuario, bool $lbParcial): array
    {
        return DB::connection($this->pcConexion)->transaction(function () use ($tnCelda, $taDatos, $tcVersion, $tnUsuario, $lbParcial): array {
            $loActual = DB::connection($this->pcConexion)->table('CELDA')->where('Celda', $tnCelda)->lockForUpdate()->first();
            if (!$loActual) {
                return ['Estado' => 'NO_ENCONTRADO'];
            }
            if (!$this->toControlVersion->coincide($tcVersion, $loActual)) {
                return ['Estado' => 'CONFLICTO_VERSION', 'Actual' => $this->Obtener($tnCelda)];
            }

            $laCampos = ['Maquina', 'CodigoSeleccion', 'Fila', 'Columna', 'CapacidadMaxima', 'Estado'];
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

            DB::connection($this->pcConexion)->table('CELDA')->where('Celda', $tnCelda)->update($laUpdate);

            $laAntes = $this->normalizarFila($loActual);
            $laDespues = $this->Obtener($tnCelda) ?? [];
            $this->toAuditoria->registrar('CELDA', $tnCelda, 'ACTUALIZAR', $laAntes, $laDespues, $tnUsuario, $taDatos['Motivo'] ?? null);

            return ['Estado' => 'OK', 'Datos' => $laDespues];
        });
    }

    public function EliminarLogico(int $tnCelda, string $tcVersion, int $tnUsuario, ?string $tcMotivo): array
    {
        $tnEstadoInactivo = $this->toEstadoCatalogo->obtenerId('GENERAL', 2);

        return DB::connection($this->pcConexion)->transaction(function () use ($tnCelda, $tcVersion, $tnUsuario, $tnEstadoInactivo, $tcMotivo): array {
            $loActual = DB::connection($this->pcConexion)->table('CELDA')->where('Celda', $tnCelda)->lockForUpdate()->first();
            if (!$loActual) {
                return ['Estado' => 'NO_ENCONTRADO'];
            }
            if (!$this->toControlVersion->coincide($tcVersion, $loActual)) {
                return ['Estado' => 'CONFLICTO_VERSION', 'Actual' => $this->Obtener($tnCelda)];
            }

            $tdAhora = now();
            DB::connection($this->pcConexion)->table('CELDA')->where('Celda', $tnCelda)->update([
                'Estado' => $tnEstadoInactivo,
                'Usr' => $tnUsuario,
                'UsrFecha' => $tdAhora->toDateString(),
                'UsrHora' => $tdAhora->format('H:i:s'),
            ]);

            DB::connection($this->pcConexion)->table('EXISTENCIACELDA')->where('Celda', $tnCelda)->update([
                'Estado' => $tnEstadoInactivo,
                'Usr' => $tnUsuario,
                'UsrFecha' => $tdAhora->toDateString(),
                'UsrHora' => $tdAhora->format('H:i:s'),
            ]);

            $laAntes = $this->normalizarFila($loActual);
            $laDespues = $this->Obtener($tnCelda) ?? [];
            $this->toAuditoria->registrar('CELDA', $tnCelda, 'ELIMINAR_LOGICO', $laAntes, $laDespues, $tnUsuario, $tcMotivo);

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

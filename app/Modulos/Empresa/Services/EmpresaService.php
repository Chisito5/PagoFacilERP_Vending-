<?php

namespace App\Modulos\Empresa\Services;

use App\Soporte\AuditoriaService;
use App\Soporte\ControlVersionService;
use App\Support\EstadoCatalogo;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class EmpresaService
{
    private string $pcConexion = 'mysqlNegocio';

    public function __construct(
        private EstadoCatalogo $toEstadoCatalogo,
        private ControlVersionService $toControlVersion,
        private AuditoriaService $toAuditoria
    ) {
    }

    public function Listar(?int $tnEstado, ?string $tcBusqueda, int $tnPagina, int $tnTamanoPagina): LengthAwarePaginator
    {
        $tnTamanoPagina = max(1, min($tnTamanoPagina, 200));

        $toConsulta = DB::connection($this->pcConexion)
            ->table('EMPRESA')
            ->select([
                'Empresa',
                'CodigoEmpresa',
                'RazonSocial',
                'NombreComercial',
                'Nit',
                'Telefono',
                'Correo',
                'DireccionFiscal',
                'TipoEmpresa',
                'Estado',
                'PlantillaVisualPredeterminada',
                'Usr',
                'UsrFecha',
                'UsrHora',
            ])
            ->orderByDesc('Empresa');

        if ($tnEstado !== null && $tnEstado > 0) {
            $toConsulta->where('Estado', $tnEstado);
        }

        if ($tcBusqueda !== null && trim($tcBusqueda) !== '') {
            $tcBusqueda = trim($tcBusqueda);
            $toConsulta->where(function ($toWhere) use ($tcBusqueda): void {
                $toWhere->where('CodigoEmpresa', 'like', '%' . $tcBusqueda . '%')
                    ->orWhere('RazonSocial', 'like', '%' . $tcBusqueda . '%')
                    ->orWhere('NombreComercial', 'like', '%' . $tcBusqueda . '%')
                    ->orWhere('Nit', 'like', '%' . $tcBusqueda . '%');
            });
        }

        return $toConsulta->paginate($tnTamanoPagina, ['*'], 'Pagina', max(1, $tnPagina));
    }

    public function Obtener(int $tnEmpresa): ?array
    {
        $loEmpresa = DB::connection($this->pcConexion)
            ->table('EMPRESA')
            ->where('Empresa', $tnEmpresa)
            ->first();

        if (!$loEmpresa) {
            return null;
        }

        return $this->normalizarFila($loEmpresa);
    }

    /** @param array<string,mixed> $taDatos */
    public function Crear(array $taDatos, int $tnUsuario): array
    {
        $tdAhora = now();
        $tnEstadoActivo = $this->toEstadoCatalogo->obtenerId('GENERAL', 1);

        $laInsert = [
            'CodigoEmpresa' => $taDatos['CodigoEmpresa'],
            'RazonSocial' => $taDatos['RazonSocial'],
            'NombreComercial' => $taDatos['NombreComercial'] ?? null,
            'Nit' => $taDatos['Nit'] ?? null,
            'Telefono' => $taDatos['Telefono'] ?? null,
            'Correo' => $taDatos['Correo'] ?? null,
            'DireccionFiscal' => $taDatos['DireccionFiscal'] ?? null,
            'TipoEmpresa' => (int)$taDatos['TipoEmpresa'],
            'Estado' => (int)($taDatos['Estado'] ?? $tnEstadoActivo),
            'PlantillaVisualPredeterminada' => $taDatos['PlantillaVisualPredeterminada'] ?? null,
            'Usr' => $tnUsuario,
            'UsrFecha' => $tdAhora->toDateString(),
            'UsrHora' => $tdAhora->format('H:i:s'),
        ];

        $tnEmpresa = DB::connection($this->pcConexion)
            ->table('EMPRESA')
            ->insertGetId($laInsert);

        $loNueva = DB::connection($this->pcConexion)->table('EMPRESA')->where('Empresa', $tnEmpresa)->first();
        $laNueva = $this->normalizarFila($loNueva);

        $this->toAuditoria->registrar('EMPRESA', $tnEmpresa, 'CREAR', null, $laNueva, $tnUsuario, $taDatos['Motivo'] ?? null);

        return $laNueva;
    }

    /** @param array<string,mixed> $taDatos */
    public function Actualizar(int $tnEmpresa, array $taDatos, string $tcVersion, int $tnUsuario, bool $lbParcial = false): array
    {
        return DB::connection($this->pcConexion)->transaction(function () use ($tnEmpresa, $taDatos, $tcVersion, $tnUsuario, $lbParcial): array {
            $loActual = DB::connection($this->pcConexion)
                ->table('EMPRESA')
                ->where('Empresa', $tnEmpresa)
                ->lockForUpdate()
                ->first();

            if (!$loActual) {
                return ['Estado' => 'NO_ENCONTRADO'];
            }

            if (!$this->toControlVersion->coincide($tcVersion, $loActual)) {
                return ['Estado' => 'CONFLICTO_VERSION', 'Actual' => $this->normalizarFila($loActual)];
            }

            $laUpdate = [];
            $laCampos = [
                'CodigoEmpresa',
                'RazonSocial',
                'NombreComercial',
                'Nit',
                'Telefono',
                'Correo',
                'DireccionFiscal',
                'TipoEmpresa',
                'Estado',
                'PlantillaVisualPredeterminada',
            ];

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

            DB::connection($this->pcConexion)
                ->table('EMPRESA')
                ->where('Empresa', $tnEmpresa)
                ->update($laUpdate);

            $loNuevo = DB::connection($this->pcConexion)->table('EMPRESA')->where('Empresa', $tnEmpresa)->first();

            $laAntes = $this->normalizarFila($loActual);
            $laDespues = $this->normalizarFila($loNuevo);
            $this->toAuditoria->registrar('EMPRESA', $tnEmpresa, 'ACTUALIZAR', $laAntes, $laDespues, $tnUsuario, $taDatos['Motivo'] ?? null);

            return ['Estado' => 'OK', 'Datos' => $laDespues];
        });
    }

    public function EliminarLogico(int $tnEmpresa, string $tcVersion, int $tnUsuario, ?string $tcMotivo): array
    {
        $tnEstadoInactivo = $this->toEstadoCatalogo->obtenerId('GENERAL', 2);

        return DB::connection($this->pcConexion)->transaction(function () use ($tnEmpresa, $tcVersion, $tnUsuario, $tnEstadoInactivo, $tcMotivo): array {
            $loActual = DB::connection($this->pcConexion)
                ->table('EMPRESA')
                ->where('Empresa', $tnEmpresa)
                ->lockForUpdate()
                ->first();

            if (!$loActual) {
                return ['Estado' => 'NO_ENCONTRADO'];
            }

            if (!$this->toControlVersion->coincide($tcVersion, $loActual)) {
                return ['Estado' => 'CONFLICTO_VERSION', 'Actual' => $this->normalizarFila($loActual)];
            }

            $tdAhora = now();
            DB::connection($this->pcConexion)
                ->table('EMPRESA')
                ->where('Empresa', $tnEmpresa)
                ->update([
                    'Estado' => $tnEstadoInactivo,
                    'Usr' => $tnUsuario,
                    'UsrFecha' => $tdAhora->toDateString(),
                    'UsrHora' => $tdAhora->format('H:i:s'),
                ]);

            $loNuevo = DB::connection($this->pcConexion)->table('EMPRESA')->where('Empresa', $tnEmpresa)->first();
            $laAntes = $this->normalizarFila($loActual);
            $laDespues = $this->normalizarFila($loNuevo);
            $this->toAuditoria->registrar('EMPRESA', $tnEmpresa, 'ELIMINAR_LOGICO', $laAntes, $laDespues, $tnUsuario, $tcMotivo);

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

<?php

namespace App\Modulos\Producto\Services;

use App\Soporte\AuditoriaService;
use App\Soporte\ControlVersionService;
use App\Support\EstadoCatalogo;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class ProductoService
{
    private string $pcConexion = 'mysqlNegocio';

    public function __construct(
        private EstadoCatalogo $toEstadoCatalogo,
        private ControlVersionService $toControlVersion,
        private AuditoriaService $toAuditoria
    ) {
    }

    public function Listar(?int $tnEmpresa, ?int $tnEstado, ?string $tcBusqueda, int $tnPagina, int $tnTamanoPagina): LengthAwarePaginator
    {
        $tnTamanoPagina = max(1, min($tnTamanoPagina, 200));

        $toConsulta = DB::connection($this->pcConexion)
            ->table('PRODUCTO')
            ->select([
                'Producto',
                DB::raw('Producto as IdProducto'),
                'Empresa',
                'CodigoSku',
                DB::raw('COALESCE(CodigoProducto, CodigoSku) as CodigoProducto'),
                'CodigoBarra',
                'NombreProducto',
                'Precio',
                'AnchoMm',
                'AltoMm',
                'ProfundidadMm',
                'Orientacion',
                'PermiteGiro',
                'UnidadEmpaque',
                'Descripcion',
                'Marca',
                'ContenidoCantidad',
                'UnidadMedidaContenido',
                'PesoGramos',
                DB::raw('COALESCE(PesoGr, PesoGramos) as PesoGr'),
                'SubgrupoProducto',
                'Estado',
                'Usr', 'UsrFecha', 'UsrHora'
            ])
            ->orderByDesc('Producto');

        if ($tnEmpresa !== null && $tnEmpresa > 0) {
            $toConsulta->where('Empresa', $tnEmpresa);
        }

        if ($tnEstado !== null && $tnEstado > 0) {
            $toConsulta->where('Estado', $tnEstado);
        }

        if ($tcBusqueda !== null && trim($tcBusqueda) !== '') {
            $tcBusqueda = trim($tcBusqueda);
            $toConsulta->where(function ($toWhere) use ($tcBusqueda): void {
                $toWhere->where('CodigoSku', 'like', '%' . $tcBusqueda . '%')
                    ->orWhere('CodigoBarra', 'like', '%' . $tcBusqueda . '%')
                    ->orWhere('NombreProducto', 'like', '%' . $tcBusqueda . '%')
                    ->orWhere('Marca', 'like', '%' . $tcBusqueda . '%');
            });
        }

        return $toConsulta->paginate($tnTamanoPagina, ['*'], 'Pagina', max(1, $tnPagina));
    }

    public function Obtener(int $tnProducto): ?array
    {
        $loFila = DB::connection($this->pcConexion)->table('PRODUCTO')->where('Producto', $tnProducto)->first();
        return $loFila ? $this->normalizarFila($loFila) : null;
    }

    /** @param array<string,mixed> $taDatos */
    public function Crear(array $taDatos, int $tnUsuario): array
    {
        $tdAhora = now();
        $tnEstadoActivo = $this->toEstadoCatalogo->obtenerId('GENERAL', 1);

        $tnId = DB::connection($this->pcConexion)->table('PRODUCTO')->insertGetId([
            'Empresa' => (int)$taDatos['Empresa'],
            'CodigoSku' => $taDatos['CodigoSku'] ?? $taDatos['CodigoProducto'],
            'CodigoProducto' => $taDatos['CodigoProducto'] ?? $taDatos['CodigoSku'] ?? null,
            'CodigoBarra' => $taDatos['CodigoBarra'] ?? null,
            'NombreProducto' => $taDatos['NombreProducto'],
            'Precio' => $taDatos['Precio'] ?? null,
            'AnchoMm' => $taDatos['AnchoMm'] ?? null,
            'AltoMm' => $taDatos['AltoMm'] ?? null,
            'ProfundidadMm' => $taDatos['ProfundidadMm'] ?? null,
            'Orientacion' => $taDatos['Orientacion'] ?? null,
            'PermiteGiro' => isset($taDatos['PermiteGiro']) ? ((bool)$taDatos['PermiteGiro'] ? 1 : 0) : null,
            'UnidadEmpaque' => $taDatos['UnidadEmpaque'] ?? null,
            'Descripcion' => $taDatos['Descripcion'] ?? null,
            'Marca' => $taDatos['Marca'] ?? null,
            'ContenidoCantidad' => $taDatos['ContenidoCantidad'] ?? null,
            'UnidadMedidaContenido' => $taDatos['UnidadMedidaContenido'] ?? null,
            'PesoGramos' => $taDatos['PesoGramos'] ?? ($taDatos['PesoGr'] ?? null),
            'PesoGr' => $taDatos['PesoGr'] ?? ($taDatos['PesoGramos'] ?? null),
            'SubgrupoProducto' => $taDatos['SubgrupoProducto'] ?? null,
            'Estado' => (int)($taDatos['Estado'] ?? $tnEstadoActivo),
            'Usr' => $tnUsuario,
            'UsrFecha' => $tdAhora->toDateString(),
            'UsrHora' => $tdAhora->format('H:i:s'),
        ]);

        $loNuevo = DB::connection($this->pcConexion)->table('PRODUCTO')->where('Producto', $tnId)->first();
        $laNuevo = $this->normalizarFila($loNuevo);
        $this->toAuditoria->registrar('PRODUCTO', $tnId, 'CREAR', null, $laNuevo, $tnUsuario, $taDatos['Motivo'] ?? null);

        return $laNuevo;
    }

    /** @param array<string,mixed> $taDatos */
    public function Actualizar(int $tnProducto, array $taDatos, string $tcVersion, int $tnUsuario, bool $lbParcial): array
    {
        return DB::connection($this->pcConexion)->transaction(function () use ($tnProducto, $taDatos, $tcVersion, $tnUsuario, $lbParcial): array {
            $loActual = DB::connection($this->pcConexion)->table('PRODUCTO')->where('Producto', $tnProducto)->lockForUpdate()->first();
            if (!$loActual) {
                return ['Estado' => 'NO_ENCONTRADO'];
            }
            if (!$this->toControlVersion->coincide($tcVersion, $loActual)) {
                return ['Estado' => 'CONFLICTO_VERSION', 'Actual' => $this->normalizarFila($loActual)];
            }

            $laCampos = [
                'Empresa',
                'CodigoSku',
                'CodigoProducto',
                'CodigoBarra',
                'NombreProducto',
                'Precio',
                'AnchoMm',
                'AltoMm',
                'ProfundidadMm',
                'Orientacion',
                'PermiteGiro',
                'UnidadEmpaque',
                'Descripcion',
                'Marca',
                'ContenidoCantidad',
                'UnidadMedidaContenido',
                'PesoGramos',
                'PesoGr',
                'SubgrupoProducto',
                'Estado'
            ];
            $laUpdate = [];
            foreach ($laCampos as $tcCampo) {
                if (array_key_exists($tcCampo, $taDatos)) {
                    $laUpdate[$tcCampo] = $taDatos[$tcCampo];
                } elseif (!$lbParcial) {
                    $laUpdate[$tcCampo] = $loActual->{$tcCampo};
                }
            }

            if (array_key_exists('PermiteGiro', $laUpdate) && $laUpdate['PermiteGiro'] !== null) {
                $laUpdate['PermiteGiro'] = ((bool)$laUpdate['PermiteGiro']) ? 1 : 0;
            }
            if (!array_key_exists('CodigoSku', $laUpdate) && array_key_exists('CodigoProducto', $laUpdate)) {
                $laUpdate['CodigoSku'] = $laUpdate['CodigoProducto'];
            }
            if (!array_key_exists('CodigoProducto', $laUpdate) && array_key_exists('CodigoSku', $laUpdate)) {
                $laUpdate['CodigoProducto'] = $laUpdate['CodigoSku'];
            }
            if (!array_key_exists('PesoGramos', $laUpdate) && array_key_exists('PesoGr', $laUpdate)) {
                $laUpdate['PesoGramos'] = $laUpdate['PesoGr'];
            }
            if (!array_key_exists('PesoGr', $laUpdate) && array_key_exists('PesoGramos', $laUpdate)) {
                $laUpdate['PesoGr'] = $laUpdate['PesoGramos'];
            }

            $tdAhora = now();
            $laUpdate['Usr'] = $tnUsuario;
            $laUpdate['UsrFecha'] = $tdAhora->toDateString();
            $laUpdate['UsrHora'] = $tdAhora->format('H:i:s');

            DB::connection($this->pcConexion)->table('PRODUCTO')->where('Producto', $tnProducto)->update($laUpdate);

            $loNuevo = DB::connection($this->pcConexion)->table('PRODUCTO')->where('Producto', $tnProducto)->first();
            $laAntes = $this->normalizarFila($loActual);
            $laDespues = $this->normalizarFila($loNuevo);
            $this->toAuditoria->registrar('PRODUCTO', $tnProducto, 'ACTUALIZAR', $laAntes, $laDespues, $tnUsuario, $taDatos['Motivo'] ?? null);

            return ['Estado' => 'OK', 'Datos' => $laDespues];
        });
    }

    public function EliminarLogico(int $tnProducto, string $tcVersion, int $tnUsuario, ?string $tcMotivo): array
    {
        $tnEstadoInactivo = $this->toEstadoCatalogo->obtenerId('GENERAL', 2);

        return DB::connection($this->pcConexion)->transaction(function () use ($tnProducto, $tcVersion, $tnUsuario, $tnEstadoInactivo, $tcMotivo): array {
            $loActual = DB::connection($this->pcConexion)->table('PRODUCTO')->where('Producto', $tnProducto)->lockForUpdate()->first();
            if (!$loActual) {
                return ['Estado' => 'NO_ENCONTRADO'];
            }
            if (!$this->toControlVersion->coincide($tcVersion, $loActual)) {
                return ['Estado' => 'CONFLICTO_VERSION', 'Actual' => $this->normalizarFila($loActual)];
            }

            $tdAhora = now();
            DB::connection($this->pcConexion)->table('PRODUCTO')->where('Producto', $tnProducto)->update([
                'Estado' => $tnEstadoInactivo,
                'Usr' => $tnUsuario,
                'UsrFecha' => $tdAhora->toDateString(),
                'UsrHora' => $tdAhora->format('H:i:s'),
            ]);

            $loNuevo = DB::connection($this->pcConexion)->table('PRODUCTO')->where('Producto', $tnProducto)->first();
            $laAntes = $this->normalizarFila($loActual);
            $laDespues = $this->normalizarFila($loNuevo);
            $this->toAuditoria->registrar('PRODUCTO', $tnProducto, 'ELIMINAR_LOGICO', $laAntes, $laDespues, $tnUsuario, $tcMotivo);

            return ['Estado' => 'OK', 'Datos' => $laDespues];
        });
    }

    private function normalizarFila(object $toFila): array
    {
        $la = (array)$toFila;
        $la['IdProducto'] = (int)($la['Producto'] ?? 0);
        $la['CodigoProducto'] = (string)($la['CodigoProducto'] ?? ($la['CodigoSku'] ?? ''));
        $la['PesoGr'] = isset($la['PesoGr']) ? $la['PesoGr'] : ($la['PesoGramos'] ?? null);
        $la['Version'] = $this->toControlVersion->versionDesdeFila($toFila);
        return $la;
    }
}

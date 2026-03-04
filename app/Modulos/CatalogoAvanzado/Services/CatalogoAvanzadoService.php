<?php

namespace App\Modulos\CatalogoAvanzado\Services;

use App\Soporte\ArchivoStorageService;
use App\Soporte\AuditoriaService;
use App\Soporte\ControlVersionService;
use App\Soporte\EstadoNegocioService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class CatalogoAvanzadoService
{
    private string $pcConexion = 'mysqlNegocio';

    public function __construct(
        private EstadoNegocioService $toEstadoNegocio,
        private ControlVersionService $toControlVersion,
        private AuditoriaService $toAuditoria,
        private ArchivoStorageService $toArchivoStorage
    ) {
    }

    public function listarFamilias(?int $tnEmpresa, ?int $tnEstado, int $tnPagina, int $tnTamanoPagina): LengthAwarePaginator
    {
        $tnTamanoPagina = max(1, min($tnTamanoPagina, 200));
        $to = DB::connection($this->pcConexion)->table('PRODUCTOFAMILIA')->orderByDesc('FamiliaProducto');
        if ($tnEmpresa !== null && $tnEmpresa > 0) {
            $to->where('Empresa', $tnEmpresa);
        }
        if ($tnEstado !== null && $tnEstado > 0) {
            $to->where('Estado', $tnEstado);
        }
        return $to->paginate($tnTamanoPagina, ['*'], 'Pagina', max(1, $tnPagina));
    }

    public function obtenerFamilia(int $tnId): ?array
    {
        $lo = DB::connection($this->pcConexion)->table('PRODUCTOFAMILIA')->where('FamiliaProducto', $tnId)->first();
        if (!$lo) {
            return null;
        }
        return $this->normalizar($lo);
    }

    /** @param array<string,mixed> $taDatos */
    public function crearFamilia(array $taDatos, int $tnUsuario): array
    {
        $tdAhora = now();
        $tnId = DB::connection($this->pcConexion)->table('PRODUCTOFAMILIA')->insertGetId([
            'Empresa' => (int)$taDatos['Empresa'],
            'NombreFamiliaProducto' => (string)$taDatos['NombreFamiliaProducto'],
            'Descripcion' => $taDatos['Descripcion'] ?? null,
            'Estado' => (int)($taDatos['Estado'] ?? $this->toEstadoNegocio->activoGeneral()),
            'Usr' => $tnUsuario,
            'UsrFecha' => $tdAhora->toDateString(),
            'UsrHora' => $tdAhora->format('H:i:s'),
        ]);
        $la = $this->obtenerFamilia($tnId) ?? [];
        $this->toAuditoria->registrar('PRODUCTOFAMILIA', $tnId, 'CREAR', null, $la, $tnUsuario, $taDatos['Motivo'] ?? null);
        return $la;
    }

    /** @param array<string,mixed> $taDatos */
    public function actualizarFamilia(int $tnId, array $taDatos, string $tcVersion, int $tnUsuario, bool $lbParcial): array
    {
        return $this->actualizarGenerico('PRODUCTOFAMILIA', 'FamiliaProducto', $tnId, $taDatos, $tcVersion, $tnUsuario, $lbParcial);
    }

    public function eliminarFamilia(int $tnId, string $tcVersion, int $tnUsuario, ?string $tcMotivo): array
    {
        return $this->eliminarGenerico('PRODUCTOFAMILIA', 'FamiliaProducto', $tnId, $tcVersion, $tnUsuario, $tcMotivo);
    }

    public function listarGrupos(?int $tnFamilia, ?int $tnEstado, int $tnPagina, int $tnTamanoPagina): LengthAwarePaginator
    {
        $tnTamanoPagina = max(1, min($tnTamanoPagina, 200));
        $to = DB::connection($this->pcConexion)->table('PRODUCTOGRUPO')->orderByDesc('GrupoProducto');
        if ($tnFamilia !== null && $tnFamilia > 0) {
            $to->where('FamiliaProducto', $tnFamilia);
        }
        if ($tnEstado !== null && $tnEstado > 0) {
            $to->where('Estado', $tnEstado);
        }
        return $to->paginate($tnTamanoPagina, ['*'], 'Pagina', max(1, $tnPagina));
    }

    public function obtenerGrupo(int $tnId): ?array
    {
        $lo = DB::connection($this->pcConexion)->table('PRODUCTOGRUPO')->where('GrupoProducto', $tnId)->first();
        return $lo ? $this->normalizar($lo) : null;
    }

    /** @param array<string,mixed> $taDatos */
    public function crearGrupo(array $taDatos, int $tnUsuario): array
    {
        $tdAhora = now();
        $tnId = DB::connection($this->pcConexion)->table('PRODUCTOGRUPO')->insertGetId([
            'FamiliaProducto' => (int)$taDatos['FamiliaProducto'],
            'NombreGrupoProducto' => (string)$taDatos['NombreGrupoProducto'],
            'Descripcion' => $taDatos['Descripcion'] ?? null,
            'Estado' => (int)($taDatos['Estado'] ?? $this->toEstadoNegocio->activoGeneral()),
            'Usr' => $tnUsuario,
            'UsrFecha' => $tdAhora->toDateString(),
            'UsrHora' => $tdAhora->format('H:i:s'),
        ]);
        $la = $this->obtenerGrupo($tnId) ?? [];
        $this->toAuditoria->registrar('PRODUCTOGRUPO', $tnId, 'CREAR', null, $la, $tnUsuario, $taDatos['Motivo'] ?? null);
        return $la;
    }

    /** @param array<string,mixed> $taDatos */
    public function actualizarGrupo(int $tnId, array $taDatos, string $tcVersion, int $tnUsuario, bool $lbParcial): array
    {
        return $this->actualizarGenerico('PRODUCTOGRUPO', 'GrupoProducto', $tnId, $taDatos, $tcVersion, $tnUsuario, $lbParcial);
    }

    public function eliminarGrupo(int $tnId, string $tcVersion, int $tnUsuario, ?string $tcMotivo): array
    {
        return $this->eliminarGenerico('PRODUCTOGRUPO', 'GrupoProducto', $tnId, $tcVersion, $tnUsuario, $tcMotivo);
    }

    public function listarSubgrupos(?int $tnGrupo, ?int $tnEstado, int $tnPagina, int $tnTamanoPagina): LengthAwarePaginator
    {
        $tnTamanoPagina = max(1, min($tnTamanoPagina, 200));
        $to = DB::connection($this->pcConexion)->table('PRODUCTOSUBGRUPO')->orderByDesc('SubgrupoProducto');
        if ($tnGrupo !== null && $tnGrupo > 0) {
            $to->where('GrupoProducto', $tnGrupo);
        }
        if ($tnEstado !== null && $tnEstado > 0) {
            $to->where('Estado', $tnEstado);
        }
        return $to->paginate($tnTamanoPagina, ['*'], 'Pagina', max(1, $tnPagina));
    }

    public function obtenerSubgrupo(int $tnId): ?array
    {
        $lo = DB::connection($this->pcConexion)->table('PRODUCTOSUBGRUPO')->where('SubgrupoProducto', $tnId)->first();
        return $lo ? $this->normalizar($lo) : null;
    }

    /** @param array<string,mixed> $taDatos */
    public function crearSubgrupo(array $taDatos, int $tnUsuario): array
    {
        $tdAhora = now();
        $tnId = DB::connection($this->pcConexion)->table('PRODUCTOSUBGRUPO')->insertGetId([
            'GrupoProducto' => (int)$taDatos['GrupoProducto'],
            'NombreSubgrupoProducto' => (string)$taDatos['NombreSubgrupoProducto'],
            'Descripcion' => $taDatos['Descripcion'] ?? null,
            'Estado' => (int)($taDatos['Estado'] ?? $this->toEstadoNegocio->activoGeneral()),
            'Usr' => $tnUsuario,
            'UsrFecha' => $tdAhora->toDateString(),
            'UsrHora' => $tdAhora->format('H:i:s'),
        ]);
        $la = $this->obtenerSubgrupo($tnId) ?? [];
        $this->toAuditoria->registrar('PRODUCTOSUBGRUPO', $tnId, 'CREAR', null, $la, $tnUsuario, $taDatos['Motivo'] ?? null);
        return $la;
    }

    /** @param array<string,mixed> $taDatos */
    public function actualizarSubgrupo(int $tnId, array $taDatos, string $tcVersion, int $tnUsuario, bool $lbParcial): array
    {
        return $this->actualizarGenerico('PRODUCTOSUBGRUPO', 'SubgrupoProducto', $tnId, $taDatos, $tcVersion, $tnUsuario, $lbParcial);
    }

    public function eliminarSubgrupo(int $tnId, string $tcVersion, int $tnUsuario, ?string $tcMotivo): array
    {
        return $this->eliminarGenerico('PRODUCTOSUBGRUPO', 'SubgrupoProducto', $tnId, $tcVersion, $tnUsuario, $tcMotivo);
    }

    public function listarImagenes(?int $tnProducto, ?int $tnEstado, int $tnPagina, int $tnTamanoPagina): LengthAwarePaginator
    {
        $tnTamanoPagina = max(1, min($tnTamanoPagina, 200));

        $to = DB::connection($this->pcConexion)
            ->table('PRODUCTOIMAGEN as pi')
            ->leftJoin('TIPOIMAGEN as ti', 'ti.TipoImagen', '=', 'pi.TipoImagen')
            ->select(['pi.*', 'ti.NombreTipoImagen'])
            ->orderByDesc('pi.ProductoImagen');

        if ($tnProducto !== null && $tnProducto > 0) {
            $to->where('pi.Producto', $tnProducto);
        }
        if ($tnEstado !== null && $tnEstado > 0) {
            $to->where('pi.Estado', $tnEstado);
        }

        return $to->paginate($tnTamanoPagina, ['*'], 'Pagina', max(1, $tnPagina));
    }

    public function obtenerImagen(int $tnId): ?array
    {
        $lo = DB::connection($this->pcConexion)
            ->table('PRODUCTOIMAGEN as pi')
            ->leftJoin('TIPOIMAGEN as ti', 'ti.TipoImagen', '=', 'pi.TipoImagen')
            ->select(['pi.*', 'ti.NombreTipoImagen'])
            ->where('pi.ProductoImagen', $tnId)
            ->first();

        return $lo ? $this->normalizar($lo) : null;
    }

    /** @param array<string,mixed> $taDatos */
    public function crearImagen(array $taDatos, int $tnUsuario): array
    {
        $tdAhora = now();

        $tnId = DB::connection($this->pcConexion)->table('PRODUCTOIMAGEN')->insertGetId([
            'Producto' => (int)$taDatos['Producto'],
            'TipoImagen' => (int)$taDatos['TipoImagen'],
            'RutaImagen' => (string)$taDatos['RutaImagen'],
            'Orden' => (int)($taDatos['Orden'] ?? 1),
            'Estado' => (int)($taDatos['Estado'] ?? $this->toEstadoNegocio->activoGeneral()),
            'Usr' => $tnUsuario,
            'UsrFecha' => $tdAhora->toDateString(),
            'UsrHora' => $tdAhora->format('H:i:s'),
        ]);

        $la = $this->obtenerImagen($tnId) ?? [];
        $this->toAuditoria->registrar('PRODUCTOIMAGEN', $tnId, 'CREAR', null, $la, $tnUsuario, $taDatos['Motivo'] ?? null);

        return $la;
    }

    /** @param array<string,mixed> $taDatos */
    public function actualizarImagen(int $tnId, array $taDatos, string $tcVersion, int $tnUsuario, bool $lbParcial): array
    {
        return $this->actualizarGenerico('PRODUCTOIMAGEN', 'ProductoImagen', $tnId, $taDatos, $tcVersion, $tnUsuario, $lbParcial);
    }

    public function eliminarImagen(int $tnId, string $tcVersion, int $tnUsuario, ?string $tcMotivo): array
    {
        return $this->eliminarGenerico('PRODUCTOIMAGEN', 'ProductoImagen', $tnId, $tcVersion, $tnUsuario, $tcMotivo);
    }

    public function subirImagenProducto(int $tnProducto, int $tnTipoImagen, UploadedFile $toArchivo, int $tnUsuario, ?string $tcMotivo): array
    {
        $tnEmpresa = $this->obtenerEmpresaProducto($tnProducto);
        $laArchivo = $this->toArchivoStorage->subirArchivo($toArchivo, 'producto', $tnEmpresa, 'producto', $tnProducto);
        $tcRuta = (string)$laArchivo['RutaObjeto'];

        return $this->crearImagen([
            'Producto' => $tnProducto,
            'TipoImagen' => $tnTipoImagen,
            'RutaImagen' => $tcRuta,
            'Orden' => 1,
            'Motivo' => $tcMotivo,
        ], $tnUsuario);
    }

    public function eliminarImagenProducto(int $tnProducto, int $tnProductoImagen, string $tcVersion, int $tnUsuario, ?string $tcMotivo): array
    {
        $la = $this->obtenerImagen($tnProductoImagen);
        if (!$la) {
            return ['Estado' => 'NO_ENCONTRADO'];
        }
        if ((int)$la['Producto'] !== $tnProducto) {
            return ['Estado' => 'NO_CORRESPONDE'];
        }

        return $this->eliminarImagen($tnProductoImagen, $tcVersion, $tnUsuario, $tcMotivo);
    }

    public function obtenerTipoImagenPorNombre(string $tcNombre): ?int
    {
        $lo = DB::connection($this->pcConexion)
            ->table('TIPOIMAGEN')
            ->select('TipoImagen')
            ->whereRaw('UPPER(NombreTipoImagen) = ?', [strtoupper(trim($tcNombre))])
            ->first();

        return $lo ? (int)$lo->TipoImagen : null;
    }

    /** @param array<string,mixed> $taDatos */
    private function actualizarGenerico(string $tcTabla, string $tcPk, int $tnId, array $taDatos, string $tcVersion, int $tnUsuario, bool $lbParcial): array
    {
        return DB::connection($this->pcConexion)->transaction(function () use ($tcTabla, $tcPk, $tnId, $taDatos, $tcVersion, $tnUsuario, $lbParcial): array {
            $loActual = DB::connection($this->pcConexion)->table($tcTabla)->where($tcPk, $tnId)->lockForUpdate()->first();
            if (!$loActual) {
                return ['Estado' => 'NO_ENCONTRADO'];
            }
            if (!$this->toControlVersion->coincide($tcVersion, $loActual)) {
                return ['Estado' => 'CONFLICTO_VERSION', 'Actual' => $this->normalizar($loActual)];
            }

            $laUpdate = [];
            foreach ($taDatos as $tcCampo => $tmValor) {
                if (in_array($tcCampo, ['Version', 'Motivo'], true)) {
                    continue;
                }
                if ($lbParcial || array_key_exists($tcCampo, $taDatos)) {
                    $laUpdate[$tcCampo] = $tmValor;
                }
            }

            $tdAhora = now();
            $laUpdate['Usr'] = $tnUsuario;
            $laUpdate['UsrFecha'] = $tdAhora->toDateString();
            $laUpdate['UsrHora'] = $tdAhora->format('H:i:s');

            DB::connection($this->pcConexion)->table($tcTabla)->where($tcPk, $tnId)->update($laUpdate);

            $loNuevo = DB::connection($this->pcConexion)->table($tcTabla)->where($tcPk, $tnId)->first();
            $laAntes = $this->normalizar($loActual);
            $laDespues = $this->normalizar($loNuevo);
            $this->toAuditoria->registrar($tcTabla, $tnId, 'ACTUALIZAR', $laAntes, $laDespues, $tnUsuario, $taDatos['Motivo'] ?? null);

            return ['Estado' => 'OK', 'Datos' => $laDespues];
        });
    }

    private function eliminarGenerico(string $tcTabla, string $tcPk, int $tnId, string $tcVersion, int $tnUsuario, ?string $tcMotivo): array
    {
        return DB::connection($this->pcConexion)->transaction(function () use ($tcTabla, $tcPk, $tnId, $tcVersion, $tnUsuario, $tcMotivo): array {
            $loActual = DB::connection($this->pcConexion)->table($tcTabla)->where($tcPk, $tnId)->lockForUpdate()->first();
            if (!$loActual) {
                return ['Estado' => 'NO_ENCONTRADO'];
            }
            if (!$this->toControlVersion->coincide($tcVersion, $loActual)) {
                return ['Estado' => 'CONFLICTO_VERSION', 'Actual' => $this->normalizar($loActual)];
            }

            $tdAhora = now();
            DB::connection($this->pcConexion)->table($tcTabla)->where($tcPk, $tnId)->update([
                'Estado' => $this->toEstadoNegocio->inactivoGeneral(),
                'Usr' => $tnUsuario,
                'UsrFecha' => $tdAhora->toDateString(),
                'UsrHora' => $tdAhora->format('H:i:s'),
            ]);

            // Si es una imagen de producto, elimina el archivo fisico cuando ya no tenga referencias activas.
            if ($tcTabla === 'PRODUCTOIMAGEN') {
                $tcRutaImagen = trim((string)($loActual->RutaImagen ?? ''));
                if ($tcRutaImagen !== '') {
                    $tnReferenciasActivas = (int)DB::connection($this->pcConexion)
                        ->table('PRODUCTOIMAGEN')
                        ->where('RutaImagen', $tcRutaImagen)
                        ->where('Estado', $this->toEstadoNegocio->activoGeneral())
                        ->count();

                    if ($tnReferenciasActivas === 0) {
                        $this->toArchivoStorage->eliminar($tcRutaImagen);
                    }
                }
            }

            $loNuevo = DB::connection($this->pcConexion)->table($tcTabla)->where($tcPk, $tnId)->first();
            $laAntes = $this->normalizar($loActual);
            $laDespues = $this->normalizar($loNuevo);
            $this->toAuditoria->registrar($tcTabla, $tnId, 'ELIMINAR_LOGICO', $laAntes, $laDespues, $tnUsuario, $tcMotivo);

            return ['Estado' => 'OK', 'Datos' => $laDespues];
        });
    }

    private function normalizar(object $toFila): array
    {
        $la = (array)$toFila;
        $la['Version'] = $this->toControlVersion->versionDesdeFila($toFila);
        if (isset($la['RutaImagen']) && is_string($la['RutaImagen']) && $la['RutaImagen'] !== '') {
            $la['UrlImagen'] = $this->toArchivoStorage->resolverUrl($la['RutaImagen']);
        }
        return $la;
    }

    private function obtenerEmpresaProducto(int $tnProducto): int
    {
        $tnEmpresa = DB::connection($this->pcConexion)
            ->table('PRODUCTO')
            ->where('Producto', $tnProducto)
            ->value('Empresa');

        return $tnEmpresa !== null ? (int)$tnEmpresa : 0;
    }
}

<?php

namespace App\Modulos\Kiosko\Services;

use App\Soporte\ArchivoStorageService;
use App\Support\EstadoCatalogo;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class KioskoService
{
    private string $pcConexion = 'mysqlNegocio';

    public function __construct(
        private EstadoCatalogo $toEstadoCatalogo,
        private ArchivoStorageService $toArchivoStorage
    ) {
    }

    /**
     * @return array{Estado:string,Datos?:array<string,mixed>,Meta?:array<string,mixed>}
     */
    public function CatalogoPorMaquina(int $tnMaquina): array
    {
        $tdInicio = microtime(true);

        $tnEstadoGeneralActivo = $this->obtenerEstado('GENERAL', 1, 1);
        $tnEstadoOcupacionActiva = $this->obtenerEstado('CELDAOCUPACION', 1, $tnEstadoGeneralActivo);

        $loMaquina = DB::connection($this->pcConexion)
            ->table('MAQUINA')
            ->select(['Maquina', 'CodigoMaquina'])
            ->where('Maquina', $tnMaquina)
            ->first();

        if (!$loMaquina) {
            return ['Estado' => 'NO_ENCONTRADO'];
        }

        $loPlanograma = DB::connection($this->pcConexion)
            ->table('PLANOGRAMA')
            ->select(['Planograma'])
            ->where('Maquina', $tnMaquina)
            ->where('Estado', $tnEstadoGeneralActivo)
            ->orderByDesc('Planograma')
            ->first();

        $tnPlanograma = $loPlanograma ? (int)$loPlanograma->Planograma : 0;
        $lbSinPlanograma = $tnPlanograma <= 0;

        $laCeldasBase = DB::connection($this->pcConexion)
            ->table('CELDA')
            ->select(['Celda', 'CodigoSeleccion', 'Fila', 'Columna', 'Estado'])
            ->where('Maquina', $tnMaquina)
            ->orderBy('Fila')
            ->orderBy('Columna')
            ->get()
            ->all();

        $laCeldasIds = array_values(array_map(static fn(object $toFila): int => (int)$toFila->Celda, $laCeldasBase));

        $laExistencia = $this->obtenerExistenciaPorCelda($laCeldasIds, $tnEstadoGeneralActivo);
        $laPrecios = $lbSinPlanograma ? [] : $this->obtenerPreciosPlanograma($tnPlanograma, $tnEstadoGeneralActivo);
        $laBloqueadas = $this->obtenerCeldasBloqueadas($laCeldasIds, $tnEstadoOcupacionActiva);
        $laImagenes = $this->obtenerPortadasProducto($laExistencia['Productos'] ?? [], $tnEstadoGeneralActivo);

        $laCeldas = [];
        foreach ($laCeldasBase as $toCelda) {
            $tnCelda = (int)$toCelda->Celda;
            $laEx = $laExistencia['PorCelda'][$tnCelda] ?? [
                'Stock' => 0,
                'Producto' => null,
                'CodigoProducto' => null,
                'NombreProducto' => null,
            ];

            $lbBloqueada = ((int)$toCelda->Estado !== $tnEstadoGeneralActivo) || isset($laBloqueadas[$tnCelda]);
            $tnStock = (int)($laEx['Stock'] ?? 0);
            $tnProducto = isset($laEx['Producto']) ? (int)$laEx['Producto'] : null;
            $tnPrecioVenta = !$lbSinPlanograma && array_key_exists($tnCelda, $laPrecios)
                ? (float)$laPrecios[$tnCelda]
                : null;

            $laImagen = $tnProducto !== null && $tnProducto > 0 ? ($laImagenes[$tnProducto] ?? null) : null;
            $tcUrlImagen = null;
            $tcHashImagen = null;
            $tcFechaImagen = null;

            if ($laImagen !== null) {
                $tcUrl = $this->toArchivoStorage->resolverUrl((string)$laImagen['RutaImagen']);
                $tcUrlImagen = trim($tcUrl) !== '' ? $tcUrl : null;
                $tcHashImagen = (string)$laImagen['HashImagen'];
                $tcFechaImagen = $laImagen['FechaActualizacionImagen'] !== '' ? (string)$laImagen['FechaActualizacionImagen'] : null;
            }

            $lbTieneProducto = !$lbBloqueada
                && $tnProducto !== null
                && $tnProducto > 0
                && $tnPrecioVenta !== null;

            if ($lbSinPlanograma) {
                $lbTieneProducto = false;
                $tnPrecioVenta = null;
            }

            $laCeldas[] = [
                'Celda' => $tnCelda,
                'NumeroCelda' => $tnCelda,
                'CodigoSeleccion' => (string)$toCelda->CodigoSeleccion,
                'Bloqueada' => $lbBloqueada ? 1 : 0,
                'Stock' => $tnStock,
                'TieneProducto' => $lbTieneProducto,
                'Producto' => $tnProducto,
                'CodigoProducto' => $laEx['CodigoProducto'] ?? null,
                'NombreProducto' => $laEx['NombreProducto'] ?? null,
                'PrecioVenta' => $tnPrecioVenta,
                'UrlImagen' => $tcUrlImagen,
                'HashImagen' => $tcHashImagen,
                'FechaActualizacionImagen' => $tcFechaImagen,
            ];
        }

        $tcVersionCatalogo = $this->generarVersionCatalogo($laCeldas);
        $tnMs = (int)round((microtime(true) - $tdInicio) * 1000);

        Log::info('kiosko_catalogo_generado', [
            'Maquina' => $tnMaquina,
            'TotalCeldas' => count($laCeldas),
            'VersionCatalogo' => $tcVersionCatalogo,
            'Milisegundos' => $tnMs,
        ]);

        if ($lbSinPlanograma) {
            Log::warning('kiosko_catalogo_sin_planograma', [
                'Maquina' => $tnMaquina,
            ]);
        }

        return [
            'Estado' => 'OK',
            'Datos' => [
                'Maquina' => (int)$loMaquina->Maquina,
                'CodigoMaquina' => (string)$loMaquina->CodigoMaquina,
                'VersionCatalogo' => $tcVersionCatalogo,
                'Celdas' => $laCeldas,
            ],
            'Meta' => [
                'Origen' => 'kiosko_unificado_v1',
                'SinPlanogramaActivo' => $lbSinPlanograma,
                'TotalCeldas' => count($laCeldas),
            ],
        ];
    }

    /**
     * @param array<int> $laCeldasIds
     * @return array{PorCelda:array<int,array<string,mixed>>,Productos:array<int>}
     */
    private function obtenerExistenciaPorCelda(array $laCeldasIds, int $tnEstadoGeneralActivo): array
    {
        if (count($laCeldasIds) === 0) {
            return ['PorCelda' => [], 'Productos' => []];
        }

        $laFilas = DB::connection($this->pcConexion)
            ->table('EXISTENCIACELDA as ec')
            ->leftJoin('PRODUCTOEMPRESA as pe', 'pe.ProductoEmpresa', '=', 'ec.ProductoEmpresa')
            ->leftJoin('PRODUCTO as p', 'p.Producto', '=', 'pe.Producto')
            ->select([
                'ec.Celda',
                'ec.CantidadDisponible',
                'pe.Producto',
                'p.CodigoProducto',
                'p.CodigoSku',
                'p.NombreProducto',
                'ec.ExistenciaCelda',
            ])
            ->whereIn('ec.Celda', $laCeldasIds)
            ->where('ec.Estado', $tnEstadoGeneralActivo)
            ->orderBy('ec.Celda')
            ->orderByDesc('ec.CantidadDisponible')
            ->orderByDesc('ec.ExistenciaCelda')
            ->get();

        $laPorCelda = [];
        $laProductos = [];
        foreach ($laFilas as $toFila) {
            $tnCelda = (int)$toFila->Celda;
            if (!isset($laPorCelda[$tnCelda])) {
                $tnProducto = isset($toFila->Producto) ? (int)$toFila->Producto : null;
                $laPorCelda[$tnCelda] = [
                    'Stock' => 0,
                    'Producto' => $tnProducto,
                    'CodigoProducto' => $toFila->CodigoProducto ?: ($toFila->CodigoSku ?: null),
                    'NombreProducto' => $toFila->NombreProducto ?: null,
                ];

                if ($tnProducto !== null && $tnProducto > 0) {
                    $laProductos[$tnProducto] = true;
                }
            }

            $laPorCelda[$tnCelda]['Stock'] += (int)$toFila->CantidadDisponible;
        }

        return [
            'PorCelda' => $laPorCelda,
            'Productos' => array_map('intval', array_keys($laProductos)),
        ];
    }

    /**
     * @return array<int,float>
     */
    private function obtenerPreciosPlanograma(int $tnPlanograma, int $tnEstadoGeneralActivo): array
    {
        $laFilas = DB::connection($this->pcConexion)
            ->table('PLANOGRAMACELDA')
            ->select(['Celda', 'PrecioVenta'])
            ->where('Planograma', $tnPlanograma)
            ->where('Estado', $tnEstadoGeneralActivo)
            ->orderBy('PlanogramaCelda')
            ->get();

        $laPrecios = [];
        foreach ($laFilas as $toFila) {
            $laPrecios[(int)$toFila->Celda] = $toFila->PrecioVenta !== null ? (float)$toFila->PrecioVenta : null;
        }
        return $laPrecios;
    }

    /**
     * @param array<int> $laCeldasIds
     * @return array<int,bool>
     */
    private function obtenerCeldasBloqueadas(array $laCeldasIds, int $tnEstadoOcupacionActiva): array
    {
        if (count($laCeldasIds) === 0) {
            return [];
        }

        $toSchema = Schema::connection($this->pcConexion);
        if (
            !$toSchema->hasTable('CELDAOCUPACIONDETALLE')
            || !$toSchema->hasTable('CELDAOCUPACION')
        ) {
            return [];
        }

        $laBloqueadas = DB::connection($this->pcConexion)
            ->table('CELDAOCUPACIONDETALLE as od')
            ->join('CELDAOCUPACION as o', 'o.CeldaOcupacion', '=', 'od.CeldaOcupacion')
            ->whereIn('od.Celda', $laCeldasIds)
            ->where('od.Estado', $tnEstadoOcupacionActiva)
            ->where('o.Estado', $tnEstadoOcupacionActiva)
            ->whereRaw('UPPER(od.TipoBloqueo) = ?', ['BLOQUEADA'])
            ->pluck('od.Celda')
            ->all();

        $la = [];
        foreach ($laBloqueadas as $tnCelda) {
            $la[(int)$tnCelda] = true;
        }

        return $la;
    }

    /**
     * @param array<int> $laProductos
     * @return array<int,array{RutaImagen:string,HashImagen:string,FechaActualizacionImagen:string}>
     */
    private function obtenerPortadasProducto(array $laProductos, int $tnEstadoGeneralActivo): array
    {
        if (count($laProductos) === 0) {
            return [];
        }

        $laFilas = DB::connection($this->pcConexion)
            ->table('PRODUCTOIMAGEN')
            ->select(['Producto', 'RutaImagen', 'UsrFecha', 'UsrHora', 'Orden', 'ProductoImagen'])
            ->whereIn('Producto', $laProductos)
            ->where('Estado', $tnEstadoGeneralActivo)
            ->orderBy('Producto')
            ->orderBy('Orden')
            ->orderBy('ProductoImagen')
            ->get();

        $laPorProducto = [];
        foreach ($laFilas as $toFila) {
            $tnProducto = (int)$toFila->Producto;
            if (isset($laPorProducto[$tnProducto])) {
                continue;
            }

            $tcRuta = (string)$toFila->RutaImagen;
            $tcMarcaTiempo = trim((string)($toFila->UsrFecha ?? '') . ' ' . (string)($toFila->UsrHora ?? ''));
            $tcHash = sha1($tcRuta . '|' . $tcMarcaTiempo);

            $laPorProducto[$tnProducto] = [
                'RutaImagen' => $tcRuta,
                'HashImagen' => $tcHash,
                'FechaActualizacionImagen' => $this->normalizarFechaLaPaz($toFila->UsrFecha ?? null, $toFila->UsrHora ?? null),
            ];
        }

        return $laPorProducto;
    }

    /**
     * @param array<int,array<string,mixed>> $laCeldas
     */
    private function generarVersionCatalogo(array $laCeldas): string
    {
        $laLineas = [];
        foreach ($laCeldas as $laCelda) {
            $tcPrecio = $laCelda['PrecioVenta'] === null
                ? 'null'
                : number_format((float)$laCelda['PrecioVenta'], 2, '.', '');

            $laLineas[] = implode('|', [
                (string)($laCelda['Celda'] ?? '0'),
                (string)($laCelda['Stock'] ?? '0'),
                $tcPrecio,
                (string)($laCelda['Bloqueada'] ?? '0'),
                (string)($laCelda['Producto'] ?? 'null'),
                (string)($laCelda['HashImagen'] ?? 'null'),
            ]);
        }

        return sha1(implode("\n", $laLineas));
    }

    private function normalizarFechaLaPaz(mixed $tmFecha, mixed $tmHora): ?string
    {
        $tcFecha = trim((string)$tmFecha);
        $tcHora = trim((string)$tmHora);
        if ($tcFecha === '' || $tcHora === '') {
            return null;
        }

        $tcFechaHora = $tcFecha . ' ' . $tcHora;
        $tcZonaBase = (string)config('app.timezone', 'UTC');

        try {
            $toFecha = Carbon::createFromFormat('Y-m-d H:i:s', $tcFechaHora, $tcZonaBase);
            return $toFecha->setTimezone('America/La_Paz')->format('Y-m-d H:i:s');
        } catch (\Throwable) {
            try {
                $toFecha = Carbon::parse($tcFechaHora, $tcZonaBase);
                return $toFecha->setTimezone('America/La_Paz')->format('Y-m-d H:i:s');
            } catch (\Throwable) {
                return null;
            }
        }
    }

    private function obtenerEstado(string $tcEntidad, int $tnCodigo, int $tnFallback): int
    {
        try {
            return $this->toEstadoCatalogo->obtenerId($tcEntidad, $tnCodigo);
        } catch (RuntimeException) {
            return $tnFallback;
        }
    }
}

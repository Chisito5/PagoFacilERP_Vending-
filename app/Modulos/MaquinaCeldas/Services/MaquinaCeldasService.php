<?php

namespace App\Modulos\MaquinaCeldas\Services;

use App\Soporte\AuditoriaService;
use App\Soporte\AutorizacionNegocioService;
use App\Soporte\ControlVersionService;
use App\Support\EstadoCatalogo;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use stdClass;

class MaquinaCeldasService
{
    private string $pcConexion = 'mysqlNegocio';

    public function __construct(
        private EstadoCatalogo $toEstadoCatalogo,
        private ControlVersionService $toControlVersion,
        private AuditoriaService $toAuditoria,
        private AutorizacionNegocioService $toAutorizacion
    ) {
    }

    /**
     * SYSCOOP
     * category: Service
     * package: App\Modulos\MaquinaCeldas\Services
     * author: Vladimir Meriles Velasquez
     * fecha: 01-03-2026
     * param: int $tnMaquina, ?string $tcBusqueda, int $tnPagina, int $tnTamanoPagina, int $tnUsuarioSesion
     * return: array<string,mixed>
     *
     * Devuelve la matriz operacional de celdas (6x9) con estado disponible/ocupada/bloqueada.
     */
    public function Matriz(int $tnMaquina, ?string $tcBusqueda, int $tnPagina, int $tnTamanoPagina, int $tnUsuarioSesion): array
    {
        if (!$this->toAutorizacion->puedeAccederMaquina($tnUsuarioSesion, $tnMaquina)) {
            return ['Estado' => 'NO_AUTORIZADO'];
        }

        $this->asegurarMatriz54($tnMaquina);

        $tnTamanoPagina = max(1, min($tnTamanoPagina, 200));
        $tnEstadoGeneralActivo = $this->obtenerEstado('GENERAL', 1, 1);

        $toConsulta = DB::connection($this->pcConexion)
            ->table('CELDA as c')
            ->leftJoin('EXISTENCIACELDA as ec', 'ec.Celda', '=', 'c.Celda')
            ->select([
                'c.Celda',
                'c.Maquina',
                'c.CodigoSeleccion',
                'c.Fila',
                'c.Columna',
                'c.CapacidadMaxima',
                'c.AnchoMaximoMm',
                'c.AltoMaximoMm',
                'c.ProfundidadMaximaMm',
                'c.PesoMaximoGr',
                'c.PermiteGiro',
                'c.Estado',
                'c.UsrFecha',
                'c.UsrHora',
                DB::raw('COALESCE(SUM(ec.CantidadDisponible), 0) as CantidadDisponible'),
                DB::raw('COALESCE(SUM(ec.CantidadReservada), 0) as CantidadReservada'),
            ])
            ->where('c.Maquina', $tnMaquina)
            ->groupBy([
                'c.Celda',
                'c.Maquina',
                'c.CodigoSeleccion',
                'c.Fila',
                'c.Columna',
                'c.CapacidadMaxima',
                'c.AnchoMaximoMm',
                'c.AltoMaximoMm',
                'c.ProfundidadMaximaMm',
                'c.PesoMaximoGr',
                'c.PermiteGiro',
                'c.Estado',
                'c.UsrFecha',
                'c.UsrHora',
            ])
            ->orderBy('c.Fila')
            ->orderBy('c.Columna');

        if ($tcBusqueda !== null && trim($tcBusqueda) !== '') {
            $tcBusqueda = trim($tcBusqueda);
            $toConsulta->where(function ($toWhere) use ($tcBusqueda): void {
                $toWhere->where('c.CodigoSeleccion', 'like', '%' . $tcBusqueda . '%')
                    ->orWhereRaw('CAST(c.Celda as char) like ?', ['%' . $tcBusqueda . '%']);
            });
        }

        $laBase = $toConsulta->get()->all();
        $laCeldasIds = array_values(array_map(static fn(object $toFila): int => (int)$toFila->Celda, $laBase));
        $laOcupaciones = $this->obtenerOcupacionesActivasPorCelda($tnMaquina, $laCeldasIds);

        $laFilas = [];
        foreach ($laBase as $toFila) {
            $tnCelda = (int)$toFila->Celda;
            $loOcupacion = $laOcupaciones[$tnCelda] ?? null;
            $tcEstadoCelda = 'DISPONIBLE';
            $tcProductoActual = '';
            $tcBloqueadaPor = '';

            if ((int)$toFila->Estado !== $tnEstadoGeneralActivo) {
                $tcEstadoCelda = 'INACTIVA';
            } elseif ($loOcupacion !== null) {
                if (strtoupper((string)$loOcupacion->TipoBloqueo) === 'ANCLA') {
                    $tcEstadoCelda = 'OCUPADA';
                    $tcProductoActual = (string)($loOcupacion->NombreProducto ?? '');
                } else {
                    $tcEstadoCelda = 'BLOQUEADA';
                    $tcBloqueadaPor = 'ANCLA ' . (string)$loOcupacion->CeldaAncla;
                    $tcProductoActual = (string)($loOcupacion->NombreProducto ?? '');
                }
            }

            $laFila = [
                'IdCelda' => $tnCelda,
                'NumeroCelda' => $tnCelda,
                'Maquina' => (int)$toFila->Maquina,
                'Fila' => (int)($toFila->Fila ?? 0),
                'Columna' => (int)($toFila->Columna ?? 0),
                'CodigoSeleccion' => (string)$toFila->CodigoSeleccion,
                'EstadoCelda' => $tcEstadoCelda,
                'CapacidadMaxima' => (int)$toFila->CapacidadMaxima,
                'CantidadDisponible' => (int)$toFila->CantidadDisponible,
                'CantidadReservada' => (int)$toFila->CantidadReservada,
                'ProductoActual' => $tcProductoActual,
                'BloqueadaPor' => $tcBloqueadaPor,
                'Version' => $this->toControlVersion->versionDesdeFila($toFila),
                'UsrFecha' => (string)$toFila->UsrFecha,
                'UsrHora' => (string)$toFila->UsrHora,
            ];
            $laFilas[] = $laFila;
        }

        $tnPagina = max(1, $tnPagina);
        $tnTotal = count($laFilas);
        $tnOffset = ($tnPagina - 1) * $tnTamanoPagina;
        $laPaginadas = array_slice($laFilas, $tnOffset, $tnTamanoPagina);

        $toPaginador = new LengthAwarePaginator(
            $laPaginadas,
            $tnTotal,
            $tnTamanoPagina,
            $tnPagina,
            [
                'path' => request()->url(),
                'pageName' => 'Pagina',
            ]
        );

        return [
            'Estado' => 'OK',
            'Paginador' => $toPaginador,
        ];
    }

    /**
     * SYSCOOP
     * category: Service
     * package: App\Modulos\MaquinaCeldas\Services
     * author: Vladimir Meriles Velasquez
     * fecha: 01-03-2026
     * param: int $tnMaquina, int $tnPagina, int $tnTamanoPagina, int $tnUsuarioSesion
     * return: array<string,mixed>
     *
     * Lista conflictos operativos registrados de la matriz de celdas.
     */
    public function Conflictos(int $tnMaquina, int $tnPagina, int $tnTamanoPagina, int $tnUsuarioSesion): array
    {
        if (!$this->toAutorizacion->puedeAccederMaquina($tnUsuarioSesion, $tnMaquina)) {
            return ['Estado' => 'NO_AUTORIZADO'];
        }

        $tnTamanoPagina = max(1, min($tnTamanoPagina, 200));

        $toPaginador = DB::connection($this->pcConexion)
            ->table('CELDACONFLICTO')
            ->select([
                'CeldaConflicto as IdConflicto',
                'Celda',
                'Tipo',
                'Detalle',
                DB::raw('DATE_FORMAT(FechaHora, "%Y-%m-%d %H:%i:%s") as Fecha'),
                'UsrFecha',
                'UsrHora',
            ])
            ->where('Maquina', $tnMaquina)
            ->orderByDesc('CeldaConflicto')
            ->paginate($tnTamanoPagina, ['*'], 'Pagina', max(1, $tnPagina));

        return ['Estado' => 'OK', 'Paginador' => $toPaginador];
    }

    /**
     * SYSCOOP
     * category: Service
     * package: App\Modulos\MaquinaCeldas\Services
     * author: Vladimir Meriles Velasquez
     * fecha: 01-03-2026
     * param: int $tnMaquina, array<string,mixed> $taDatos, int $tnUsuarioSesion
     * return: array<string,mixed>
     *
     * Simula ocupacion de celdas por ancla y span sin aplicar cambios.
     */
    public function SimularOcupacion(int $tnMaquina, array $taDatos, int $tnUsuarioSesion): array
    {
        if (!$this->toAutorizacion->puedeAccederMaquina($tnUsuarioSesion, $tnMaquina)) {
            return ['Estado' => 'NO_AUTORIZADO'];
        }

        $this->asegurarMatriz54($tnMaquina);

        $tnCeldaAncla = (int)($taDatos['CeldaAncla'] ?? 0);
        $tnSpanColumnas = max(1, (int)($taDatos['SpanColumnas'] ?? 1));
        $tnSpanFilas = max(1, (int)($taDatos['SpanFilas'] ?? 1));
        $tnProducto = (int)($taDatos['Producto'] ?? 0);

        $laSimulacion = $this->resolverSimulacion($tnMaquina, $tnCeldaAncla, $tnSpanColumnas, $tnSpanFilas, $tnProducto, false);
        if ($laSimulacion['Estado'] !== 'OK') {
            return $laSimulacion;
        }

        return [
            'Estado' => count($laSimulacion['Conflictos']) > 0 ? 'CONFLICTO' : 'OK',
            'Datos' => [
                'CeldaAncla' => $tnCeldaAncla,
                'SpanColumnas' => $tnSpanColumnas,
                'SpanFilas' => $tnSpanFilas,
                'Producto' => $tnProducto,
                'CeldasAfectadas' => $laSimulacion['CeldasAfectadas'],
                'Conflictos' => $laSimulacion['Conflictos'],
                'Apta' => count($laSimulacion['Conflictos']) === 0,
            ],
        ];
    }

    /**
     * SYSCOOP
     * category: Service
     * package: App\Modulos\MaquinaCeldas\Services
     * author: Vladimir Meriles Velasquez
     * fecha: 01-03-2026
     * param: int $tnMaquina, array<string,mixed> $taDatos, int $tnUsuarioSesion
     * return: array<string,mixed>
     *
     * Asigna producto por celda ancla y bloquea celdas afectadas.
     */
    public function AsignarProducto(int $tnMaquina, array $taDatos, int $tnUsuarioSesion): array
    {
        return DB::connection($this->pcConexion)->transaction(function () use ($tnMaquina, $taDatos, $tnUsuarioSesion): array {
            if (!$this->toAutorizacion->puedeAccederMaquina($tnUsuarioSesion, $tnMaquina)) {
                return ['Estado' => 'NO_AUTORIZADO'];
            }

            $this->asegurarMatriz54($tnMaquina);

            $tnCeldaAncla = (int)($taDatos['CeldaAncla'] ?? 0);
            $tnSpanColumnas = max(1, (int)($taDatos['SpanColumnas'] ?? 1));
            $tnSpanFilas = max(1, (int)($taDatos['SpanFilas'] ?? 1));
            $tnProducto = (int)($taDatos['Producto'] ?? 0);
            $tnLote = isset($taDatos['Lote']) && (int)$taDatos['Lote'] > 0 ? (int)$taDatos['Lote'] : null;
            $tnCantidad = max(0, (int)($taDatos['Cantidad'] ?? 0));
            $tcVersion = (string)($taDatos['Version'] ?? '');
            $tcMotivo = isset($taDatos['Motivo']) ? (string)$taDatos['Motivo'] : null;
            $tdAhora = now();

            $tnEstadoGeneralActivo = $this->obtenerEstado('GENERAL', 1, 1);
            $tnEstadoOcupacionActiva = $this->obtenerEstado('CELDAOCUPACION', 1, $tnEstadoGeneralActivo);
            $tnEstadoConflictoAbierto = $this->obtenerEstado('CELDACONFLICTO', 1, $tnEstadoGeneralActivo);

            $loCeldaAncla = DB::connection($this->pcConexion)
                ->table('CELDA')
                ->where('Maquina', $tnMaquina)
                ->where('Celda', $tnCeldaAncla)
                ->lockForUpdate()
                ->first();

            if (!$loCeldaAncla) {
                return ['Estado' => 'NO_ENCONTRADO'];
            }

            if (!$this->toControlVersion->coincide($tcVersion, $loCeldaAncla)) {
                return [
                    'Estado' => 'CONFLICTO_VERSION',
                    'Actual' => [
                        'IdCelda' => (int)$loCeldaAncla->Celda,
                        'Version' => $this->toControlVersion->versionDesdeFila($loCeldaAncla),
                    ],
                ];
            }

            $loProducto = DB::connection($this->pcConexion)
                ->table('PRODUCTO')
                ->where('Producto', $tnProducto)
                ->where('Estado', $tnEstadoGeneralActivo)
                ->first();
            if (!$loProducto) {
                return ['Estado' => 'PRODUCTO_NO_ENCONTRADO'];
            }

            if ($tnLote !== null) {
                $loLote = DB::connection($this->pcConexion)
                    ->table('LOTE')
                    ->where('Lote', $tnLote)
                    ->where('Estado', $tnEstadoGeneralActivo)
                    ->first();
                if (!$loLote) {
                    return ['Estado' => 'LOTE_NO_ENCONTRADO'];
                }
            }

            $laSimulacion = $this->resolverSimulacion($tnMaquina, $tnCeldaAncla, $tnSpanColumnas, $tnSpanFilas, $tnProducto, true);
            if ($laSimulacion['Estado'] !== 'OK') {
                return $laSimulacion;
            }

            if (count($laSimulacion['Conflictos']) > 0) {
                foreach ($laSimulacion['Conflictos'] as $tcDetalle) {
                    $this->registrarConflicto($tnMaquina, null, 'ASIGNACION', $tcDetalle, $tnEstadoConflictoAbierto, $tnUsuarioSesion, $tdAhora);
                }

                return [
                    'Estado' => 'CONFLICTO',
                    'Datos' => [
                        'CeldasAfectadas' => $laSimulacion['CeldasAfectadas'],
                        'Conflictos' => $laSimulacion['Conflictos'],
                    ],
                ];
            }

            $tnOcupacion = (int)DB::connection($this->pcConexion)->table('CELDAOCUPACION')->insertGetId([
                'Maquina' => $tnMaquina,
                'CeldaAncla' => $tnCeldaAncla,
                'Producto' => $tnProducto,
                'Lote' => $tnLote,
                'Cantidad' => $tnCantidad,
                'SpanColumnas' => $tnSpanColumnas,
                'SpanFilas' => $tnSpanFilas,
                'Estado' => $tnEstadoOcupacionActiva,
                'Usr' => $tnUsuarioSesion,
                'UsrFecha' => $tdAhora->toDateString(),
                'UsrHora' => $tdAhora->format('H:i:s'),
            ]);

            foreach ($laSimulacion['CeldasAfectadas'] as $tnCelda) {
                DB::connection($this->pcConexion)->table('CELDAOCUPACIONDETALLE')->insert([
                    'CeldaOcupacion' => $tnOcupacion,
                    'Celda' => (int)$tnCelda,
                    'TipoBloqueo' => ((int)$tnCelda === $tnCeldaAncla) ? 'ANCLA' : 'BLOQUEADA',
                    'Estado' => $tnEstadoOcupacionActiva,
                    'Usr' => $tnUsuarioSesion,
                    'UsrFecha' => $tdAhora->toDateString(),
                    'UsrHora' => $tdAhora->format('H:i:s'),
                ]);
            }

            $this->toAuditoria->registrar(
                'CELDAOCUPACION',
                $tnOcupacion,
                'ASIGNAR_PRODUCTO',
                null,
                [
                    'Maquina' => $tnMaquina,
                    'CeldaAncla' => $tnCeldaAncla,
                    'Producto' => $tnProducto,
                    'Lote' => $tnLote,
                    'Cantidad' => $tnCantidad,
                    'SpanColumnas' => $tnSpanColumnas,
                    'SpanFilas' => $tnSpanFilas,
                    'CeldasAfectadas' => $laSimulacion['CeldasAfectadas'],
                ],
                $tnUsuarioSesion,
                $tcMotivo
            );

            return [
                'Estado' => 'OK',
                'Datos' => [
                    'CeldaOcupacion' => $tnOcupacion,
                    'Maquina' => $tnMaquina,
                    'CeldaAncla' => $tnCeldaAncla,
                    'Producto' => $tnProducto,
                    'Lote' => $tnLote,
                    'Cantidad' => $tnCantidad,
                    'SpanColumnas' => $tnSpanColumnas,
                    'SpanFilas' => $tnSpanFilas,
                    'CeldasAfectadas' => $laSimulacion['CeldasAfectadas'],
                ],
            ];
        });
    }

    /**
     * SYSCOOP
     * category: Service
     * package: App\Modulos\MaquinaCeldas\Services
     * author: Vladimir Meriles Velasquez
     * fecha: 01-03-2026
     * param: int $tnMaquina, array<string,mixed> $taDatos, int $tnUsuarioSesion
     * return: array<string,mixed>
     *
     * Libera ocupacion registrada desde la celda ancla.
     */
    public function Liberar(int $tnMaquina, array $taDatos, int $tnUsuarioSesion): array
    {
        return DB::connection($this->pcConexion)->transaction(function () use ($tnMaquina, $taDatos, $tnUsuarioSesion): array {
            if (!$this->toAutorizacion->puedeAccederMaquina($tnUsuarioSesion, $tnMaquina)) {
                return ['Estado' => 'NO_AUTORIZADO'];
            }

            $this->asegurarMatriz54($tnMaquina);

            $tnCeldaAncla = (int)($taDatos['CeldaAncla'] ?? 0);
            $tcVersion = (string)($taDatos['Version'] ?? '');
            $tcMotivo = isset($taDatos['Motivo']) ? (string)$taDatos['Motivo'] : null;
            $tdAhora = now();

            $tnEstadoGeneralActivo = $this->obtenerEstado('GENERAL', 1, 1);
            $tnEstadoOcupacionActiva = $this->obtenerEstado('CELDAOCUPACION', 1, $tnEstadoGeneralActivo);
            $tnEstadoOcupacionLiberada = $this->obtenerEstado('CELDAOCUPACION', 2, $this->obtenerEstado('GENERAL', 2, 2));

            $loCeldaAncla = DB::connection($this->pcConexion)
                ->table('CELDA')
                ->where('Maquina', $tnMaquina)
                ->where('Celda', $tnCeldaAncla)
                ->lockForUpdate()
                ->first();

            if (!$loCeldaAncla) {
                return ['Estado' => 'NO_ENCONTRADO'];
            }

            if (!$this->toControlVersion->coincide($tcVersion, $loCeldaAncla)) {
                return [
                    'Estado' => 'CONFLICTO_VERSION',
                    'Actual' => [
                        'IdCelda' => (int)$loCeldaAncla->Celda,
                        'Version' => $this->toControlVersion->versionDesdeFila($loCeldaAncla),
                    ],
                ];
            }

            $loOcupacion = DB::connection($this->pcConexion)
                ->table('CELDAOCUPACION')
                ->where('Maquina', $tnMaquina)
                ->where('CeldaAncla', $tnCeldaAncla)
                ->where('Estado', $tnEstadoOcupacionActiva)
                ->lockForUpdate()
                ->orderByDesc('CeldaOcupacion')
                ->first();

            if (!$loOcupacion) {
                return ['Estado' => 'NO_OCUPACION'];
            }

            DB::connection($this->pcConexion)
                ->table('CELDAOCUPACION')
                ->where('CeldaOcupacion', (int)$loOcupacion->CeldaOcupacion)
                ->update([
                    'Estado' => $tnEstadoOcupacionLiberada,
                    'Usr' => $tnUsuarioSesion,
                    'UsrFecha' => $tdAhora->toDateString(),
                    'UsrHora' => $tdAhora->format('H:i:s'),
                ]);

            $laCeldas = DB::connection($this->pcConexion)
                ->table('CELDAOCUPACIONDETALLE')
                ->where('CeldaOcupacion', (int)$loOcupacion->CeldaOcupacion)
                ->where('Estado', $tnEstadoOcupacionActiva)
                ->pluck('Celda')
                ->map(static fn($tmCelda): int => (int)$tmCelda)
                ->all();

            DB::connection($this->pcConexion)
                ->table('CELDAOCUPACIONDETALLE')
                ->where('CeldaOcupacion', (int)$loOcupacion->CeldaOcupacion)
                ->where('Estado', $tnEstadoOcupacionActiva)
                ->update([
                    'Estado' => $tnEstadoOcupacionLiberada,
                    'Usr' => $tnUsuarioSesion,
                    'UsrFecha' => $tdAhora->toDateString(),
                    'UsrHora' => $tdAhora->format('H:i:s'),
                ]);

            $this->toAuditoria->registrar(
                'CELDAOCUPACION',
                (int)$loOcupacion->CeldaOcupacion,
                'LIBERAR_OCUPACION',
                (array)$loOcupacion,
                [
                    'Estado' => $tnEstadoOcupacionLiberada,
                    'CeldasLiberadas' => $laCeldas,
                ],
                $tnUsuarioSesion,
                $tcMotivo
            );

            return [
                'Estado' => 'OK',
                'Datos' => [
                    'CeldaOcupacion' => (int)$loOcupacion->CeldaOcupacion,
                    'CeldaAncla' => $tnCeldaAncla,
                    'CeldasLiberadas' => $laCeldas,
                    'TotalCeldasLiberadas' => count($laCeldas),
                ],
            ];
        });
    }

    /**
     * @return array<string,mixed>
     */
    private function resolverSimulacion(
        int $tnMaquina,
        int $tnCeldaAncla,
        int $tnSpanColumnas,
        int $tnSpanFilas,
        int $tnProducto,
        bool $lbBloquear
    ): array {
        $tnEstadoGeneralActivo = $this->obtenerEstado('GENERAL', 1, 1);
        $tnEstadoOcupacionActiva = $this->obtenerEstado('CELDAOCUPACION', 1, $tnEstadoGeneralActivo);

        $toCeldaAncla = DB::connection($this->pcConexion)
            ->table('CELDA')
            ->where('Maquina', $tnMaquina)
            ->where('Celda', $tnCeldaAncla);
        if ($lbBloquear) {
            $toCeldaAncla->lockForUpdate();
        }

        $loCeldaAncla = $toCeldaAncla->first();
        if (!$loCeldaAncla) {
            return ['Estado' => 'NO_ENCONTRADO'];
        }

        $tnFilaAncla = (int)($loCeldaAncla->Fila ?? 0);
        $tnColumnaAncla = (int)($loCeldaAncla->Columna ?? 0);
        if ($tnFilaAncla <= 0 || $tnColumnaAncla <= 0) {
            return ['Estado' => 'NO_ENCONTRADO'];
        }

        $tnFilaMax = $tnFilaAncla + $tnSpanFilas - 1;
        $tnColumnaMax = $tnColumnaAncla + $tnSpanColumnas - 1;

        $toCeldas = DB::connection($this->pcConexion)
            ->table('CELDA')
            ->where('Maquina', $tnMaquina)
            ->whereBetween('Fila', [$tnFilaAncla, $tnFilaMax])
            ->whereBetween('Columna', [$tnColumnaAncla, $tnColumnaMax]);
        if ($lbBloquear) {
            $toCeldas->lockForUpdate();
        }

        $laRango = $toCeldas->get()->all();
        $laMapa = [];
        foreach ($laRango as $loCelda) {
            $laMapa[(int)$loCelda->Fila . '-' . (int)$loCelda->Columna] = $loCelda;
        }

        $laCeldasAfectadas = [];
        $laConflictos = [];
        for ($tnFila = $tnFilaAncla; $tnFila <= $tnFilaMax; $tnFila++) {
            for ($tnColumna = $tnColumnaAncla; $tnColumna <= $tnColumnaMax; $tnColumna++) {
                $tcLlave = $tnFila . '-' . $tnColumna;
                if (!isset($laMapa[$tcLlave])) {
                    $laConflictos[] = 'Posicion fuera de rango: fila ' . $tnFila . ', columna ' . $tnColumna . '.';
                    continue;
                }

                /** @var stdClass $loCelda */
                $loCelda = $laMapa[$tcLlave];
                $laCeldasAfectadas[] = (int)$loCelda->Celda;
                if ((int)$loCelda->Estado !== $tnEstadoGeneralActivo) {
                    $laConflictos[] = 'Celda ' . (int)$loCelda->Celda . ' inactiva para operacion.';
                }
            }
        }

        if (count($laCeldasAfectadas) > 0) {
            $toOcupadas = DB::connection($this->pcConexion)
                ->table('CELDAOCUPACIONDETALLE as od')
                ->join('CELDAOCUPACION as o', 'o.CeldaOcupacion', '=', 'od.CeldaOcupacion')
                ->leftJoin('PRODUCTO as p', 'p.Producto', '=', 'o.Producto')
                ->select([
                    'od.Celda',
                    'od.TipoBloqueo',
                    'o.CeldaAncla',
                    'o.Producto',
                    'p.NombreProducto',
                ])
                ->whereIn('od.Celda', $laCeldasAfectadas)
                ->where('od.Estado', $tnEstadoOcupacionActiva)
                ->where('o.Estado', $tnEstadoOcupacionActiva);

            if ($lbBloquear) {
                $toOcupadas->lockForUpdate();
            }

            $laOcupadas = $toOcupadas->get()->all();
            foreach ($laOcupadas as $loOcupada) {
                $laConflictos[] = 'Celda ' . (int)$loOcupada->Celda . ' ya ocupada por ' . (string)($loOcupada->NombreProducto ?? 'producto') . '.';
            }
        }

        if ($tnProducto > 0) {
            $loProducto = DB::connection($this->pcConexion)->table('PRODUCTO')->where('Producto', $tnProducto)->first();
            if ($loProducto) {
                foreach ($laCeldasAfectadas as $tnCelda) {
                    $loCelda = null;
                    foreach ($laMapa as $toItem) {
                        if ((int)$toItem->Celda === $tnCelda) {
                            $loCelda = $toItem;
                            break;
                        }
                    }
                    if ($loCelda === null) {
                        continue;
                    }

                    $laErroresDim = $this->validarDimensionesProductoEnCelda($loProducto, $loCelda);
                    foreach ($laErroresDim as $tcError) {
                        $laConflictos[] = 'Celda ' . $tnCelda . ': ' . $tcError;
                    }
                }
            }
        }

        return [
            'Estado' => 'OK',
            'CeldasAfectadas' => array_values(array_unique($laCeldasAfectadas)),
            'Conflictos' => $laConflictos,
        ];
    }

    /**
     * @param array<int,int> $laCeldasIds
     * @return array<int,stdClass>
     */
    private function obtenerOcupacionesActivasPorCelda(int $tnMaquina, array $laCeldasIds): array
    {
        if (count($laCeldasIds) === 0) {
            return [];
        }

        $tnEstadoGeneralActivo = $this->obtenerEstado('GENERAL', 1, 1);
        $tnEstadoOcupacionActiva = $this->obtenerEstado('CELDAOCUPACION', 1, $tnEstadoGeneralActivo);

        $laFilas = DB::connection($this->pcConexion)
            ->table('CELDAOCUPACIONDETALLE as od')
            ->join('CELDAOCUPACION as o', 'o.CeldaOcupacion', '=', 'od.CeldaOcupacion')
            ->leftJoin('PRODUCTO as p', 'p.Producto', '=', 'o.Producto')
            ->select([
                'od.Celda',
                'od.TipoBloqueo',
                'o.CeldaAncla',
                'o.CeldaOcupacion',
                'o.Producto',
                'p.NombreProducto',
            ])
            ->where('o.Maquina', $tnMaquina)
            ->whereIn('od.Celda', $laCeldasIds)
            ->where('od.Estado', $tnEstadoOcupacionActiva)
            ->where('o.Estado', $tnEstadoOcupacionActiva)
            ->orderByDesc('o.CeldaOcupacion')
            ->get();

        $laMapa = [];
        foreach ($laFilas as $loFila) {
            $tnCelda = (int)$loFila->Celda;
            if (!isset($laMapa[$tnCelda])) {
                $laMapa[$tnCelda] = $loFila;
            }
        }

        return $laMapa;
    }

    /**
     * @return array<int,string>
     */
    private function validarDimensionesProductoEnCelda(object $toProducto, object $toCelda): array
    {
        $laErrores = [];

        $tnAnchoProducto = (int)($toProducto->AnchoMm ?? 0);
        $tnAltoProducto = (int)($toProducto->AltoMm ?? 0);
        $tnProfundidadProducto = (int)($toProducto->ProfundidadMm ?? 0);
        $tnPesoProducto = (float)($toProducto->PesoGr ?? ($toProducto->PesoGramos ?? 0));
        $lbProductoPermiteGiro = ((int)($toProducto->PermiteGiro ?? 0)) === 1;

        $tnAnchoMaximo = (int)($toCelda->AnchoMaximoMm ?? 0);
        $tnAltoMaximo = (int)($toCelda->AltoMaximoMm ?? 0);
        $tnProfundidadMaxima = (int)($toCelda->ProfundidadMaximaMm ?? 0);
        $tnPesoMaximo = (float)($toCelda->PesoMaximoGr ?? 0);
        $lbCeldaPermiteGiro = ((int)($toCelda->PermiteGiro ?? 0)) === 1;

        if ($tnAnchoMaximo > 0 && $tnAltoMaximo > 0 && $tnAnchoProducto > 0 && $tnAltoProducto > 0) {
            $lbDirecto = $tnAnchoProducto <= $tnAnchoMaximo && $tnAltoProducto <= $tnAltoMaximo;
            $lbGiro = $lbProductoPermiteGiro && $lbCeldaPermiteGiro
                && $tnAltoProducto <= $tnAnchoMaximo && $tnAnchoProducto <= $tnAltoMaximo;
            if (!$lbDirecto && !$lbGiro) {
                $laErrores[] = 'Dimensiones alto/ancho no compatibles con la celda.';
            }
        } else {
            if ($tnAnchoMaximo > 0 && $tnAnchoProducto > $tnAnchoMaximo) {
                $laErrores[] = 'Ancho excede limite de celda.';
            }
            if ($tnAltoMaximo > 0 && $tnAltoProducto > $tnAltoMaximo) {
                $laErrores[] = 'Alto excede limite de celda.';
            }
        }

        if ($tnProfundidadMaxima > 0 && $tnProfundidadProducto > $tnProfundidadMaxima) {
            $laErrores[] = 'Profundidad excede limite de celda.';
        }
        if ($tnPesoMaximo > 0 && $tnPesoProducto > $tnPesoMaximo) {
            $laErrores[] = 'Peso excede limite de celda.';
        }

        return $laErrores;
    }

    private function registrarConflicto(
        int $tnMaquina,
        ?int $tnCelda,
        string $tcTipo,
        string $tcDetalle,
        int $tnEstado,
        int $tnUsuario,
        \Illuminate\Support\Carbon $tdAhora
    ): void {
        DB::connection($this->pcConexion)->table('CELDACONFLICTO')->insert([
            'Maquina' => $tnMaquina,
            'Celda' => $tnCelda,
            'Tipo' => mb_strtoupper($tcTipo),
            'Detalle' => $tcDetalle,
            'FechaHora' => $tdAhora,
            'Estado' => $tnEstado,
            'Usr' => $tnUsuario,
            'UsrFecha' => $tdAhora->toDateString(),
            'UsrHora' => $tdAhora->format('H:i:s'),
        ]);
    }

    private function asegurarMatriz54(int $tnMaquina): void
    {
        $tnEstadoGeneralActivo = $this->obtenerEstado('GENERAL', 1, 1);
        $tdAhora = now();

        DB::connection($this->pcConexion)
            ->table('MAQUINA')
            ->where('Maquina', $tnMaquina)
            ->update([
                'FilasMatriz' => 6,
                'ColumnasMatriz' => 9,
                'UsrFecha' => $tdAhora->toDateString(),
                'UsrHora' => $tdAhora->format('H:i:s'),
            ]);

        $laActivas = DB::connection($this->pcConexion)
            ->table('CELDA')
            ->where('Maquina', $tnMaquina)
            ->where('Estado', $tnEstadoGeneralActivo)
            ->orderBy('Celda')
            ->get();

        $laPorPosicion = [];
        foreach ($laActivas as $loCelda) {
            $tnFila = (int)($loCelda->Fila ?? 0);
            $tnColumna = (int)($loCelda->Columna ?? 0);
            if ($tnFila < 1 || $tnFila > 6 || $tnColumna < 1 || $tnColumna > 9) {
                continue;
            }
            $tcLlave = $tnFila . '-' . $tnColumna;
            if (!isset($laPorPosicion[$tcLlave])) {
                $laPorPosicion[$tcLlave] = true;
            }
        }

        for ($tnFila = 1; $tnFila <= 6; $tnFila++) {
            for ($tnColumna = 1; $tnColumna <= 9; $tnColumna++) {
                $tcLlave = $tnFila . '-' . $tnColumna;
                if (isset($laPorPosicion[$tcLlave])) {
                    continue;
                }

                DB::connection($this->pcConexion)->table('CELDA')->insert([
                    'Maquina' => $tnMaquina,
                    'CodigoSeleccion' => $this->generarCodigoSeleccion($tnMaquina, $tnFila, $tnColumna),
                    'Fila' => $tnFila,
                    'Columna' => $tnColumna,
                    'CapacidadMaxima' => 0,
                    'Estado' => $tnEstadoGeneralActivo,
                    'Usr' => 0,
                    'UsrFecha' => $tdAhora->toDateString(),
                    'UsrHora' => $tdAhora->format('H:i:s'),
                ]);
            }
        }
    }

    private function generarCodigoSeleccion(int $tnMaquina, int $tnFila, int $tnColumna): string
    {
        $tcBase = chr(64 + $tnFila) . $tnColumna;
        $tcCodigo = $tcBase;
        $tnSecuencia = 2;
        while (DB::connection($this->pcConexion)
            ->table('CELDA')
            ->where('Maquina', $tnMaquina)
            ->where('CodigoSeleccion', $tcCodigo)
            ->exists()) {
            $tcCodigo = $tcBase . '_' . $tnSecuencia;
            $tnSecuencia++;
        }

        return $tcCodigo;
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

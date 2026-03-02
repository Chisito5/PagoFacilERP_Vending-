<?php

namespace App\Modulos\Maquina\Services;

use App\Soporte\AuditoriaService;
use App\Soporte\ControlVersionService;
use App\Support\EstadoCatalogo;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class MaquinaService
{
    private string $pcConexion = 'mysqlNegocio';

    public function __construct(
        private EstadoCatalogo $toEstadoCatalogo,
        private ControlVersionService $toControlVersion,
        private AuditoriaService $toAuditoria
    ) {
    }

    public function Listar(
        ?int $tnEmpresa = null,
        ?int $tnEstado = null,
        ?string $tcBusqueda = null,
        int $tnPagina = 1,
        int $tnTamanoPagina = 20
    ): LengthAwarePaginator {
        $tnTamanoPagina = max(1, min($tnTamanoPagina, 200));

        $loConsulta = DB::connection($this->pcConexion)
            ->table('MAQUINA as m')
            ->leftJoin('UBICACION as u', 'u.Ubicacion', '=', 'm.UbicacionActual')
            ->select([
                'm.Maquina',
                'm.CodigoMaquina',
                'm.NumeroSerie',
                'm.Marca',
                'm.Modelo',
                'm.IdentificadorConexion',
                'm.UbicacionActual',
                'm.FilasMatriz',
                'm.ColumnasMatriz',
                'u.Empresa as Empresa',
                'u.NombreUbicacion',
                'm.Estado',
                'm.Usr',
                'm.UsrFecha',
                'm.UsrHora',
            ])
            ->orderByDesc('m.Maquina');

        if ($tnEmpresa !== null && $tnEmpresa > 0) {
            $loConsulta->where('u.Empresa', $tnEmpresa);
        }
        if ($tnEstado !== null && $tnEstado > 0) {
            $loConsulta->where('m.Estado', $tnEstado);
        }
        if ($tcBusqueda !== null && trim($tcBusqueda) !== '') {
            $tcBusqueda = trim($tcBusqueda);
            $loConsulta->where(function ($toWhere) use ($tcBusqueda): void {
                $toWhere->where('m.CodigoMaquina', 'like', '%' . $tcBusqueda . '%')
                    ->orWhere('m.NumeroSerie', 'like', '%' . $tcBusqueda . '%')
                    ->orWhere('m.Marca', 'like', '%' . $tcBusqueda . '%')
                    ->orWhere('m.Modelo', 'like', '%' . $tcBusqueda . '%')
                    ->orWhere('u.NombreUbicacion', 'like', '%' . $tcBusqueda . '%');
            });
        }

        return $loConsulta->paginate($tnTamanoPagina, ['*'], 'Pagina', max(1, $tnPagina));
    }

    public function Obtener(int $tnMaquina): ?array
    {
        $loFila = DB::connection($this->pcConexion)
            ->table('MAQUINA')
            ->where('Maquina', $tnMaquina)
            ->first();

        return $loFila ? $this->normalizarFila($loFila) : null;
    }

    public function ListarCeldas(int $tnMaquina, int $tnPagina = 1, int $tnTamanoPagina = 50): LengthAwarePaginator
    {
        $tnTamanoPagina = max(1, min($tnTamanoPagina, 200));

        return DB::connection($this->pcConexion)
            ->table('CELDA as c')
            ->leftJoin('EXISTENCIACELDA as ec', 'ec.Celda', '=', 'c.Celda')
            ->select([
                'c.Celda',
                'c.Maquina',
                'c.CodigoSeleccion',
                'c.Fila',
                'c.Columna',
                'c.CapacidadMaxima',
                'c.Estado',
                DB::raw('COALESCE(ec.CantidadDisponible, 0) as CantidadDisponible'),
                DB::raw('COALESCE(ec.CantidadReservada, 0) as CantidadReservada'),
                'c.UsrFecha',
                'c.UsrHora',
            ])
            ->where('c.Maquina', $tnMaquina)
            ->orderBy('c.Fila')
            ->orderBy('c.Columna')
            ->paginate($tnTamanoPagina, ['*'], 'Pagina', max(1, $tnPagina));
    }

    /** @param array<string,mixed> $taDatos */
    public function Crear(array $taDatos, int $tnUsuario): array
    {
        $tdAhora = now();
        $tnEstadoActivo = $this->toEstadoCatalogo->obtenerId('GENERAL', 1);

        $tnId = DB::connection($this->pcConexion)
            ->table('MAQUINA')
            ->insertGetId([
                'CodigoMaquina' => $taDatos['CodigoMaquina'],
                'NumeroSerie' => $taDatos['NumeroSerie'] ?? null,
                'Marca' => $taDatos['Marca'] ?? null,
                'Modelo' => $taDatos['Modelo'] ?? null,
                'IdentificadorConexion' => $taDatos['IdentificadorConexion'],
                'UbicacionActual' => $taDatos['UbicacionActual'] ?? null,
                'FilasMatriz' => (int)($taDatos['FilasMatriz'] ?? 6),
                'ColumnasMatriz' => (int)($taDatos['ColumnasMatriz'] ?? 9),
                'Estado' => (int)($taDatos['Estado'] ?? $tnEstadoActivo),
                'Usr' => $tnUsuario,
                'UsrFecha' => $tdAhora->toDateString(),
                'UsrHora' => $tdAhora->format('H:i:s'),
            ]);

        $loNuevo = DB::connection($this->pcConexion)->table('MAQUINA')->where('Maquina', $tnId)->first();
        $laNuevo = $this->normalizarFila($loNuevo);
        $this->toAuditoria->registrar('MAQUINA', $tnId, 'CREAR', null, $laNuevo, $tnUsuario, $taDatos['Motivo'] ?? null);

        return $laNuevo;
    }

    /** @param array<string,mixed> $taDatos */
    public function Actualizar(int $tnMaquina, array $taDatos, string $tcVersion, int $tnUsuario, bool $lbParcial): array
    {
        return DB::connection($this->pcConexion)->transaction(function () use ($tnMaquina, $taDatos, $tcVersion, $tnUsuario, $lbParcial): array {
            $loActual = DB::connection($this->pcConexion)->table('MAQUINA')->where('Maquina', $tnMaquina)->lockForUpdate()->first();
            if (!$loActual) {
                return ['Estado' => 'NO_ENCONTRADO'];
            }
            if (!$this->toControlVersion->coincide($tcVersion, $loActual)) {
                return ['Estado' => 'CONFLICTO_VERSION', 'Actual' => $this->normalizarFila($loActual)];
            }

            $laCampos = [
                'CodigoMaquina',
                'NumeroSerie',
                'Marca',
                'Modelo',
                'IdentificadorConexion',
                'UbicacionActual',
                'FilasMatriz',
                'ColumnasMatriz',
                'Estado',
            ];

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

            DB::connection($this->pcConexion)->table('MAQUINA')->where('Maquina', $tnMaquina)->update($laUpdate);

            $loNuevo = DB::connection($this->pcConexion)->table('MAQUINA')->where('Maquina', $tnMaquina)->first();
            $laAntes = $this->normalizarFila($loActual);
            $laDespues = $this->normalizarFila($loNuevo);
            $this->toAuditoria->registrar('MAQUINA', $tnMaquina, 'ACTUALIZAR', $laAntes, $laDespues, $tnUsuario, $taDatos['Motivo'] ?? null);

            return ['Estado' => 'OK', 'Datos' => $laDespues];
        });
    }

    /**
     * @return array<string,mixed>
     */
    public function ListarFotos(int $tnMaquina): array
    {
        $loMaquina = DB::connection($this->pcConexion)
            ->table('MAQUINA as m')
            ->leftJoin('UBICACION as u', 'u.Ubicacion', '=', 'm.UbicacionActual')
            ->select([
                'm.Maquina',
                'm.CodigoMaquina',
                'm.UbicacionActual',
                'u.NombreUbicacion',
                'u.Direccion',
                'u.Ciudad',
                'u.Departamento',
                'u.Latitud',
                'u.Longitud',
            ])
            ->where('m.Maquina', $tnMaquina)
            ->first();

        if (!$loMaquina) {
            return ['Estado' => 'NO_ENCONTRADO'];
        }

        $tnEstadoActivo = $this->toEstadoCatalogo->obtenerId('GENERAL', 1);
        $laFotos = DB::connection($this->pcConexion)
            ->table('MAQUINAIMAGEN')
            ->where('Maquina', $tnMaquina)
            ->where('Estado', $tnEstadoActivo)
            ->orderBy('Orden')
            ->orderBy('MaquinaImagen')
            ->get()
            ->map(fn(object $toFila): array => $this->normalizarFoto($toFila))
            ->all();

        return [
            'Estado' => 'OK',
            'Datos' => [
                'Maquina' => [
                    'IdMaquina' => (int)$loMaquina->Maquina,
                    'CodigoMaquina' => (string)$loMaquina->CodigoMaquina,
                    'UbicacionActual' => (int)($loMaquina->UbicacionActual ?? 0),
                    'NombreUbicacion' => (string)($loMaquina->NombreUbicacion ?? ''),
                    'Direccion' => (string)($loMaquina->Direccion ?? ''),
                    'Ciudad' => (string)($loMaquina->Ciudad ?? ''),
                    'Departamento' => (string)($loMaquina->Departamento ?? ''),
                    'Latitud' => $loMaquina->Latitud !== null ? (float)$loMaquina->Latitud : null,
                    'Longitud' => $loMaquina->Longitud !== null ? (float)$loMaquina->Longitud : null,
                ],
                'Fotos' => $laFotos,
                'MinimoRequerido' => 3,
                'TotalFotos' => count($laFotos),
                'CumpleMinimo' => count($laFotos) >= 3,
            ],
        ];
    }

    /**
     * @param array<int,array<string,mixed>> $laFotos
     * @param array<int,UploadedFile> $laArchivos
     * @return array<string,mixed>
     */
    public function SubirFotosLote(int $tnMaquina, array $laFotos, array $laArchivos, int $tnUsuario, ?string $tcMotivo): array
    {
        return DB::connection($this->pcConexion)->transaction(function () use ($tnMaquina, $laFotos, $laArchivos, $tnUsuario, $tcMotivo): array {
            $loMaquina = DB::connection($this->pcConexion)
                ->table('MAQUINA')
                ->where('Maquina', $tnMaquina)
                ->lockForUpdate()
                ->first();
            if (!$loMaquina) {
                return ['Estado' => 'NO_ENCONTRADO'];
            }

            $tnEstadoActivo = $this->toEstadoCatalogo->obtenerId('GENERAL', 1);
            $tdAhora = now();

            $tnActual = (int)DB::connection($this->pcConexion)
                ->table('MAQUINAIMAGEN')
                ->where('Maquina', $tnMaquina)
                ->where('Estado', $tnEstadoActivo)
                ->count();

            $tnNuevasDesdeUrl = 0;
            foreach ($laFotos as $laFoto) {
                $tcRuta = trim((string)($laFoto['Url'] ?? ''));
                if ($tcRuta !== '') {
                    $tnNuevasDesdeUrl++;
                }
            }
            $tnNuevasDesdeArchivo = count($laArchivos);
            $tnTotalPosterior = $tnActual + $tnNuevasDesdeUrl + $tnNuevasDesdeArchivo;
            if ($tnTotalPosterior < 3) {
                return ['Estado' => 'MINIMO_FOTOS', 'TotalActual' => $tnActual, 'TotalPosterior' => $tnTotalPosterior];
            }

            $tnOrdenBase = (int)(DB::connection($this->pcConexion)
                ->table('MAQUINAIMAGEN')
                ->where('Maquina', $tnMaquina)
                ->max('Orden') ?? 0);

            $laInsertadas = [];
            $tnPos = 1;

            foreach ($laFotos as $laFoto) {
                $tcRuta = trim((string)($laFoto['Url'] ?? ''));
                if ($tcRuta === '') {
                    continue;
                }

                $tnId = (int)DB::connection($this->pcConexion)->table('MAQUINAIMAGEN')->insertGetId([
                    'Maquina' => $tnMaquina,
                    'TipoFoto' => mb_strtoupper(trim((string)($laFoto['TipoFoto'] ?? 'GENERAL'))),
                    'RutaImagen' => $tcRuta,
                    'Orden' => isset($laFoto['Orden']) && (int)$laFoto['Orden'] > 0 ? (int)$laFoto['Orden'] : ($tnOrdenBase + $tnPos),
                    'Observacion' => isset($laFoto['Observacion']) ? (string)$laFoto['Observacion'] : null,
                    'Estado' => $tnEstadoActivo,
                    'Usr' => $tnUsuario,
                    'UsrFecha' => $tdAhora->toDateString(),
                    'UsrHora' => $tdAhora->format('H:i:s'),
                ]);
                $laInsertadas[] = $tnId;
                $tnPos++;
            }

            foreach ($laArchivos as $toArchivo) {
                $tcNombre = 'maquina_' . $tnMaquina . '_' . now()->format('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $toArchivo->getClientOriginalExtension();
                $tcRuta = $toArchivo->storeAs('maquina/imagenes', $tcNombre, 'public');

                $tnId = (int)DB::connection($this->pcConexion)->table('MAQUINAIMAGEN')->insertGetId([
                    'Maquina' => $tnMaquina,
                    'TipoFoto' => 'GENERAL',
                    'RutaImagen' => $tcRuta,
                    'Orden' => $tnOrdenBase + $tnPos,
                    'Observacion' => null,
                    'Estado' => $tnEstadoActivo,
                    'Usr' => $tnUsuario,
                    'UsrFecha' => $tdAhora->toDateString(),
                    'UsrHora' => $tdAhora->format('H:i:s'),
                ]);
                $laInsertadas[] = $tnId;
                $tnPos++;
            }

            $laFotosNuevas = DB::connection($this->pcConexion)
                ->table('MAQUINAIMAGEN')
                ->whereIn('MaquinaImagen', $laInsertadas)
                ->get()
                ->map(fn(object $toFila): array => $this->normalizarFoto($toFila))
                ->all();

            $tnTotal = (int)DB::connection($this->pcConexion)
                ->table('MAQUINAIMAGEN')
                ->where('Maquina', $tnMaquina)
                ->where('Estado', $tnEstadoActivo)
                ->count();

            $this->toAuditoria->registrar(
                'MAQUINAIMAGEN',
                $tnMaquina,
                'SUBIR_LOTE',
                null,
                ['ImagenesInsertadas' => $laInsertadas, 'TotalFotosActivas' => $tnTotal],
                $tnUsuario,
                $tcMotivo
            );

            return [
                'Estado' => 'OK',
                'Datos' => [
                    'Maquina' => $tnMaquina,
                    'ImagenesInsertadas' => $laFotosNuevas,
                    'TotalFotosActivas' => $tnTotal,
                    'CumpleMinimo' => $tnTotal >= 3,
                    'MinimoRequerido' => 3,
                ],
            ];
        });
    }

    /**
     * @return array<string,mixed>
     */
    public function EliminarFoto(int $tnMaquina, int $tnMaquinaImagen, string $tcVersion, int $tnUsuario, ?string $tcMotivo): array
    {
        return DB::connection($this->pcConexion)->transaction(function () use ($tnMaquina, $tnMaquinaImagen, $tcVersion, $tnUsuario, $tcMotivo): array {
            $tnEstadoActivo = $this->toEstadoCatalogo->obtenerId('GENERAL', 1);
            $tnEstadoInactivo = $this->toEstadoCatalogo->obtenerId('GENERAL', 2);

            $loFoto = DB::connection($this->pcConexion)
                ->table('MAQUINAIMAGEN')
                ->where('MaquinaImagen', $tnMaquinaImagen)
                ->where('Maquina', $tnMaquina)
                ->where('Estado', $tnEstadoActivo)
                ->lockForUpdate()
                ->first();

            if (!$loFoto) {
                return ['Estado' => 'NO_ENCONTRADO'];
            }
            if (!$this->toControlVersion->coincide($tcVersion, $loFoto)) {
                return ['Estado' => 'CONFLICTO_VERSION', 'Actual' => $this->normalizarFoto($loFoto)];
            }

            $tnTotal = (int)DB::connection($this->pcConexion)
                ->table('MAQUINAIMAGEN')
                ->where('Maquina', $tnMaquina)
                ->where('Estado', $tnEstadoActivo)
                ->count();
            if ($tnTotal <= 3) {
                return ['Estado' => 'MINIMO_FOTOS', 'TotalActual' => $tnTotal];
            }

            $tdAhora = now();
            DB::connection($this->pcConexion)
                ->table('MAQUINAIMAGEN')
                ->where('MaquinaImagen', $tnMaquinaImagen)
                ->update([
                    'Estado' => $tnEstadoInactivo,
                    'Usr' => $tnUsuario,
                    'UsrFecha' => $tdAhora->toDateString(),
                    'UsrHora' => $tdAhora->format('H:i:s'),
                ]);

            $loNuevo = DB::connection($this->pcConexion)
                ->table('MAQUINAIMAGEN')
                ->where('MaquinaImagen', $tnMaquinaImagen)
                ->first();

            $this->toAuditoria->registrar(
                'MAQUINAIMAGEN',
                $tnMaquinaImagen,
                'ELIMINAR_LOGICO',
                $this->normalizarFoto($loFoto),
                $loNuevo ? $this->normalizarFoto($loNuevo) : null,
                $tnUsuario,
                $tcMotivo
            );

            return [
                'Estado' => 'OK',
                'Datos' => [
                    'MaquinaImagen' => $tnMaquinaImagen,
                    'Maquina' => $tnMaquina,
                    'TotalFotosActivas' => $tnTotal - 1,
                    'CumpleMinimo' => ($tnTotal - 1) >= 3,
                    'MinimoRequerido' => 3,
                ],
            ];
        });
    }

    private function normalizarFoto(object $toFila): array
    {
        $la = (array)$toFila;
        $tcRuta = (string)($la['RutaImagen'] ?? '');
        $la['UrlImagen'] = $tcRuta !== '' ? $this->urlImagen($tcRuta) : '';
        $la['Version'] = $this->toControlVersion->versionDesdeFila($toFila);
        return $la;
    }

    private function urlImagen(string $tcRuta): string
    {
        if (preg_match('/^https?:\\/\\//i', $tcRuta) === 1) {
            return $tcRuta;
        }

        return Storage::disk('public')->url($tcRuta);
    }

    public function EliminarLogico(int $tnMaquina, string $tcVersion, int $tnUsuario, ?string $tcMotivo): array
    {
        $tnEstadoInactivo = $this->toEstadoCatalogo->obtenerId('GENERAL', 2);

        return DB::connection($this->pcConexion)->transaction(function () use ($tnMaquina, $tcVersion, $tnUsuario, $tnEstadoInactivo, $tcMotivo): array {
            $loActual = DB::connection($this->pcConexion)->table('MAQUINA')->where('Maquina', $tnMaquina)->lockForUpdate()->first();
            if (!$loActual) {
                return ['Estado' => 'NO_ENCONTRADO'];
            }
            if (!$this->toControlVersion->coincide($tcVersion, $loActual)) {
                return ['Estado' => 'CONFLICTO_VERSION', 'Actual' => $this->normalizarFila($loActual)];
            }

            $tdAhora = now();
            DB::connection($this->pcConexion)->table('MAQUINA')->where('Maquina', $tnMaquina)->update([
                'Estado' => $tnEstadoInactivo,
                'Usr' => $tnUsuario,
                'UsrFecha' => $tdAhora->toDateString(),
                'UsrHora' => $tdAhora->format('H:i:s'),
            ]);

            $loNuevo = DB::connection($this->pcConexion)->table('MAQUINA')->where('Maquina', $tnMaquina)->first();
            $laAntes = $this->normalizarFila($loActual);
            $laDespues = $this->normalizarFila($loNuevo);
            $this->toAuditoria->registrar('MAQUINA', $tnMaquina, 'ELIMINAR_LOGICO', $laAntes, $laDespues, $tnUsuario, $tcMotivo);

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

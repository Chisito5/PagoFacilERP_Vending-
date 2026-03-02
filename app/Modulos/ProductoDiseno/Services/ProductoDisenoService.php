<?php

namespace App\Modulos\ProductoDiseno\Services;

use App\Soporte\AuditoriaService;
use App\Soporte\ControlVersionService;
use App\Support\EstadoCatalogo;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class ProductoDisenoService
{
    private string $pcConexion = 'mysqlNegocio';

    public function __construct(
        private EstadoCatalogo $toEstadoCatalogo,
        private ControlVersionService $toControlVersion,
        private AuditoriaService $toAuditoria
    ) {
    }

    /**
     * SYSCOOP
     * category: Service
     * package: App\Modulos\ProductoDiseno\Services
     * author: Vladimir Meriles Velasquez
     * fecha: 01-03-2026
     * param: int $tnProducto
     * return: array<string,mixed>
     */
    public function ObtenerDiseno(int $tnProducto): array
    {
        $loProducto = DB::connection($this->pcConexion)->table('PRODUCTO')->where('Producto', $tnProducto)->first();
        if (!$loProducto) {
            return ['Estado' => 'NO_ENCONTRADO'];
        }

        $tnEstadoActivo = $this->obtenerEstado('PRODUCTODISENO', 1, $this->obtenerEstado('GENERAL', 1, 1));
        $loDiseno = DB::connection($this->pcConexion)
            ->table('PRODUCTODISENO')
            ->where('Producto', $tnProducto)
            ->where('Estado', $tnEstadoActivo)
            ->first();

        if (!$loDiseno) {
            return [
                'Estado' => 'OK',
                'Datos' => [
                    'Lienzo' => ['Ancho' => 900, 'Alto' => 500],
                    'Capas' => [],
                    'Version' => null,
                    'Estado' => 'SIN_DISENO',
                ],
            ];
        }

        $laCapas = DB::connection($this->pcConexion)
            ->table('PRODUCTODISENOCAPA')
            ->select([
                'CapaIdExterno as Id',
                'Tipo',
                'Orden',
                'X',
                'Y',
                'Ancho',
                'Alto',
                'Opacidad',
                'Rotacion',
                'Texto',
                'Color',
                'Fuente',
                'Recurso',
            ])
            ->where('ProductoDiseno', (int)$loDiseno->ProductoDiseno)
            ->where('Estado', $tnEstadoActivo)
            ->orderBy('Orden')
            ->get()
            ->map(static fn($toFila): array => (array)$toFila)
            ->all();

        return [
            'Estado' => 'OK',
            'Datos' => [
                'Lienzo' => [
                    'Ancho' => (int)$loDiseno->LienzoAncho,
                    'Alto' => (int)$loDiseno->LienzoAlto,
                ],
                'Capas' => $laCapas,
                'Version' => $this->toControlVersion->versionDesdeFila($loDiseno),
                'Estado' => (int)$loDiseno->Estado,
            ],
        ];
    }

    /**
     * SYSCOOP
     * category: Service
     * package: App\Modulos\ProductoDiseno\Services
     * author: Vladimir Meriles Velasquez
     * fecha: 01-03-2026
     * param: int $tnProducto, array<string,mixed> $taDatos, int $tnUsuarioSesion
     * return: array<string,mixed>
     */
    public function CrearDiseno(int $tnProducto, array $taDatos, int $tnUsuarioSesion): array
    {
        return DB::connection($this->pcConexion)->transaction(function () use ($tnProducto, $taDatos, $tnUsuarioSesion): array {
            $loProducto = DB::connection($this->pcConexion)->table('PRODUCTO')->where('Producto', $tnProducto)->lockForUpdate()->first();
            if (!$loProducto) {
                return ['Estado' => 'NO_ENCONTRADO'];
            }

            $tnEstadoActivo = $this->obtenerEstado('PRODUCTODISENO', 1, $this->obtenerEstado('GENERAL', 1, 1));
            $loExiste = DB::connection($this->pcConexion)
                ->table('PRODUCTODISENO')
                ->where('Producto', $tnProducto)
                ->where('Estado', $tnEstadoActivo)
                ->lockForUpdate()
                ->first();
            if ($loExiste) {
                return ['Estado' => 'YA_EXISTE', 'Version' => $this->toControlVersion->versionDesdeFila($loExiste)];
            }

            $tdAhora = now();
            $laLienzo = $this->normalizarLienzo($taDatos['Lienzo'] ?? []);
            $laCapas = $this->normalizarCapas($taDatos['Capas'] ?? []);
            $tcMotivo = isset($taDatos['Motivo']) ? (string)$taDatos['Motivo'] : null;

            $tnDiseno = (int)DB::connection($this->pcConexion)->table('PRODUCTODISENO')->insertGetId([
                'Producto' => $tnProducto,
                'LienzoAncho' => $laLienzo['Ancho'],
                'LienzoAlto' => $laLienzo['Alto'],
                'JsonDiseno' => json_encode(['Lienzo' => $laLienzo, 'Capas' => $laCapas], JSON_UNESCAPED_UNICODE),
                'Estado' => $tnEstadoActivo,
                'Usr' => $tnUsuarioSesion,
                'UsrFecha' => $tdAhora->toDateString(),
                'UsrHora' => $tdAhora->format('H:i:s'),
            ]);

            $this->insertarCapas($tnDiseno, $laCapas, $tnEstadoActivo, $tnUsuarioSesion, $tdAhora);

            $loNuevo = DB::connection($this->pcConexion)->table('PRODUCTODISENO')->where('ProductoDiseno', $tnDiseno)->first();
            $this->toAuditoria->registrar('PRODUCTODISENO', $tnDiseno, 'CREAR', null, (array)$loNuevo, $tnUsuarioSesion, $tcMotivo);

            return [
                'Estado' => 'OK',
                'Datos' => [
                    'ProductoDiseno' => $tnDiseno,
                    'Producto' => $tnProducto,
                    'Lienzo' => $laLienzo,
                    'Capas' => $laCapas,
                    'Version' => $this->toControlVersion->versionDesdeFila($loNuevo),
                ],
            ];
        });
    }

    /**
     * SYSCOOP
     * category: Service
     * package: App\Modulos\ProductoDiseno\Services
     * author: Vladimir Meriles Velasquez
     * fecha: 01-03-2026
     * param: int $tnProducto, array<string,mixed> $taDatos, int $tnUsuarioSesion
     * return: array<string,mixed>
     */
    public function ActualizarDiseno(int $tnProducto, array $taDatos, int $tnUsuarioSesion): array
    {
        return DB::connection($this->pcConexion)->transaction(function () use ($tnProducto, $taDatos, $tnUsuarioSesion): array {
            $tnEstadoActivo = $this->obtenerEstado('PRODUCTODISENO', 1, $this->obtenerEstado('GENERAL', 1, 1));
            $tnEstadoInactivo = $this->obtenerEstado('PRODUCTODISENO', 2, $this->obtenerEstado('GENERAL', 2, 2));
            $loActual = DB::connection($this->pcConexion)
                ->table('PRODUCTODISENO')
                ->where('Producto', $tnProducto)
                ->where('Estado', $tnEstadoActivo)
                ->lockForUpdate()
                ->first();

            if (!$loActual) {
                return ['Estado' => 'NO_ENCONTRADO'];
            }

            $tcVersion = (string)($taDatos['Version'] ?? '');
            if (!$this->toControlVersion->coincide($tcVersion, $loActual)) {
                return [
                    'Estado' => 'CONFLICTO_VERSION',
                    'Actual' => ['Version' => $this->toControlVersion->versionDesdeFila($loActual)],
                ];
            }

            $tdAhora = now();
            $laLienzo = $this->normalizarLienzo($taDatos['Lienzo'] ?? []);
            $laCapas = $this->normalizarCapas($taDatos['Capas'] ?? []);
            $tcMotivo = isset($taDatos['Motivo']) ? (string)$taDatos['Motivo'] : null;

            DB::connection($this->pcConexion)
                ->table('PRODUCTODISENO')
                ->where('ProductoDiseno', (int)$loActual->ProductoDiseno)
                ->update([
                    'LienzoAncho' => $laLienzo['Ancho'],
                    'LienzoAlto' => $laLienzo['Alto'],
                    'JsonDiseno' => json_encode(['Lienzo' => $laLienzo, 'Capas' => $laCapas], JSON_UNESCAPED_UNICODE),
                    'Usr' => $tnUsuarioSesion,
                    'UsrFecha' => $tdAhora->toDateString(),
                    'UsrHora' => $tdAhora->format('H:i:s'),
                ]);

            DB::connection($this->pcConexion)
                ->table('PRODUCTODISENOCAPA')
                ->where('ProductoDiseno', (int)$loActual->ProductoDiseno)
                ->where('Estado', $tnEstadoActivo)
                ->update([
                    'Estado' => $tnEstadoInactivo,
                    'Usr' => $tnUsuarioSesion,
                    'UsrFecha' => $tdAhora->toDateString(),
                    'UsrHora' => $tdAhora->format('H:i:s'),
                ]);

            $this->insertarCapas((int)$loActual->ProductoDiseno, $laCapas, $tnEstadoActivo, $tnUsuarioSesion, $tdAhora);

            $loNuevo = DB::connection($this->pcConexion)->table('PRODUCTODISENO')->where('ProductoDiseno', (int)$loActual->ProductoDiseno)->first();
            $this->toAuditoria->registrar('PRODUCTODISENO', (int)$loActual->ProductoDiseno, 'ACTUALIZAR', (array)$loActual, (array)$loNuevo, $tnUsuarioSesion, $tcMotivo);

            return [
                'Estado' => 'OK',
                'Datos' => [
                    'ProductoDiseno' => (int)$loActual->ProductoDiseno,
                    'Producto' => $tnProducto,
                    'Lienzo' => $laLienzo,
                    'Capas' => $laCapas,
                    'Version' => $this->toControlVersion->versionDesdeFila($loNuevo),
                ],
            ];
        });
    }

    /**
     * SYSCOOP
     * category: Service
     * package: App\Modulos\ProductoDiseno\Services
     * author: Vladimir Meriles Velasquez
     * fecha: 01-03-2026
     * param: int $tnProducto, array<string,mixed> $taDatos
     * return: array<string,mixed>
     */
    public function RenderDiseno(int $tnProducto, array $taDatos): array
    {
        $laLienzo = $this->normalizarLienzo($taDatos['Lienzo'] ?? []);
        $laCapas = $this->normalizarCapas($taDatos['Capas'] ?? []);

        if (count($laCapas) === 0) {
            $laDiseno = $this->ObtenerDiseno($tnProducto);
            if ($laDiseno['Estado'] === 'OK') {
                $laLienzo = $laDiseno['Datos']['Lienzo'] ?? $laLienzo;
                $laCapas = $laDiseno['Datos']['Capas'] ?? [];
            }
        }

        $tcSvg = $this->construirSvg($laLienzo, $laCapas);
        return [
            'Estado' => 'OK',
            'Datos' => [
                'Svg' => $tcSvg,
                'Mime' => 'image/svg+xml',
                'Resumen' => [
                    'Ancho' => $laLienzo['Ancho'],
                    'Alto' => $laLienzo['Alto'],
                    'TotalCapas' => count($laCapas),
                ],
            ],
        ];
    }

    /**
     * SYSCOOP
     * category: Service
     * package: App\Modulos\ProductoDiseno\Services
     * author: Vladimir Meriles Velasquez
     * fecha: 01-03-2026
     * param: int $tnProducto
     * return: array<string,mixed>
     */
    public function Galeria(int $tnProducto): array
    {
        $tnEstadoActivo = $this->obtenerEstado('GENERAL', 1, 1);
        $laImagenes = DB::connection($this->pcConexion)
            ->table('PRODUCTOIMAGEN as pi')
            ->leftJoin('TIPOIMAGEN as ti', 'ti.TipoImagen', '=', 'pi.TipoImagen')
            ->select([
                'pi.ProductoImagen',
                'pi.Producto',
                'pi.TipoImagen',
                'ti.NombreTipoImagen',
                'pi.RutaImagen',
                'pi.Orden',
                'pi.Estado',
                'pi.UsrFecha',
                'pi.UsrHora',
            ])
            ->where('pi.Producto', $tnProducto)
            ->where('pi.Estado', $tnEstadoActivo)
            ->orderBy('pi.Orden')
            ->orderBy('pi.ProductoImagen')
            ->get()
            ->map(function ($toFila): array {
                return [
                    'ProductoImagen' => (int)$toFila->ProductoImagen,
                    'Producto' => (int)$toFila->Producto,
                    'TipoImagen' => (string)($toFila->NombreTipoImagen ?? ''),
                    'TipoImagenId' => (int)$toFila->TipoImagen,
                    'RutaImagen' => (string)$toFila->RutaImagen,
                    'Url' => (string)$toFila->RutaImagen,
                    'Orden' => (int)$toFila->Orden,
                    'Estado' => (int)$toFila->Estado,
                    'Version' => $this->toControlVersion->versionDesdeFila($toFila),
                ];
            })
            ->all();

        return ['Estado' => 'OK', 'Datos' => $laImagenes];
    }

    /**
     * SYSCOOP
     * category: Service
     * package: App\Modulos\ProductoDiseno\Services
     * author: Vladimir Meriles Velasquez
     * fecha: 01-03-2026
     * param: int $tnProducto, array<int,array<string,mixed>> $laImagenes, array<int,UploadedFile> $laArchivos, int $tnUsuarioSesion, ?string $tcMotivo
     * return: array<string,mixed>
     */
    public function SubirImagenLote(int $tnProducto, array $laImagenes, array $laArchivos, int $tnUsuarioSesion, ?string $tcMotivo): array
    {
        return DB::connection($this->pcConexion)->transaction(function () use ($tnProducto, $laImagenes, $laArchivos, $tnUsuarioSesion, $tcMotivo): array {
            $loProducto = DB::connection($this->pcConexion)->table('PRODUCTO')->where('Producto', $tnProducto)->lockForUpdate()->first();
            if (!$loProducto) {
                return ['Estado' => 'NO_ENCONTRADO'];
            }

            $tnEstadoActivo = $this->obtenerEstado('GENERAL', 1, 1);
            $tdAhora = now();

            $tnOrdenBase = (int)(DB::connection($this->pcConexion)
                ->table('PRODUCTOIMAGEN')
                ->where('Producto', $tnProducto)
                ->max('Orden') ?? 0);

            $laInsertadas = [];
            $tnPos = 1;
            foreach ($laImagenes as $laImagen) {
                $tcUrl = trim((string)($laImagen['Url'] ?? ''));
                if ($tcUrl === '') {
                    continue;
                }
                $tcTipo = trim((string)($laImagen['TipoImagen'] ?? 'DETALLE'));
                $tnTipoImagen = $this->obtenerTipoImagenPorNombre($tcTipo);
                $tnOrden = isset($laImagen['Orden']) && (int)$laImagen['Orden'] > 0 ? (int)$laImagen['Orden'] : ($tnOrdenBase + $tnPos);

                $tnId = (int)DB::connection($this->pcConexion)->table('PRODUCTOIMAGEN')->insertGetId([
                    'Producto' => $tnProducto,
                    'TipoImagen' => $tnTipoImagen,
                    'RutaImagen' => $tcUrl,
                    'Orden' => $tnOrden,
                    'Estado' => $tnEstadoActivo,
                    'Usr' => $tnUsuarioSesion,
                    'UsrFecha' => $tdAhora->toDateString(),
                    'UsrHora' => $tdAhora->format('H:i:s'),
                ]);
                $laInsertadas[] = $tnId;
                $tnPos++;
            }

            foreach ($laArchivos as $toArchivo) {
                $tcTipo = 'DETALLE';
                $tnTipoImagen = $this->obtenerTipoImagenPorNombre($tcTipo);
                $tcNombre = Str::uuid()->toString() . '_' . preg_replace('/[^A-Za-z0-9_\\.-]/', '_', $toArchivo->getClientOriginalName());
                $tcRuta = $toArchivo->storeAs('productos/' . $tnProducto, $tcNombre, 'public');
                $tcUrl = Storage::disk('public')->url($tcRuta);
                $tnOrden = $tnOrdenBase + $tnPos;

                $tnId = (int)DB::connection($this->pcConexion)->table('PRODUCTOIMAGEN')->insertGetId([
                    'Producto' => $tnProducto,
                    'TipoImagen' => $tnTipoImagen,
                    'RutaImagen' => $tcUrl,
                    'Orden' => $tnOrden,
                    'Estado' => $tnEstadoActivo,
                    'Usr' => $tnUsuarioSesion,
                    'UsrFecha' => $tdAhora->toDateString(),
                    'UsrHora' => $tdAhora->format('H:i:s'),
                ]);
                $laInsertadas[] = $tnId;
                $tnPos++;
            }

            $this->toAuditoria->registrar(
                'PRODUCTOIMAGEN',
                $tnProducto,
                'SUBIR_LOTE_IMAGENES',
                null,
                ['ImagenesInsertadas' => $laInsertadas],
                $tnUsuarioSesion,
                $tcMotivo
            );

            return ['Estado' => 'OK', 'Datos' => ['Producto' => $tnProducto, 'ImagenesInsertadas' => $laInsertadas]];
        });
    }

    /**
     * SYSCOOP
     * category: Service
     * package: App\Modulos\ProductoDiseno\Services
     * author: Vladimir Meriles Velasquez
     * fecha: 01-03-2026
     * param: int $tnProducto, array<int,array<string,mixed>> $laOrdenes, string $tcVersion, int $tnUsuarioSesion, ?string $tcMotivo
     * return: array<string,mixed>
     */
    public function ReordenarGaleria(int $tnProducto, array $laOrdenes, string $tcVersion, int $tnUsuarioSesion, ?string $tcMotivo): array
    {
        return DB::connection($this->pcConexion)->transaction(function () use ($tnProducto, $laOrdenes, $tcVersion, $tnUsuarioSesion, $tcMotivo): array {
            $loProducto = DB::connection($this->pcConexion)->table('PRODUCTO')->where('Producto', $tnProducto)->lockForUpdate()->first();
            if (!$loProducto) {
                return ['Estado' => 'NO_ENCONTRADO'];
            }
            if (!$this->toControlVersion->coincide($tcVersion, $loProducto)) {
                return ['Estado' => 'CONFLICTO_VERSION', 'Actual' => ['Version' => $this->toControlVersion->versionDesdeFila($loProducto)]];
            }

            $tdAhora = now();
            $laAfectadas = [];
            foreach ($laOrdenes as $laOrden) {
                $tnProductoImagen = (int)($laOrden['ProductoImagen'] ?? 0);
                $tnOrden = (int)($laOrden['Orden'] ?? 0);
                if ($tnProductoImagen <= 0 || $tnOrden <= 0) {
                    continue;
                }

                $tnRows = DB::connection($this->pcConexion)
                    ->table('PRODUCTOIMAGEN')
                    ->where('ProductoImagen', $tnProductoImagen)
                    ->where('Producto', $tnProducto)
                    ->update([
                        'Orden' => $tnOrden,
                        'Usr' => $tnUsuarioSesion,
                        'UsrFecha' => $tdAhora->toDateString(),
                        'UsrHora' => $tdAhora->format('H:i:s'),
                    ]);
                if ($tnRows > 0) {
                    $laAfectadas[] = $tnProductoImagen;
                }
            }

            $this->toAuditoria->registrar('PRODUCTOIMAGEN', $tnProducto, 'REORDENAR_GALERIA', null, ['ImagenesAfectadas' => $laAfectadas], $tnUsuarioSesion, $tcMotivo);

            return ['Estado' => 'OK', 'Datos' => ['Producto' => $tnProducto, 'ImagenesAfectadas' => $laAfectadas]];
        });
    }

    /**
     * @param array<string,mixed> $taLienzo
     * @return array{Ancho:int,Alto:int}
     */
    private function normalizarLienzo(array $taLienzo): array
    {
        return [
            'Ancho' => max(100, (int)($taLienzo['Ancho'] ?? 900)),
            'Alto' => max(100, (int)($taLienzo['Alto'] ?? 500)),
        ];
    }

    /**
     * @param mixed $tmCapas
     * @return array<int,array<string,mixed>>
     */
    private function normalizarCapas(mixed $tmCapas): array
    {
        if (!is_array($tmCapas)) {
            return [];
        }

        $laCapas = [];
        $tnOrden = 1;
        foreach ($tmCapas as $taCapa) {
            if (!is_array($taCapa)) {
                continue;
            }

            $laCapas[] = [
                'Id' => (string)($taCapa['Id'] ?? ('CAPA-' . $tnOrden)),
                'Tipo' => (string)($taCapa['Tipo'] ?? 'DETALLE'),
                'Orden' => max(1, (int)($taCapa['Orden'] ?? $tnOrden)),
                'X' => (float)($taCapa['X'] ?? 0),
                'Y' => (float)($taCapa['Y'] ?? 0),
                'Ancho' => (float)($taCapa['Ancho'] ?? 0),
                'Alto' => (float)($taCapa['Alto'] ?? 0),
                'Opacidad' => max(0, min(1, (float)($taCapa['Opacidad'] ?? 1))),
                'Rotacion' => (float)($taCapa['Rotacion'] ?? 0),
                'Texto' => isset($taCapa['Texto']) ? (string)$taCapa['Texto'] : null,
                'Color' => isset($taCapa['Color']) ? (string)$taCapa['Color'] : null,
                'Fuente' => isset($taCapa['Fuente']) ? (string)$taCapa['Fuente'] : null,
                'Recurso' => isset($taCapa['Recurso']) ? (string)$taCapa['Recurso'] : null,
            ];
            $tnOrden++;
        }

        usort($laCapas, static fn(array $a, array $b): int => ((int)$a['Orden']) <=> ((int)$b['Orden']));
        return $laCapas;
    }

    /**
     * @param array<int,array<string,mixed>> $laCapas
     */
    private function insertarCapas(int $tnDiseno, array $laCapas, int $tnEstado, int $tnUsuarioSesion, \Illuminate\Support\Carbon $tdAhora): void
    {
        foreach ($laCapas as $laCapa) {
            DB::connection($this->pcConexion)->table('PRODUCTODISENOCAPA')->insert([
                'ProductoDiseno' => $tnDiseno,
                'CapaIdExterno' => (string)$laCapa['Id'],
                'Tipo' => (string)$laCapa['Tipo'],
                'Orden' => (int)$laCapa['Orden'],
                'X' => (float)$laCapa['X'],
                'Y' => (float)$laCapa['Y'],
                'Ancho' => (float)$laCapa['Ancho'],
                'Alto' => (float)$laCapa['Alto'],
                'Opacidad' => (float)$laCapa['Opacidad'],
                'Rotacion' => (float)$laCapa['Rotacion'],
                'Texto' => $laCapa['Texto'],
                'Color' => $laCapa['Color'],
                'Fuente' => $laCapa['Fuente'],
                'Recurso' => $laCapa['Recurso'],
                'Estado' => $tnEstado,
                'Usr' => $tnUsuarioSesion,
                'UsrFecha' => $tdAhora->toDateString(),
                'UsrHora' => $tdAhora->format('H:i:s'),
            ]);
        }
    }

    /**
     * @param array{Ancho:int,Alto:int} $laLienzo
     * @param array<int,array<string,mixed>> $laCapas
     */
    private function construirSvg(array $laLienzo, array $laCapas): string
    {
        $tnAncho = $laLienzo['Ancho'];
        $tnAlto = $laLienzo['Alto'];
        $laLineas = [];
        $laLineas[] = '<svg xmlns=\"http://www.w3.org/2000/svg\" width=\"' . $tnAncho . '\" height=\"' . $tnAlto . '\" viewBox=\"0 0 ' . $tnAncho . ' ' . $tnAlto . '\">';
        $laLineas[] = '<rect x=\"0\" y=\"0\" width=\"' . $tnAncho . '\" height=\"' . $tnAlto . '\" fill=\"#f8fafc\"/>';

        foreach ($laCapas as $laCapa) {
            $tcTipo = mb_strtoupper((string)$laCapa['Tipo']);
            $tnX = (float)$laCapa['X'];
            $tnY = (float)$laCapa['Y'];
            $tnW = max(1, (float)$laCapa['Ancho']);
            $tnH = max(1, (float)$laCapa['Alto']);
            $tnOpacidad = max(0, min(1, (float)$laCapa['Opacidad']));
            $tnRotacion = (float)$laCapa['Rotacion'];
            $tcTransform = $tnRotacion != 0 ? ' transform=\"rotate(' . $tnRotacion . ' ' . $tnX . ' ' . $tnY . ')\"' : '';

            if ($tcTipo === 'TEXTO') {
                $tcTexto = htmlspecialchars((string)($laCapa['Texto'] ?? ''), ENT_QUOTES, 'UTF-8');
                $tcColor = htmlspecialchars((string)($laCapa['Color'] ?? '#111827'), ENT_QUOTES, 'UTF-8');
                $tcFuente = htmlspecialchars((string)($laCapa['Fuente'] ?? 'Arial'), ENT_QUOTES, 'UTF-8');
                $laLineas[] = '<text x=\"' . $tnX . '\" y=\"' . ($tnY + max(12, $tnH / 2)) . '\" fill=\"' . $tcColor . '\" font-family=\"' . $tcFuente . '\" font-size=\"24\" opacity=\"' . $tnOpacidad . '\"' . $tcTransform . '>' . $tcTexto . '</text>';
                continue;
            }

            $tcRecurso = trim((string)($laCapa['Recurso'] ?? ''));
            if ($tcRecurso !== '') {
                $tcHref = htmlspecialchars($tcRecurso, ENT_QUOTES, 'UTF-8');
                $laLineas[] = '<image x=\"' . $tnX . '\" y=\"' . $tnY . '\" width=\"' . $tnW . '\" height=\"' . $tnH . '\" href=\"' . $tcHref . '\" opacity=\"' . $tnOpacidad . '\"' . $tcTransform . ' />';
                continue;
            }

            $tcColor = $tcTipo === 'FONDO' ? '#cbd5e1' : '#f59e0b';
            $laLineas[] = '<rect x=\"' . $tnX . '\" y=\"' . $tnY . '\" width=\"' . $tnW . '\" height=\"' . $tnH . '\" fill=\"' . $tcColor . '\" opacity=\"' . $tnOpacidad . '\"' . $tcTransform . '/>';
        }

        $laLineas[] = '</svg>';
        return implode('', $laLineas);
    }

    private function obtenerTipoImagenPorNombre(string $tcNombre): int
    {
        $tnEstadoActivo = $this->obtenerEstado('GENERAL', 1, 1);
        $tcNombre = mb_strtoupper(trim($tcNombre));
        $loTipo = DB::connection($this->pcConexion)
            ->table('TIPOIMAGEN')
            ->select('TipoImagen')
            ->whereRaw('UPPER(NombreTipoImagen) = ?', [$tcNombre])
            ->where('Estado', $tnEstadoActivo)
            ->first();

        if ($loTipo) {
            return (int)$loTipo->TipoImagen;
        }

        return (int)DB::connection($this->pcConexion)->table('TIPOIMAGEN')->insertGetId([
            'NombreTipoImagen' => $tcNombre,
            'Descripcion' => 'Tipo imagen autogenerado',
            'Estado' => $tnEstadoActivo,
            'Usr' => 0,
            'UsrFecha' => now()->toDateString(),
            'UsrHora' => now()->format('H:i:s'),
        ]);
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

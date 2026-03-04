<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;

require __DIR__ . '/../../vendor/autoload.php';

$app = require __DIR__ . '/../../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

date_default_timezone_set(config('app.timezone', 'UTC'));

$pcBaseDir = realpath(__DIR__ . '/../../') ?: getcwd();
$pcDocsDir = $pcBaseDir . DIRECTORY_SEPARATOR . 'docs' . DIRECTORY_SEPARATOR . 'diagnostico';
$pcSqlCompatDir = $pcBaseDir . DIRECTORY_SEPARATOR . 'sql' . DIRECTORY_SEPARATOR . 'compat';

if (!is_dir($pcDocsDir)) {
    mkdir($pcDocsDir, 0777, true);
}

if (!is_dir($pcSqlCompatDir)) {
    mkdir($pcSqlCompatDir, 0777, true);
}

function pcNormalizarRuta(string $pcRuta): string
{
    return str_replace('\\', '/', $pcRuta);
}

function taArchivosPhp(string $pcBase): array
{
    $taArchivos = [];
    $toIterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($pcBase));
    foreach ($toIterator as $toFile) {
        if (!$toFile->isFile()) {
            continue;
        }
        if (strtolower($toFile->getExtension()) !== 'php') {
            continue;
        }
        $taArchivos[] = $toFile->getPathname();
    }

    sort($taArchivos);
    return $taArchivos;
}

function tcOperacionDesdeContexto(string $pcContexto): string
{
    $pc = strtolower($pcContexto);
    $taWrite = ['insert', 'update', 'delete', 'upsert', 'increment', 'decrement', 'truncate', 'insertgetid'];
    foreach ($taWrite as $pcToken) {
        if (str_contains($pc, $pcToken)) {
            return 'W';
        }
    }
    return 'R';
}

function taExtraerUsosTablaDesdeArchivo(string $pcArchivo): array
{
    $pcContenido = (string) file_get_contents($pcArchivo);
    $taLineas = preg_split('/\R/u', $pcContenido) ?: [];
    $taUsos = [];

    foreach ($taLineas as $tnIndice => $pcLinea) {
        if (
            !preg_match_all('/(?:->table|DB::table)\(\s*[\'"]([A-Z0-9_]+)/', $pcLinea, $taMatches, PREG_SET_ORDER)
            && !preg_match_all('/\$table\s*=\s*[\'"]([A-Z0-9_]+)/', $pcLinea, $taMatches, PREG_SET_ORDER)
        ) {
            continue;
        }

        $pcContexto = implode(' ', array_slice($taLineas, $tnIndice, 8));
        $pcOperacion = tcOperacionDesdeContexto($pcContexto);

        foreach ($taMatches as $taMatch) {
            $pcTabla = strtoupper(trim((string)($taMatch[1] ?? '')));
            if ($pcTabla === '') {
                continue;
            }
            $taUsos[] = [
                'Tabla' => $pcTabla,
                'Linea' => $tnIndice + 1,
                'Operacion' => $pcOperacion,
            ];
        }
    }

    return $taUsos;
}

function pcModuloDesdeRuta(string $pcRuta): string
{
    $pc = pcNormalizarRuta($pcRuta);
    if (preg_match('#/app/Modulos/([^/]+)/#', $pc, $taMatch)) {
        return strtoupper((string)$taMatch[1]);
    }
    if (preg_match('#/app/Soporte/#', $pc)) {
        return 'SOPORTE';
    }
    if (preg_match('#/app/Console/#', $pc)) {
        return 'CONSOLE';
    }
    if (preg_match('#/app/Providers/#', $pc)) {
        return 'PROVIDERS';
    }
    return 'APP';
}

function taEscribirCsv(string $pcArchivo, array $taCabecera, array $taFilas): void
{
    $to = fopen($pcArchivo, 'wb');
    if ($to === false) {
        throw new RuntimeException('No se pudo abrir CSV: ' . $pcArchivo);
    }
    fputcsv($to, $taCabecera);
    foreach ($taFilas as $taFila) {
        $taOut = [];
        foreach ($taCabecera as $pcCol) {
            $taOut[] = $taFila[$pcCol] ?? '';
        }
        fputcsv($to, $taOut);
    }
    fclose($to);
}

function tcClasificacionPorOperacion(int $tnR, int $tnW): string
{
    if ($tnR > 0 && $tnW > 0) {
        return 'RW';
    }
    if ($tnW > 0) {
        return 'W';
    }
    return 'R';
}

$taArchivosApp = taArchivosPhp($pcBaseDir . DIRECTORY_SEPARATOR . 'app');
$taUsoTabla = [];
$taTablasPorArchivo = [];

foreach ($taArchivosApp as $pcArchivo) {
    $taUsosArchivo = taExtraerUsosTablaDesdeArchivo($pcArchivo);
    if (count($taUsosArchivo) === 0) {
        continue;
    }

    $pcModulo = pcModuloDesdeRuta($pcArchivo);
    $taTablasPorArchivo[$pcArchivo] = $taUsosArchivo;
    foreach ($taUsosArchivo as $taUso) {
        $pcTabla = $taUso['Tabla'];
        if (!isset($taUsoTabla[$pcTabla])) {
            $taUsoTabla[$pcTabla] = [
                'ReferenciasCodigo' => 0,
                'Lecturas' => 0,
                'Escrituras' => 0,
                'Modulos' => [],
                'Archivos' => [],
            ];
        }

        $taUsoTabla[$pcTabla]['ReferenciasCodigo']++;
        if ($taUso['Operacion'] === 'W') {
            $taUsoTabla[$pcTabla]['Escrituras']++;
        } else {
            $taUsoTabla[$pcTabla]['Lecturas']++;
        }
        $taUsoTabla[$pcTabla]['Modulos'][$pcModulo] = true;
        $taUsoTabla[$pcTabla]['Archivos'][pcNormalizarRuta(str_replace($pcBaseDir . DIRECTORY_SEPARATOR, '', $pcArchivo))] = true;
    }
}

ksort($taUsoTabla);

$toConn = DB::connection('mysqlNegocio');
$pcDb = (string)$toConn->getDatabaseName();

$taDbTablesRaw = $toConn->select(
    'SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? ORDER BY TABLE_NAME',
    [$pcDb]
);

$taDbTables = [];
$taDbRows = [];
foreach ($taDbTablesRaw as $toRow) {
    $pcTabla = strtoupper((string)$toRow->TABLE_NAME);
    $taDbTables[] = $pcTabla;
    try {
        $taDbRows[$pcTabla] = (int)$toConn->table($pcTabla)->count();
    } catch (Throwable $e) {
        $taDbRows[$pcTabla] = -1;
    }
}

$taCritical = [
    'RESERVA', 'RESERVADETALLE', 'VENTA', 'VENTAREVERSA',
    'REPOSICION', 'REPOSICIONDETALLE', 'EXISTENCIACELDA', 'LOTE',
    'MOVIMIENTOINVENTARIO', 'PLANOGRAMA', 'PLANOGRAMACELDA',
    'MAQUINA', 'CELDA', 'PRODUCTO', 'PRODUCTOEMPRESA',
    'USUARIO', 'USUARIOROL', 'USUARIOMAQUINA', 'SESIONAPI', 'IDEMPOTENCIA'
];
$taCriticalSet = array_fill_keys($taCritical, true);

$taCandidatePatterns = [
    '/^TIPO[A-Z0-9_]*$/',
    '/^ANUNCIO/',
    '/^PRODUCTODISENO/',
    '/^PRODUCTOIMAGEN$/',
    '/^MAQUINAESTADOOPERATIVO/',
    '/^ALERTA$/',
    '/^REGLAALERTA$/',
    '/^PRODUCTOFAMILIA$/',
    '/^PRODUCTOGRUPO$/',
    '/^PRODUCTOSUBGRUPO$/'
];

$taLegacyNoUsadas = [];
$taCandidatas = [];
$taClasificacion = [];

foreach ($taDbTables as $pcTabla) {
    $tnRows = $taDbRows[$pcTabla] ?? 0;
    $tnRef = (int)($taUsoTabla[$pcTabla]['ReferenciasCodigo'] ?? 0);

    if (isset($taCriticalSet[$pcTabla])) {
        $taClasificacion[$pcTabla] = 'CRITICA';
        continue;
    }

    $lbCandidate = false;
    foreach ($taCandidatePatterns as $pcPattern) {
        if (preg_match($pcPattern, $pcTabla) === 1) {
            $lbCandidate = true;
            break;
        }
    }

    if ($tnRef === 0 && $tnRows === 0) {
        $taClasificacion[$pcTabla] = 'LEGACY_NO_USADA';
        $taLegacyNoUsadas[] = $pcTabla;
        continue;
    }

    if ($lbCandidate) {
        $taClasificacion[$pcTabla] = 'CANDIDATA_UNIFICAR';
        $taCandidatas[] = $pcTabla;
        continue;
    }

    $taClasificacion[$pcTabla] = $tnRef > 0 ? 'ACTIVA' : 'SIN_REFERENCIA';
}

sort($taLegacyNoUsadas);
sort($taCandidatas);

$toRoutes = app('router')->getRoutes();
$taMatrizApiTabla = [];
$taPrefijosApi = [];
$taServiciosPorModulo = [];
$tnRutasApi = 0;

foreach ($toRoutes as $toRoute) {
    $pcUri = (string)$toRoute->uri();
    if (!str_starts_with($pcUri, 'api/')) {
        continue;
    }
    $tnRutasApi++;

    $taPartes = explode('/', $pcUri);
    $pcPrefijo = $taPartes[1] ?? 'api';
    $taPrefijosApi[$pcPrefijo] = (int)($taPrefijosApi[$pcPrefijo] ?? 0) + 1;

    $pcAction = (string)$toRoute->getActionName();
    if (str_contains($pcAction, 'Closure')) {
        continue;
    }

    $pcControlador = $pcAction;
    $pcMetodoCtrl = '';
    if (str_contains($pcAction, '@')) {
        [$pcControlador, $pcMetodoCtrl] = explode('@', $pcAction, 2);
    }

    $pcModuloCase = 'Global';
    $pcModulo = 'GLOBAL';
    if (preg_match('/App\\\\Modulos\\\\([^\\\\]+)\\\\Controllers\\\\/', $pcControlador, $taMatchModulo) === 1) {
        $pcModuloCase = (string)$taMatchModulo[1];
        $pcModulo = strtoupper($pcModuloCase);
    }

    if (!isset($taServiciosPorModulo[$pcModulo])) {
        $taServiciosPorModulo[$pcModulo] = [];
        $pcDirServicios = $pcBaseDir . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Modulos' . DIRECTORY_SEPARATOR . $pcModuloCase . DIRECTORY_SEPARATOR . 'Services';
        if (is_dir($pcDirServicios)) {
            $taFiles = glob($pcDirServicios . DIRECTORY_SEPARATOR . '*Service.php') ?: [];
            foreach ($taFiles as $pcServiceFile) {
                $taServiciosPorModulo[$pcModulo][] = $pcServiceFile;
            }
        }
    }

    $taMetodos = array_values(array_diff($toRoute->methods(), ['HEAD']));
    sort($taMetodos);
    $pcMetodoHttp = implode('|', $taMetodos);
    $taServicios = $taServiciosPorModulo[$pcModulo] ?? [];

    if (count($taServicios) === 0) {
        $taMatrizApiTabla[] = [
            'Metodo' => $pcMetodoHttp,
            'Ruta' => '/' . $pcUri,
            'Controlador' => $pcControlador,
            'MetodoControlador' => $pcMetodoCtrl,
            'Modulo' => $pcModulo,
            'Servicio' => '',
            'Tabla' => '',
            'OperacionTabla' => '',
            'Relacion' => 'DIRECTA_SIN_SERVICIO',
        ];
        continue;
    }

    foreach ($taServicios as $pcServiceFile) {
        $pcServicio = basename($pcServiceFile, '.php');
        $taUsosSrv = taExtraerUsosTablaDesdeArchivo($pcServiceFile);
        $taAgg = [];
        foreach ($taUsosSrv as $taUsoSrv) {
            $pcTabla = $taUsoSrv['Tabla'];
            if (!isset($taAgg[$pcTabla])) {
                $taAgg[$pcTabla] = ['R' => 0, 'W' => 0];
            }
            $taAgg[$pcTabla][$taUsoSrv['Operacion']]++;
        }

        if (count($taAgg) === 0) {
            $taMatrizApiTabla[] = [
                'Metodo' => $pcMetodoHttp,
                'Ruta' => '/' . $pcUri,
                'Controlador' => $pcControlador,
                'MetodoControlador' => $pcMetodoCtrl,
                'Modulo' => $pcModulo,
                'Servicio' => $pcServicio,
                'Tabla' => '',
                'OperacionTabla' => '',
                'Relacion' => 'HEURISTICO_MODULO',
            ];
            continue;
        }

        ksort($taAgg);
        foreach ($taAgg as $pcTabla => $taOp) {
            $taMatrizApiTabla[] = [
                'Metodo' => $pcMetodoHttp,
                'Ruta' => '/' . $pcUri,
                'Controlador' => $pcControlador,
                'MetodoControlador' => $pcMetodoCtrl,
                'Modulo' => $pcModulo,
                'Servicio' => $pcServicio,
                'Tabla' => $pcTabla,
                'OperacionTabla' => tcClasificacionPorOperacion((int)$taOp['R'], (int)$taOp['W']),
                'Relacion' => 'HEURISTICO_MODULO',
            ];
        }
    }
}

usort($taMatrizApiTabla, static function (array $a, array $b): int {
    return [$a['Ruta'], $a['Metodo'], $a['Servicio'], $a['Tabla']] <=> [$b['Ruta'], $b['Metodo'], $b['Servicio'], $b['Tabla']];
});

$taMatrizTablaUso = [];
foreach ($taDbTables as $pcTabla) {
    $tnRef = (int)($taUsoTabla[$pcTabla]['ReferenciasCodigo'] ?? 0);
    $tnR = (int)($taUsoTabla[$pcTabla]['Lecturas'] ?? 0);
    $tnW = (int)($taUsoTabla[$pcTabla]['Escrituras'] ?? 0);
    $taMod = array_keys($taUsoTabla[$pcTabla]['Modulos'] ?? []);
    sort($taMod);
    $taFiles = array_keys($taUsoTabla[$pcTabla]['Archivos'] ?? []);
    sort($taFiles);

    $taMatrizTablaUso[] = [
        'Tabla' => $pcTabla,
        'FilasBD' => (string)($taDbRows[$pcTabla] ?? 0),
        'ReferenciasCodigo' => (string)$tnRef,
        'Lecturas' => (string)$tnR,
        'Escrituras' => (string)$tnW,
        'Operacion' => tcClasificacionPorOperacion($tnR, $tnW),
        'Modulos' => implode('|', $taMod),
        'Archivos' => implode('|', $taFiles),
        'ClasificacionInicial' => $taClasificacion[$pcTabla] ?? 'SIN_CLASIFICACION',
    ];
}

taEscribirCsv(
    $pcDocsDir . DIRECTORY_SEPARATOR . 'MATRIZ_API_TABLA.csv',
    ['Metodo', 'Ruta', 'Controlador', 'MetodoControlador', 'Modulo', 'Servicio', 'Tabla', 'OperacionTabla', 'Relacion'],
    $taMatrizApiTabla
);

taEscribirCsv(
    $pcDocsDir . DIRECTORY_SEPARATOR . 'MATRIZ_TABLA_USO.csv',
    ['Tabla', 'FilasBD', 'ReferenciasCodigo', 'Lecturas', 'Escrituras', 'Operacion', 'Modulos', 'Archivos', 'ClasificacionInicial'],
    $taMatrizTablaUso
);

arsort($taPrefijosApi);
$taTopPrefijos = array_slice($taPrefijosApi, 0, 20, true);

$taUsoOrdenado = $taUsoTabla;
uasort($taUsoOrdenado, static function (array $a, array $b): int {
    return ($b['ReferenciasCodigo'] ?? 0) <=> ($a['ReferenciasCodigo'] ?? 0);
});
$taTopTablas = array_slice($taUsoOrdenado, 0, 20, true);

$pcNow = now()->format('Y-m-d H:i:s');

$pcClasificacionMd = "# CLASIFICACION DE TABLAS\n\n";
$pcClasificacionMd .= "Fecha de corte: **{$pcNow}**\n";
$pcClasificacionMd .= "Base: **{$pcDb}**\n\n";
$pcClasificacionMd .= "## Resumen\n\n";
$pcClasificacionMd .= "- Total tablas en BD: **" . count($taDbTables) . "**\n";
$pcClasificacionMd .= "- Tablas referenciadas por codigo (`app/*`): **" . count($taUsoTabla) . "**\n";
$pcClasificacionMd .= "- Tablas no referenciadas por codigo: **" . (count($taDbTables) - count($taUsoTabla)) . "**\n";
$pcClasificacionMd .= "- Criticas: **" . count($taCritical) . "**\n";
$pcClasificacionMd .= "- Candidatas a unificar: **" . count($taCandidatas) . "**\n";
$pcClasificacionMd .= "- Legacy no usadas (sin referencia + 0 filas): **" . count($taLegacyNoUsadas) . "**\n\n";

$pcClasificacionMd .= "## 1) Tablas criticas (no tocar estructural al inicio)\n\n";
foreach ($taCritical as $pcTabla) {
    $tnFilas = (int)($taDbRows[$pcTabla] ?? 0);
    $pcClasificacionMd .= "- `{$pcTabla}` (Filas: {$tnFilas})\n";
}
$pcClasificacionMd .= "\n";

$pcClasificacionMd .= "## 2) Candidatas a unificar (despues de capa de compatibilidad)\n\n";
foreach ($taCandidatas as $pcTabla) {
    $tnFilas = (int)($taDbRows[$pcTabla] ?? 0);
    $tnRef = (int)($taUsoTabla[$pcTabla]['ReferenciasCodigo'] ?? 0);
    $pcClasificacionMd .= "- `{$pcTabla}` (Filas: {$tnFilas}, Referencias: {$tnRef})\n";
}
$pcClasificacionMd .= "\n";

$pcClasificacionMd .= "## 3) Legacy no usadas (congelar primero)\n\n";
foreach ($taLegacyNoUsadas as $pcTabla) {
    $pcClasificacionMd .= "- `{$pcTabla}`\n";
}
$pcClasificacionMd .= "\n";

$pcClasificacionMd .= "## 4) Criterio aplicado\n\n";
$pcClasificacionMd .= "- `CRITICA`: lista transaccional cerrada por negocio.\n";
$pcClasificacionMd .= "- `CANDIDATA_UNIFICAR`: catalogos repetidos o dominios duplicados con potencial de consolidacion.\n";
$pcClasificacionMd .= "- `LEGACY_NO_USADA`: sin referencia en `app/*` y sin filas.\n";
$pcClasificacionMd .= "- `ACTIVA`: con referencia en codigo y fuera de lista critica.\n";
$pcClasificacionMd .= "- `SIN_REFERENCIA`: sin referencia pero con filas (requiere analisis funcional antes de tocar).\n";

file_put_contents($pcDocsDir . DIRECTORY_SEPARATOR . 'CLASIFICACION_TABLAS.md', $pcClasificacionMd);

$pcBaselineMd = "# BASELINE DE METRICAS\n\n";
$pcBaselineMd .= "Fecha de corte: **{$pcNow}**\n";
$pcBaselineMd .= "Entorno objetivo de medicion: **Staging replica**\n\n";
$pcBaselineMd .= "## Inventario base\n\n";
$pcBaselineMd .= "- Rutas API detectadas: **{$tnRutasApi}**.\n";
$pcBaselineMd .= "- Filas en matriz API-TABLA (desagregadas por tabla): **" . count($taMatrizApiTabla) . "**.\n";
$pcBaselineMd .= "- Total tablas BD: **" . count($taDbTables) . "**.\n";
$pcBaselineMd .= "- Tablas con referencia en codigo: **" . count($taUsoTabla) . "**.\n";
$pcBaselineMd .= "- Tablas sin referencia en codigo: **" . (count($taDbTables) - count($taUsoTabla)) . "**.\n\n";
$pcBaselineMd .= "## Inventario de datos y crecimiento\n\n";
$pcBaselineMd .= "- Filas por tabla: disponible en `MATRIZ_TABLA_USO.csv` (columna `FilasBD`).\n";
$pcBaselineMd .= "- Crecimiento 30 dias: **pendiente de serie historica** (requiere snapshots diarios para comparacion).\n\n";

$pcBaselineMd .= "## Top prefijos API (conteo de rutas)\n\n";
$pcBaselineMd .= "| Prefijo | Rutas |\n|---|---:|\n";
foreach ($taTopPrefijos as $pcPref => $tnCount) {
    $pcBaselineMd .= "| {$pcPref} | {$tnCount} |\n";
}
$pcBaselineMd .= "\n";

$pcBaselineMd .= "## Top tablas por referencia en codigo\n\n";
$pcBaselineMd .= "| Tabla | Referencias | Lecturas | Escrituras |\n|---|---:|---:|---:|\n";
foreach ($taTopTablas as $pcTabla => $taInfo) {
    $pcBaselineMd .= "| {$pcTabla} | " . (int)$taInfo['ReferenciasCodigo'] . " | " . (int)$taInfo['Lecturas'] . " | " . (int)$taInfo['Escrituras'] . " |\n";
}
$pcBaselineMd .= "\n";

$pcBaselineMd .= "## Metricas obligatorias antes/despues (Fase 3/Fase 4)\n\n";
$pcBaselineMd .= "1. p50/p95/p99 por endpoint critico (`/api/reserva/*`, `/api/venta*`, `/api/reposicion*`, `/api/stock/*`, `/api/tablero/*`).\n";
$pcBaselineMd .= "2. Queries SQL por request y tiempo total SQL por request.\n";
$pcBaselineMd .= "3. Deadlocks, lock wait timeout, errores 409/500.\n";
$pcBaselineMd .= "4. Tamano de tablas e indices por dominio.\n";
$pcBaselineMd .= "5. Throughput transaccional (RPS por modulo transaccional).\n\n";

$pcBaselineMd .= "## Umbrales de aceptacion\n\n";
$pcBaselineMd .= "- No degradar p95 > 10%.\n";
$pcBaselineMd .= "- No aumentar errores de negocio.\n";
$pcBaselineMd .= "- Cero violaciones de integridad referencial.\n";

file_put_contents($pcDocsDir . DIRECTORY_SEPARATOR . 'BASELINE_METRICAS.md', $pcBaselineMd);

$pcStdMd = "# ESTANDAR NOMENCLATURA SYSCOOP\n\n";
$pcStdMd .= "## Objetivo\n\n";
$pcStdMd .= "Unificar nomenclatura de objetos nuevos sin romper estructuras existentes en produccion.\n\n";
$pcStdMd .= "## Reglas obligatorias para nuevos objetos\n\n";
$pcStdMd .= "1. Tabla en mayuscula (`MAQUINA`, `PRODUCTOIMAGEN`).\n";
$pcStdMd .= "2. PK = nombre de tabla en singular (`MAQUINA.Maquina`, `ROJO.Rojo`).\n";
$pcStdMd .= "3. FK = nombre exacto de entidad referenciada (`USUARIO`, `EMPRESA`, `MAQUINA`).\n";
$pcStdMd .= "4. Campos de auditoria estandar: `Usr`, `UsrFecha`, `UsrHora`.\n";
$pcStdMd .= "5. Estado por catalogo central (`ESTADO`) y no por flags ambiguos.\n";
$pcStdMd .= "6. Endpoints mantienen contrato uniforme `{Ok, Mensaje, Datos, Errores, Meta}`.\n";
$pcStdMd .= "7. No hacer renombres destructivos directos: usar estrategia aditiva + backfill + switch.\n\n";
$pcStdMd .= "## Convencion de compatibilidad durante refactor\n\n";
$pcStdMd .= "- Fase 1/2: crear vistas/adaptadores para homogenizar naming logico.\n";
$pcStdMd .= "- Fase 3: introducir columnas/estructuras nuevas en paralelo.\n";
$pcStdMd .= "- Fase 4: deprecacion controlada con observabilidad.\n";

file_put_contents($pcDocsDir . DIRECTORY_SEPARATOR . 'ESTANDAR_NOMENCLATURA_SYSCOOP.md', $pcStdMd);

$pcMapaMd = "# MAPA EQUIVALENCIAS TABLA/CAMPO\n\n";
$pcMapaMd .= "Objetivo: documentar equivalencias entre naming actual y naming objetivo SYSCOOP para migracion gradual sin ruptura.\n\n";
$pcMapaMd .= "| Dominio | Objeto actual | Campo actual | Equivalencia objetivo | Nota |\n";
$pcMapaMd .= "|---|---|---|---|---|\n";
$pcMapaMd .= "| Producto | `PRODUCTO` | `CodigoSku` | `CodigoProducto` | Mantener ambos en fase de convivencia |\n";
$pcMapaMd .= "| Producto | `PRODUCTO` | `PesoGramos` | `PesoGr` | Normalizar en payloads nuevos |\n";
$pcMapaMd .= "| Reserva | `RESERVA` | `ReservaExterna` | `CodigoReservaExterna` | Alias logico recomendado en adaptador |\n";
$pcMapaMd .= "| Maquina | `MAQUINA` | `UbicacionActual` | `Ubicacion` | En dominio operativo usar nombre de entidad |\n";
$pcMapaMd .= "| Venta | `VENTA` | `PrecioUnitario` | `PrecioVentaUnitario` | Mantener actual por compatibilidad SQL |\n";
$pcMapaMd .= "| Imagenes | `PRODUCTOIMAGEN` | `RutaImagen` | `RutaObjeto` | Persistir key, resolver URL en servicio |\n";
$pcMapaMd .= "| Sesion API | `SESIONAPI` | `HashTokenAcceso` | `TokenAccesoHash` | Cambio solo logico en adaptador |\n";
$pcMapaMd .= "| Estados | `ESTADO` | `CodigoEstado` | `CodigoEstado` | Ya alineado: entidad + codigo |\n\n";
$pcMapaMd .= "## Convencion para APIs nuevas\n\n";
$pcMapaMd .= "- Exponer nombres funcionales estables al frontend.\n";
$pcMapaMd .= "- Resolver diferencias de nombre en capa service/adaptador.\n";
$pcMapaMd .= "- Evitar exponer nombres tecnicos de transicion.\n";

file_put_contents($pcDocsDir . DIRECTORY_SEPARATOR . 'MAPA_EQUIVALENCIAS_TABLA_CAMPO.md', $pcMapaMd);

$pcSqlMaquina = <<<SQL
DROP VIEW IF EXISTS VW_MAQUINA_OPERATIVA;
CREATE VIEW VW_MAQUINA_OPERATIVA AS
SELECT
    m.Maquina,
    m.CodigoMaquina,
    m.NumeroSerie,
    m.Marca,
    m.Modelo,
    m.Estado AS EstadoMaquina,
    m.UbicacionActual AS Ubicacion,
    u.Empresa,
    u.NombreUbicacion,
    u.Departamento,
    u.Ciudad,
    u.Zona,
    u.Direccion,
    u.Latitud,
    u.Longitud,
    ti.TipoInternet,
    ti.CodigoTipoInternet,
    ti.NombreTipoInternet,
    tli.TipoLugarInstalacion,
    tli.CodigoTipoLugar,
    tli.NombreTipoLugar
FROM MAQUINA m
LEFT JOIN UBICACION u ON u.Ubicacion = m.UbicacionActual
LEFT JOIN TIPOINTERNET ti ON ti.TipoInternet = m.TipoInternet
LEFT JOIN TIPOLUGARINSTALACION tli ON tli.TipoLugarInstalacion = u.TipoLugarInstalacion;
SQL;
file_put_contents($pcSqlCompatDir . DIRECTORY_SEPARATOR . 'vw_maquina_operativa.sql', $pcSqlMaquina . PHP_EOL);

$pcSqlStock = <<<SQL
DROP VIEW IF EXISTS VW_STOCK_CELDA_DETALLE;
CREATE VIEW VW_STOCK_CELDA_DETALLE AS
SELECT
    c.Maquina,
    c.Celda,
    c.CodigoSeleccion,
    c.Fila,
    c.Columna,
    c.CapacidadMaxima,
    ec.ExistenciaCelda,
    ec.ProductoEmpresa,
    p.Producto,
    p.CodigoProducto,
    p.NombreProducto,
    ec.Lote,
    ec.CantidadDisponible,
    ec.CantidadReservada,
    (ec.CantidadDisponible + ec.CantidadReservada) AS CantidadTotal
FROM CELDA c
LEFT JOIN EXISTENCIACELDA ec ON ec.Celda = c.Celda
LEFT JOIN PRODUCTOEMPRESA pe ON pe.ProductoEmpresa = ec.ProductoEmpresa
LEFT JOIN PRODUCTO p ON p.Producto = pe.Producto;
SQL;
file_put_contents($pcSqlCompatDir . DIRECTORY_SEPARATOR . 'vw_stock_celda_detalle.sql', $pcSqlStock . PHP_EOL);

$pcSqlTransaccion = <<<SQL
DROP VIEW IF EXISTS VW_TRANSACCION_COMERCIAL;
CREATE VIEW VW_TRANSACCION_COMERCIAL AS
SELECT
    'VENTA' AS TipoTransaccion,
    v.Venta AS IdTransaccion,
    v.Maquina,
    v.Celda,
    v.ProductoEmpresa,
    v.Lote,
    v.Cantidad,
    v.PrecioUnitario,
    (v.Cantidad * v.PrecioUnitario) AS Importe,
    v.FechaVenta AS FechaHora,
    v.Estado,
    v.Usr,
    v.UsrFecha,
    v.UsrHora
FROM VENTA v
UNION ALL
SELECT
    'REPOSICION' AS TipoTransaccion,
    r.Reposicion AS IdTransaccion,
    r.Maquina,
    NULL AS Celda,
    NULL AS ProductoEmpresa,
    NULL AS Lote,
    NULL AS Cantidad,
    NULL AS PrecioUnitario,
    NULL AS Importe,
    r.FechaHoraReposicion AS FechaHora,
    r.Estado,
    r.Usr,
    r.UsrFecha,
    r.UsrHora
FROM REPOSICION r
UNION ALL
SELECT
    'RESERVA' AS TipoTransaccion,
    rs.Reserva AS IdTransaccion,
    rs.Maquina,
    NULL AS Celda,
    NULL AS ProductoEmpresa,
    NULL AS Lote,
    NULL AS Cantidad,
    NULL AS PrecioUnitario,
    NULL AS Importe,
    rs.FechaHoraReserva AS FechaHora,
    rs.Estado,
    rs.Usr,
    rs.UsrFecha,
    rs.UsrHora
FROM RESERVA rs;
SQL;
file_put_contents($pcSqlCompatDir . DIRECTORY_SEPARATOR . 'vw_transaccion_comercial.sql', $pcSqlTransaccion . PHP_EOL);

$pcSqlUsuario = <<<SQL
DROP VIEW IF EXISTS VW_USUARIO_ACCESO;
CREATE VIEW VW_USUARIO_ACCESO AS
SELECT
    u.Usuario,
    u.NombreUsuario,
    u.Nombres,
    u.Empresa,
    ur.Rol,
    r.CodigoRol,
    r.NombreRol,
    um.Maquina,
    um.Estado AS EstadoAsignacion
FROM USUARIO u
LEFT JOIN USUARIOROL ur ON ur.Usuario = u.Usuario
LEFT JOIN ROL r ON r.Rol = ur.Rol
LEFT JOIN USUARIOMAQUINA um ON um.Usuario = u.Usuario;
SQL;
file_put_contents($pcSqlCompatDir . DIRECTORY_SEPARATOR . 'vw_usuario_acceso.sql', $pcSqlUsuario . PHP_EOL);

$pcReadmeCompat = "# SQL compatibilidad (Fase 2)\n\n";
$pcReadmeCompat .= "Archivos `vw_*.sql` proponen vistas de lectura para capa adaptadora sin romper contratos actuales.\n";
$pcReadmeCompat .= "Aplicar primero en staging replica y validar paridad de payloads antes de promover.\n";
file_put_contents($pcSqlCompatDir . DIRECTORY_SEPARATOR . 'README.md', $pcReadmeCompat);

echo "Diagnostico generado en:\n";
echo "- docs/diagnostico/MATRIZ_API_TABLA.csv\n";
echo "- docs/diagnostico/MATRIZ_TABLA_USO.csv\n";
echo "- docs/diagnostico/CLASIFICACION_TABLAS.md\n";
echo "- docs/diagnostico/BASELINE_METRICAS.md\n";
echo "- docs/diagnostico/ESTANDAR_NOMENCLATURA_SYSCOOP.md\n";
echo "- docs/diagnostico/MAPA_EQUIVALENCIAS_TABLA_CAMPO.md\n";
echo "- sql/compat/vw_*.sql\n";

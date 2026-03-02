<?php

namespace App\Modulos\Anuncio\Services;

use App\Soporte\AuditoriaService;
use App\Soporte\ControlVersionService;
use App\Soporte\EstadoNegocioService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class AnuncioService
{
    private string $pcConexion = 'mysqlNegocio';

    public function __construct(
        private EstadoNegocioService $toEstadoNegocio,
        private ControlVersionService $toControlVersion,
        private AuditoriaService $toAuditoria
    ) {
    }

    public function listar(?int $tnEmpresa, ?int $tnEstado, int $tnPagina, int $tnTamanoPagina): LengthAwarePaginator
    {
        $tnTamanoPagina = max(1, min($tnTamanoPagina, 200));

        $to = DB::connection($this->pcConexion)->table('ANUNCIO')->orderByDesc('Anuncio');
        if ($tnEmpresa !== null && $tnEmpresa > 0) {
            $to->where('Empresa', $tnEmpresa);
        }
        if ($tnEstado !== null && $tnEstado > 0) {
            $to->where('Estado', $tnEstado);
        }

        return $to->paginate($tnTamanoPagina, ['*'], 'Pagina', max(1, $tnPagina));
    }

    public function obtener(int $tnAnuncio): ?array
    {
        $lo = DB::connection($this->pcConexion)->table('ANUNCIO')->where('Anuncio', $tnAnuncio)->first();
        if (!$lo) {
            return null;
        }

        $la = (array)$lo;
        $la['Version'] = $this->toControlVersion->versionDesdeFila($lo);
        $la['MaquinasAsignadas'] = $this->listarMaquinas($tnAnuncio);
        $la['ProductosAsignados'] = $this->listarProductos($tnAnuncio);

        return $la;
    }

    /** @param array<string,mixed> $taDatos */
    public function crear(array $taDatos, int $tnUsuario): array
    {
        $tdAhora = now();
        $tnEstadoBorrador = $this->toEstadoNegocio->estado('ANUNCIO', 1);

        $tnId = DB::connection($this->pcConexion)->table('ANUNCIO')->insertGetId([
            'Empresa' => (int)$taDatos['Empresa'],
            'AlcanceAnuncio' => (int)$taDatos['AlcanceAnuncio'],
            'Titulo' => (string)$taDatos['Titulo'],
            'Descripcion' => $taDatos['Descripcion'] ?? null,
            'RutaImagen' => $taDatos['RutaImagen'] ?? null,
            'FechaHoraInicio' => $taDatos['FechaHoraInicio'],
            'FechaHoraFin' => $taDatos['FechaHoraFin'] ?? null,
            'Prioridad' => (int)($taDatos['Prioridad'] ?? 1),
            'Estado' => (int)($taDatos['Estado'] ?? $tnEstadoBorrador),
            'Usr' => $tnUsuario,
            'UsrFecha' => $tdAhora->toDateString(),
            'UsrHora' => $tdAhora->format('H:i:s'),
        ]);

        $la = $this->obtener($tnId) ?? [];
        $this->toAuditoria->registrar('ANUNCIO', $tnId, 'CREAR', null, $la, $tnUsuario, $taDatos['Motivo'] ?? null);

        return $la;
    }

    /** @param array<string,mixed> $taDatos */
    public function actualizar(int $tnAnuncio, array $taDatos, string $tcVersion, int $tnUsuario, bool $lbParcial): array
    {
        return DB::connection($this->pcConexion)->transaction(function () use ($tnAnuncio, $taDatos, $tcVersion, $tnUsuario, $lbParcial): array {
            $loActual = DB::connection($this->pcConexion)
                ->table('ANUNCIO')
                ->where('Anuncio', $tnAnuncio)
                ->lockForUpdate()
                ->first();

            if (!$loActual) {
                return ['Estado' => 'NO_ENCONTRADO'];
            }
            if (!$this->toControlVersion->coincide($tcVersion, $loActual)) {
                return ['Estado' => 'CONFLICTO_VERSION', 'Actual' => $this->obtener($tnAnuncio)];
            }

            $laCampos = ['Empresa', 'AlcanceAnuncio', 'Titulo', 'Descripcion', 'RutaImagen', 'FechaHoraInicio', 'FechaHoraFin', 'Prioridad', 'Estado'];
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

            DB::connection($this->pcConexion)->table('ANUNCIO')->where('Anuncio', $tnAnuncio)->update($laUpdate);

            $laDespues = $this->obtener($tnAnuncio) ?? [];
            $this->toAuditoria->registrar('ANUNCIO', $tnAnuncio, 'ACTUALIZAR', (array)$loActual, $laDespues, $tnUsuario, $taDatos['Motivo'] ?? null);

            return ['Estado' => 'OK', 'Datos' => $laDespues];
        });
    }

    public function eliminarLogico(int $tnAnuncio, string $tcVersion, int $tnUsuario, ?string $tcMotivo): array
    {
        return DB::connection($this->pcConexion)->transaction(function () use ($tnAnuncio, $tcVersion, $tnUsuario, $tcMotivo): array {
            $loActual = DB::connection($this->pcConexion)
                ->table('ANUNCIO')
                ->where('Anuncio', $tnAnuncio)
                ->lockForUpdate()
                ->first();

            if (!$loActual) {
                return ['Estado' => 'NO_ENCONTRADO'];
            }
            if (!$this->toControlVersion->coincide($tcVersion, $loActual)) {
                return ['Estado' => 'CONFLICTO_VERSION', 'Actual' => $this->obtener($tnAnuncio)];
            }

            $tdAhora = now();
            DB::connection($this->pcConexion)->table('ANUNCIO')->where('Anuncio', $tnAnuncio)->update([
                'Estado' => $this->toEstadoNegocio->estado('ANUNCIO', 4),
                'Usr' => $tnUsuario,
                'UsrFecha' => $tdAhora->toDateString(),
                'UsrHora' => $tdAhora->format('H:i:s'),
            ]);

            DB::connection($this->pcConexion)->table('ANUNCIOMAQUINA')->where('Anuncio', $tnAnuncio)->update([
                'Estado' => $this->toEstadoNegocio->inactivoGeneral(),
                'Usr' => $tnUsuario,
                'UsrFecha' => $tdAhora->toDateString(),
                'UsrHora' => $tdAhora->format('H:i:s'),
            ]);
            DB::connection($this->pcConexion)->table('ANUNCIOPRODUCTO')->where('Anuncio', $tnAnuncio)->update([
                'Estado' => $this->toEstadoNegocio->inactivoGeneral(),
                'Usr' => $tnUsuario,
                'UsrFecha' => $tdAhora->toDateString(),
                'UsrHora' => $tdAhora->format('H:i:s'),
            ]);

            $laDespues = $this->obtener($tnAnuncio) ?? [];
            $this->toAuditoria->registrar('ANUNCIO', $tnAnuncio, 'ELIMINAR_LOGICO', (array)$loActual, $laDespues, $tnUsuario, $tcMotivo);

            return ['Estado' => 'OK', 'Datos' => $laDespues];
        });
    }

    public function asignarMaquina(int $tnAnuncio, int $tnMaquina, int $tnUsuario, ?string $tcMotivo): array
    {
        $tdAhora = now();
        $tnEstadoActivo = $this->toEstadoNegocio->activoGeneral();

        $lo = DB::connection($this->pcConexion)->table('ANUNCIOMAQUINA')->where('Anuncio', $tnAnuncio)->where('Maquina', $tnMaquina)->first();
        if ($lo) {
            DB::connection($this->pcConexion)->table('ANUNCIOMAQUINA')->where('AnuncioMaquina', (int)$lo->AnuncioMaquina)->update([
                'Estado' => $tnEstadoActivo,
                'Usr' => $tnUsuario,
                'UsrFecha' => $tdAhora->toDateString(),
                'UsrHora' => $tdAhora->format('H:i:s'),
            ]);
            $la = (array)DB::connection($this->pcConexion)->table('ANUNCIOMAQUINA')->where('AnuncioMaquina', (int)$lo->AnuncioMaquina)->first();
            $this->toAuditoria->registrar('ANUNCIOMAQUINA', (int)$lo->AnuncioMaquina, 'ASIGNAR', (array)$lo, $la, $tnUsuario, $tcMotivo);
            return $la;
        }

        $tnId = DB::connection($this->pcConexion)->table('ANUNCIOMAQUINA')->insertGetId([
            'Anuncio' => $tnAnuncio,
            'Maquina' => $tnMaquina,
            'Estado' => $tnEstadoActivo,
            'Usr' => $tnUsuario,
            'UsrFecha' => $tdAhora->toDateString(),
            'UsrHora' => $tdAhora->format('H:i:s'),
        ]);

        $la = (array)DB::connection($this->pcConexion)->table('ANUNCIOMAQUINA')->where('AnuncioMaquina', $tnId)->first();
        $this->toAuditoria->registrar('ANUNCIOMAQUINA', $tnId, 'ASIGNAR', null, $la, $tnUsuario, $tcMotivo);

        return $la;
    }

    public function quitarMaquina(int $tnAnuncio, int $tnMaquina, int $tnUsuario, ?string $tcMotivo): array
    {
        $lo = DB::connection($this->pcConexion)->table('ANUNCIOMAQUINA')->where('Anuncio', $tnAnuncio)->where('Maquina', $tnMaquina)->first();
        if (!$lo) {
            return ['Estado' => 'NO_ENCONTRADO'];
        }

        $tdAhora = now();
        DB::connection($this->pcConexion)->table('ANUNCIOMAQUINA')->where('AnuncioMaquina', (int)$lo->AnuncioMaquina)->update([
            'Estado' => $this->toEstadoNegocio->inactivoGeneral(),
            'Usr' => $tnUsuario,
            'UsrFecha' => $tdAhora->toDateString(),
            'UsrHora' => $tdAhora->format('H:i:s'),
        ]);

        $la = (array)DB::connection($this->pcConexion)->table('ANUNCIOMAQUINA')->where('AnuncioMaquina', (int)$lo->AnuncioMaquina)->first();
        $this->toAuditoria->registrar('ANUNCIOMAQUINA', (int)$lo->AnuncioMaquina, 'ELIMINAR_LOGICO', (array)$lo, $la, $tnUsuario, $tcMotivo);

        return ['Estado' => 'OK', 'Datos' => $la];
    }

    public function asignarProducto(int $tnAnuncio, int $tnProducto, int $tnUsuario, ?string $tcMotivo): array
    {
        $tdAhora = now();
        $tnEstadoActivo = $this->toEstadoNegocio->activoGeneral();

        $lo = DB::connection($this->pcConexion)->table('ANUNCIOPRODUCTO')->where('Anuncio', $tnAnuncio)->where('Producto', $tnProducto)->first();
        if ($lo) {
            DB::connection($this->pcConexion)->table('ANUNCIOPRODUCTO')->where('AnuncioProducto', (int)$lo->AnuncioProducto)->update([
                'Estado' => $tnEstadoActivo,
                'Usr' => $tnUsuario,
                'UsrFecha' => $tdAhora->toDateString(),
                'UsrHora' => $tdAhora->format('H:i:s'),
            ]);
            $la = (array)DB::connection($this->pcConexion)->table('ANUNCIOPRODUCTO')->where('AnuncioProducto', (int)$lo->AnuncioProducto)->first();
            $this->toAuditoria->registrar('ANUNCIOPRODUCTO', (int)$lo->AnuncioProducto, 'ASIGNAR', (array)$lo, $la, $tnUsuario, $tcMotivo);
            return $la;
        }

        $tnId = DB::connection($this->pcConexion)->table('ANUNCIOPRODUCTO')->insertGetId([
            'Anuncio' => $tnAnuncio,
            'Producto' => $tnProducto,
            'Estado' => $tnEstadoActivo,
            'Usr' => $tnUsuario,
            'UsrFecha' => $tdAhora->toDateString(),
            'UsrHora' => $tdAhora->format('H:i:s'),
        ]);

        $la = (array)DB::connection($this->pcConexion)->table('ANUNCIOPRODUCTO')->where('AnuncioProducto', $tnId)->first();
        $this->toAuditoria->registrar('ANUNCIOPRODUCTO', $tnId, 'ASIGNAR', null, $la, $tnUsuario, $tcMotivo);

        return $la;
    }

    public function quitarProducto(int $tnAnuncio, int $tnProducto, int $tnUsuario, ?string $tcMotivo): array
    {
        $lo = DB::connection($this->pcConexion)->table('ANUNCIOPRODUCTO')->where('Anuncio', $tnAnuncio)->where('Producto', $tnProducto)->first();
        if (!$lo) {
            return ['Estado' => 'NO_ENCONTRADO'];
        }

        $tdAhora = now();
        DB::connection($this->pcConexion)->table('ANUNCIOPRODUCTO')->where('AnuncioProducto', (int)$lo->AnuncioProducto)->update([
            'Estado' => $this->toEstadoNegocio->inactivoGeneral(),
            'Usr' => $tnUsuario,
            'UsrFecha' => $tdAhora->toDateString(),
            'UsrHora' => $tdAhora->format('H:i:s'),
        ]);

        $la = (array)DB::connection($this->pcConexion)->table('ANUNCIOPRODUCTO')->where('AnuncioProducto', (int)$lo->AnuncioProducto)->first();
        $this->toAuditoria->registrar('ANUNCIOPRODUCTO', (int)$lo->AnuncioProducto, 'ELIMINAR_LOGICO', (array)$lo, $la, $tnUsuario, $tcMotivo);

        return ['Estado' => 'OK', 'Datos' => $la];
    }

    public function publicar(int $tnAnuncio, int $tnUsuario, ?string $tcMotivo): array
    {
        return $this->cambiarEstadoAnuncio($tnAnuncio, $this->toEstadoNegocio->estado('ANUNCIO', 2), 'PUBLICAR', $tnUsuario, $tcMotivo);
    }

    public function detener(int $tnAnuncio, int $tnUsuario, ?string $tcMotivo): array
    {
        return $this->cambiarEstadoAnuncio($tnAnuncio, $this->toEstadoNegocio->estado('ANUNCIO', 3), 'DETENER', $tnUsuario, $tcMotivo);
    }

    public function impacto(int $tnAnuncio): ?array
    {
        $lo = DB::connection($this->pcConexion)->table('ANUNCIO')->where('Anuncio', $tnAnuncio)->first();
        if (!$lo) {
            return null;
        }

        $tnActivos = $this->toEstadoNegocio->activoGeneral();

        $tnMaquinas = (int)DB::connection($this->pcConexion)->table('ANUNCIOMAQUINA')->where('Anuncio', $tnAnuncio)->where('Estado', $tnActivos)->count();
        $tnProductos = (int)DB::connection($this->pcConexion)->table('ANUNCIOPRODUCTO')->where('Anuncio', $tnAnuncio)->where('Estado', $tnActivos)->count();

        return [
            'Anuncio' => $tnAnuncio,
            'Titulo' => $lo->Titulo,
            'Estado' => (int)$lo->Estado,
            'TotalMaquinasActivas' => $tnMaquinas,
            'TotalProductosActivos' => $tnProductos,
            'EsImpactoMasivo' => $tnMaquinas === 0 && $tnProductos === 0,
        ];
    }

    /** @return array<int,array<string,mixed>> */
    public function listarMaquinas(int $tnAnuncio): array
    {
        return DB::connection($this->pcConexion)->table('ANUNCIOMAQUINA as am')
            ->join('MAQUINA as m', 'm.Maquina', '=', 'am.Maquina')
            ->select(['am.AnuncioMaquina', 'am.Anuncio', 'am.Maquina', 'm.CodigoMaquina', 'am.Estado', 'am.UsrFecha', 'am.UsrHora'])
            ->where('am.Anuncio', $tnAnuncio)
            ->orderBy('am.AnuncioMaquina')
            ->get()
            ->map(fn ($toFila) => array_merge((array)$toFila, ['Version' => $this->toControlVersion->versionDesdeFila($toFila)]))
            ->all();
    }

    /** @return array<int,array<string,mixed>> */
    public function listarProductos(int $tnAnuncio): array
    {
        return DB::connection($this->pcConexion)->table('ANUNCIOPRODUCTO as ap')
            ->join('PRODUCTO as p', 'p.Producto', '=', 'ap.Producto')
            ->select(['ap.AnuncioProducto', 'ap.Anuncio', 'ap.Producto', 'p.NombreProducto', 'ap.Estado', 'ap.UsrFecha', 'ap.UsrHora'])
            ->where('ap.Anuncio', $tnAnuncio)
            ->orderBy('ap.AnuncioProducto')
            ->get()
            ->map(fn ($toFila) => array_merge((array)$toFila, ['Version' => $this->toControlVersion->versionDesdeFila($toFila)]))
            ->all();
    }

    private function cambiarEstadoAnuncio(int $tnAnuncio, int $tnEstadoNuevo, string $tcAccion, int $tnUsuario, ?string $tcMotivo): array
    {
        return DB::connection($this->pcConexion)->transaction(function () use ($tnAnuncio, $tnEstadoNuevo, $tcAccion, $tnUsuario, $tcMotivo): array {
            $lo = DB::connection($this->pcConexion)->table('ANUNCIO')->where('Anuncio', $tnAnuncio)->lockForUpdate()->first();
            if (!$lo) {
                return ['Estado' => 'NO_ENCONTRADO'];
            }

            if ((int)$lo->Estado === $tnEstadoNuevo) {
                return ['Estado' => 'IDEMPOTENTE', 'Datos' => $this->obtener($tnAnuncio)];
            }

            $tdAhora = now();
            DB::connection($this->pcConexion)->table('ANUNCIO')->where('Anuncio', $tnAnuncio)->update([
                'Estado' => $tnEstadoNuevo,
                'Usr' => $tnUsuario,
                'UsrFecha' => $tdAhora->toDateString(),
                'UsrHora' => $tdAhora->format('H:i:s'),
            ]);

            $laDespues = $this->obtener($tnAnuncio) ?? [];
            $this->toAuditoria->registrar('ANUNCIO', $tnAnuncio, $tcAccion, (array)$lo, $laDespues, $tnUsuario, $tcMotivo);

            return ['Estado' => 'OK', 'Datos' => $laDespues];
        });
    }
}

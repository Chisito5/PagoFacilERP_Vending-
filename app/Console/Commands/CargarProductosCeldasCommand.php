<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CargarProductosCeldasCommand extends Command
{
    protected $signature = 'demo:cargar-productos-celdas {--max-celdas=54}';

    protected $description = 'Carga productos demo y los asigna a celdas sin superar el maximo por maquina';

    private string $pcConexion = 'mysqlNegocio';

    /** @var array<int,array{sku:string,nombre:string,marca:string,precio:float}> */
    private array $paCatalogo = [
        ['sku' => 'SKU-003', 'nombre' => 'Pepsi 355ml', 'marca' => 'Pepsi', 'precio' => 11.50],
        ['sku' => 'SKU-004', 'nombre' => 'Sprite 355ml', 'marca' => 'Coca Cola', 'precio' => 11.50],
        ['sku' => 'SKU-005', 'nombre' => 'Fanta Naranja 355ml', 'marca' => 'Coca Cola', 'precio' => 11.50],
        ['sku' => 'SKU-006', 'nombre' => 'Agua Sin Gas 1L', 'marca' => 'Aqua', 'precio' => 10.00],
        ['sku' => 'SKU-007', 'nombre' => 'Agua con Gas 500ml', 'marca' => 'Aqua', 'precio' => 10.50],
        ['sku' => 'SKU-008', 'nombre' => 'Jugo Naranja 300ml', 'marca' => 'Del Valle', 'precio' => 12.50],
        ['sku' => 'SKU-009', 'nombre' => 'Jugo Manzana 300ml', 'marca' => 'Del Valle', 'precio' => 12.50],
        ['sku' => 'SKU-010', 'nombre' => 'Te Helado Limon 500ml', 'marca' => 'Fuze Tea', 'precio' => 12.00],
        ['sku' => 'SKU-011', 'nombre' => 'Bebida Energetica 250ml', 'marca' => 'Volt', 'precio' => 14.00],
        ['sku' => 'SKU-012', 'nombre' => 'Cafe Frio 250ml', 'marca' => 'Nescafe', 'precio' => 14.50],
        ['sku' => 'SKU-013', 'nombre' => 'Papas Clasicas 45g', 'marca' => 'Lays', 'precio' => 9.00],
        ['sku' => 'SKU-014', 'nombre' => 'Papas BBQ 45g', 'marca' => 'Lays', 'precio' => 9.00],
        ['sku' => 'SKU-015', 'nombre' => 'Mani Salado 40g', 'marca' => 'Planters', 'precio' => 8.50],
        ['sku' => 'SKU-016', 'nombre' => 'Barra Cereal Chocolate', 'marca' => 'Nature Valley', 'precio' => 7.50],
        ['sku' => 'SKU-017', 'nombre' => 'Barra Proteina Vainilla', 'marca' => 'ProteinMax', 'precio' => 11.00],
        ['sku' => 'SKU-018', 'nombre' => 'Galleta Chocolate 60g', 'marca' => 'Oreo', 'precio' => 8.00],
        ['sku' => 'SKU-019', 'nombre' => 'Galleta Vainilla 60g', 'marca' => 'Club Social', 'precio' => 8.00],
        ['sku' => 'SKU-020', 'nombre' => 'Chocolate Leche 40g', 'marca' => 'Milka', 'precio' => 9.50],
        ['sku' => 'SKU-021', 'nombre' => 'Chocolate Amargo 40g', 'marca' => 'Lindt', 'precio' => 10.50],
        ['sku' => 'SKU-022', 'nombre' => 'Goma Menta', 'marca' => 'Trident', 'precio' => 5.00],
        ['sku' => 'SKU-023', 'nombre' => 'Caramelo Frutal', 'marca' => 'Arcor', 'precio' => 4.50],
        ['sku' => 'SKU-024', 'nombre' => 'Mix Frutos Secos 50g', 'marca' => 'Natures Heart', 'precio' => 12.00],
        ['sku' => 'SKU-025', 'nombre' => 'Sandwich Jamon y Queso', 'marca' => 'Fresh', 'precio' => 16.00],
        ['sku' => 'SKU-026', 'nombre' => 'Wrap Pollo', 'marca' => 'Fresh', 'precio' => 17.00],
        ['sku' => 'SKU-027', 'nombre' => 'Yogurt Fresa 150g', 'marca' => 'Yoplait', 'precio' => 10.00],
        ['sku' => 'SKU-028', 'nombre' => 'Yogurt Natural 150g', 'marca' => 'Yoplait', 'precio' => 10.00],
        ['sku' => 'SKU-029', 'nombre' => 'Gaseosa Zero 355ml', 'marca' => 'Coca Cola', 'precio' => 12.00],
        ['sku' => 'SKU-030', 'nombre' => 'Agua Saborizada 500ml', 'marca' => 'Aquarius', 'precio' => 11.00],
    ];

    public function handle(): int
    {
        $tnMaxCeldas = max(1, min((int)$this->option('max-celdas'), 54));
        $tdAhora = now();
        $tcFecha = $tdAhora->toDateString();
        $tcHora = $tdAhora->format('H:i:s');
        $tnEstadoActivo = 1;

        $tnEmpresa = (int)(DB::connection($this->pcConexion)
            ->table('EMPRESA')
            ->where('Estado', $tnEstadoActivo)
            ->min('Empresa') ?? 1);

        $laProductosEmpresa = $this->crearCatalogo($tnEmpresa, $tcFecha, $tcHora, $tnEstadoActivo);
        $laCodigos = $this->generarCodigosCeldas();
        $laMaquinas = DB::connection($this->pcConexion)
            ->table('MAQUINA')
            ->where('Estado', $tnEstadoActivo)
            ->orderBy('Maquina')
            ->pluck('Maquina')
            ->all();

        $laResumen = [];
        foreach ($laMaquinas as $tnMaquina) {
            $laResumen[] = DB::connection($this->pcConexion)->transaction(function () use (
                $tnMaquina,
                $tnMaxCeldas,
                $laCodigos,
                $laProductosEmpresa,
                $tcFecha,
                $tcHora,
                $tnEstadoActivo,
                $tdAhora
            ): array {
                $tnMaquina = (int)$tnMaquina;
                $tnPlanograma = $this->obtenerOCrearPlanograma($tnMaquina, $tdAhora, $tcFecha, $tcHora, $tnEstadoActivo);
                $tnNuevasCeldas = $this->crearCeldasFaltantes($tnMaquina, $tnMaxCeldas, $laCodigos, $tcFecha, $tcHora, $tnEstadoActivo);
                [$tnPlanogramaNuevas, $tnExistenciasNuevas] = $this->asignarProductosCeldas($tnMaquina, $tnPlanograma, $tnMaxCeldas, $laProductosEmpresa, $tcFecha, $tcHora, $tnEstadoActivo);

                return [
                    'Maquina' => $tnMaquina,
                    'CeldasTotal' => (int)DB::connection($this->pcConexion)->table('CELDA')->where('Maquina', $tnMaquina)->count(),
                    'CeldasNuevas' => $tnNuevasCeldas,
                    'PlanogramaNuevas' => $tnPlanogramaNuevas,
                    'ExistenciaNuevas' => $tnExistenciasNuevas,
                ];
            });
        }

        $this->table(['Maquina', 'CeldasTotal', 'CeldasNuevas', 'PlanogramaNuevas', 'ExistenciaNuevas'], $laResumen);
        $this->info('Productos en PRODUCTO: ' . (int)DB::connection($this->pcConexion)->table('PRODUCTO')->count());
        $this->info('Productos en PRODUCTOEMPRESA: ' . (int)DB::connection($this->pcConexion)->table('PRODUCTOEMPRESA')->count());

        return self::SUCCESS;
    }

    /** @return array<int,array{ProductoEmpresa:int,Lote:int,Precio:float}> */
    private function crearCatalogo(int $tnEmpresa, string $tcFecha, string $tcHora, int $tnEstadoActivo): array
    {
        $laResultado = [];

        foreach ($this->paCatalogo as $tnIdx => $laItem) {
            $loProducto = DB::connection($this->pcConexion)
                ->table('PRODUCTO')
                ->where('CodigoSku', $laItem['sku'])
                ->first();

            if (!$loProducto) {
                $tnProducto = (int)DB::connection($this->pcConexion)->table('PRODUCTO')->insertGetId([
                    'Empresa' => $tnEmpresa,
                    'CodigoSku' => $laItem['sku'],
                    'CodigoBarra' => str_pad((string)(770000000000 + $tnIdx + 1), 13, '0', STR_PAD_LEFT),
                    'NombreProducto' => $laItem['nombre'],
                    'Descripcion' => $laItem['nombre'],
                    'Marca' => $laItem['marca'],
                    'ContenidoCantidad' => null,
                    'UnidadMedidaContenido' => null,
                    'PesoGramos' => null,
                    'SubgrupoProducto' => null,
                    'Estado' => $tnEstadoActivo,
                    'Usr' => 0,
                    'UsrFecha' => $tcFecha,
                    'UsrHora' => $tcHora,
                ]);
            } else {
                $tnProducto = (int)$loProducto->Producto;
            }

            $loProductoEmpresa = DB::connection($this->pcConexion)
                ->table('PRODUCTOEMPRESA')
                ->where('Empresa', $tnEmpresa)
                ->where('Producto', $tnProducto)
                ->first();

            if (!$loProductoEmpresa) {
                $tnProductoEmpresa = (int)DB::connection($this->pcConexion)->table('PRODUCTOEMPRESA')->insertGetId([
                    'Empresa' => $tnEmpresa,
                    'Producto' => $tnProducto,
                    'NombrePublico' => $laItem['nombre'],
                    'DescripcionPublica' => $laItem['nombre'],
                    'PlantillaVisual' => null,
                    'Estado' => $tnEstadoActivo,
                    'Usr' => 0,
                    'UsrFecha' => $tcFecha,
                    'UsrHora' => $tcHora,
                ]);
            } else {
                $tnProductoEmpresa = (int)$loProductoEmpresa->ProductoEmpresa;
            }

            $tcCodigoLote = 'LOTE-' . str_replace('SKU-', '', $laItem['sku']) . '-001';
            $loLote = DB::connection($this->pcConexion)
                ->table('LOTE')
                ->where('Producto', $tnProducto)
                ->where('CodigoLote', $tcCodigoLote)
                ->first();

            if (!$loLote) {
                $tnLote = (int)DB::connection($this->pcConexion)->table('LOTE')->insertGetId([
                    'Producto' => $tnProducto,
                    'CodigoLote' => $tcCodigoLote,
                    'FechaVencimiento' => now()->addMonths(12)->toDateString(),
                    'FechaRegistro' => $tcFecha,
                    'CantidadInicial' => 500,
                    'Estado' => $tnEstadoActivo,
                    'Usr' => 0,
                    'UsrFecha' => $tcFecha,
                    'UsrHora' => $tcHora,
                ]);
            } else {
                $tnLote = (int)$loLote->Lote;
            }

            $laResultado[] = [
                'ProductoEmpresa' => $tnProductoEmpresa,
                'Lote' => $tnLote,
                'Precio' => (float)$laItem['precio'],
            ];
        }

        return $laResultado;
    }

    /** @return array<int,array{Codigo:string,Fila:int,Columna:int}> */
    private function generarCodigosCeldas(): array
    {
        $la = [];
        foreach (range('A', 'I') as $tcFila) {
            foreach (range(1, 6) as $tnColumna) {
                $la[] = [
                    'Codigo' => $tcFila . $tnColumna,
                    'Fila' => ord($tcFila) - 64,
                    'Columna' => $tnColumna,
                ];
            }
        }
        return $la;
    }

    private function obtenerOCrearPlanograma(int $tnMaquina, \Illuminate\Support\Carbon $tdAhora, string $tcFecha, string $tcHora, int $tnEstadoActivo): int
    {
        $loPlanograma = DB::connection($this->pcConexion)
            ->table('PLANOGRAMA')
            ->where('Maquina', $tnMaquina)
            ->where('Estado', $tnEstadoActivo)
            ->orderByDesc('Planograma')
            ->first();

        if ($loPlanograma) {
            return (int)$loPlanograma->Planograma;
        }

        return (int)DB::connection($this->pcConexion)->table('PLANOGRAMA')->insertGetId([
            'Maquina' => $tnMaquina,
            'VersionPlanograma' => 1,
            'NombrePlanograma' => 'Planograma Base M' . $tnMaquina,
            'FechaInicio' => $tdAhora,
            'FechaFin' => null,
            'Estado' => $tnEstadoActivo,
            'Usr' => 0,
            'UsrFecha' => $tcFecha,
            'UsrHora' => $tcHora,
        ]);
    }

    /** @param array<int,array{Codigo:string,Fila:int,Columna:int}> $laCodigos */
    private function crearCeldasFaltantes(int $tnMaquina, int $tnMaxCeldas, array $laCodigos, string $tcFecha, string $tcHora, int $tnEstadoActivo): int
    {
        $laExistentes = DB::connection($this->pcConexion)
            ->table('CELDA')
            ->where('Maquina', $tnMaquina)
            ->pluck('CodigoSeleccion')
            ->all();

        $taExiste = array_fill_keys($laExistentes, true);
        $tnCreadas = 0;
        $tnActuales = count($laExistentes);

        foreach ($laCodigos as $laCelda) {
            if ($tnActuales + $tnCreadas >= $tnMaxCeldas) {
                break;
            }
            if (isset($taExiste[$laCelda['Codigo']])) {
                continue;
            }

            DB::connection($this->pcConexion)->table('CELDA')->insert([
                'Maquina' => $tnMaquina,
                'CodigoSeleccion' => $laCelda['Codigo'],
                'Fila' => $laCelda['Fila'],
                'Columna' => $laCelda['Columna'],
                'CapacidadMaxima' => 10,
                'Estado' => $tnEstadoActivo,
                'Usr' => 0,
                'UsrFecha' => $tcFecha,
                'UsrHora' => $tcHora,
            ]);
            $tnCreadas++;
        }

        return $tnCreadas;
    }

    /**
     * @param array<int,array{ProductoEmpresa:int,Lote:int,Precio:float}> $laProductosEmpresa
     * @return array{int,int}
     */
    private function asignarProductosCeldas(
        int $tnMaquina,
        int $tnPlanograma,
        int $tnMaxCeldas,
        array $laProductosEmpresa,
        string $tcFecha,
        string $tcHora,
        int $tnEstadoActivo
    ): array {
        $laCeldas = DB::connection($this->pcConexion)
            ->table('CELDA')
            ->where('Maquina', $tnMaquina)
            ->orderBy('Fila')
            ->orderBy('Columna')
            ->orderBy('Celda')
            ->limit($tnMaxCeldas)
            ->get();

        $tnPlanogramaNuevas = 0;
        $tnExistenciasNuevas = 0;
        $tnIdx = 0;

        foreach ($laCeldas as $loCelda) {
            $laProducto = $laProductosEmpresa[$tnIdx % count($laProductosEmpresa)];
            $tnIdx++;

            $loPlanogramaCelda = DB::connection($this->pcConexion)
                ->table('PLANOGRAMACELDA')
                ->where('Planograma', $tnPlanograma)
                ->where('Celda', (int)$loCelda->Celda)
                ->first();

            if (!$loPlanogramaCelda) {
                $tnStockMaximo = min(10, max(1, (int)$loCelda->CapacidadMaxima));
                DB::connection($this->pcConexion)->table('PLANOGRAMACELDA')->insert([
                    'Planograma' => $tnPlanograma,
                    'Celda' => (int)$loCelda->Celda,
                    'ProductoEmpresa' => (int)$laProducto['ProductoEmpresa'],
                    'PrecioVenta' => $laProducto['Precio'],
                    'StockMinimo' => 1,
                    'StockMaximo' => $tnStockMaximo,
                    'PlanogramaCeldaPrincipal' => null,
                    'Estado' => $tnEstadoActivo,
                    'Usr' => 0,
                    'UsrFecha' => $tcFecha,
                    'UsrHora' => $tcHora,
                ]);
                $tnPlanogramaNuevas++;
            }

            $loExistenciaCelda = DB::connection($this->pcConexion)
                ->table('EXISTENCIACELDA')
                ->where('Celda', (int)$loCelda->Celda)
                ->first();

            if (!$loExistenciaCelda) {
                DB::connection($this->pcConexion)->table('EXISTENCIACELDA')->insert([
                    'Celda' => (int)$loCelda->Celda,
                    'ProductoEmpresa' => (int)$laProducto['ProductoEmpresa'],
                    'Lote' => (int)$laProducto['Lote'],
                    'CantidadDisponible' => min(5, max(1, (int)$loCelda->CapacidadMaxima)),
                    'CantidadReservada' => 0,
                    'Estado' => $tnEstadoActivo,
                    'Usr' => 0,
                    'UsrFecha' => $tcFecha,
                    'UsrHora' => $tcHora,
                ]);
                $tnExistenciasNuevas++;
            }
        }

        return [$tnPlanogramaNuevas, $tnExistenciasNuevas];
    }
}

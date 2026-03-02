<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    private string $pcConexion = 'mysqlNegocio';

    public function up(): void
    {
        DB::connection($this->pcConexion)->transaction(function (): void {
            $tdAhora = now();
            $tcFecha = $tdAhora->toDateString();
            $tcHora = $tdAhora->format('H:i:s');

            $this->upsertEstado('DEPOSITO', 1, 'ACTIVO', 'Deposito activo', 1, $tcFecha, $tcHora);
            $this->upsertEstado('DEPOSITO', 2, 'INACTIVO', 'Deposito inactivo', 2, $tcFecha, $tcHora);

            $this->upsertEstado('STOCKDEPOSITO', 1, 'ACTIVO', 'Stock deposito activo', 1, $tcFecha, $tcHora);
            $this->upsertEstado('STOCKDEPOSITO', 2, 'INACTIVO', 'Stock deposito inactivo', 2, $tcFecha, $tcHora);

            $this->upsertEstado('MOVIMIENTODEPOSITO', 1, 'REGISTRADO', 'Movimiento deposito registrado', 1, $tcFecha, $tcHora);
            $this->upsertEstado('MOVIMIENTODEPOSITO', 2, 'ANULADO', 'Movimiento deposito anulado', 2, $tcFecha, $tcHora);

            $this->upsertEstado('CELDAOCUPACION', 1, 'ACTIVA', 'Ocupacion de celdas activa', 1, $tcFecha, $tcHora);
            $this->upsertEstado('CELDAOCUPACION', 2, 'LIBERADA', 'Ocupacion liberada', 2, $tcFecha, $tcHora);
            $this->upsertEstado('CELDAOCUPACION', 3, 'ANULADA', 'Ocupacion anulada', 3, $tcFecha, $tcHora);

            $this->upsertEstado('CELDACONFLICTO', 1, 'ABIERTO', 'Conflicto operativo abierto', 1, $tcFecha, $tcHora);
            $this->upsertEstado('CELDACONFLICTO', 2, 'RESUELTO', 'Conflicto operativo resuelto', 2, $tcFecha, $tcHora);

            $this->upsertEstado('PRODUCTODISENO', 1, 'ACTIVO', 'Diseno activo', 1, $tcFecha, $tcHora);
            $this->upsertEstado('PRODUCTODISENO', 2, 'INACTIVO', 'Diseno inactivo', 2, $tcFecha, $tcHora);

            $tnEstadoGeneralActivo = $this->obtenerEstado('GENERAL', 1, 1);
            $tnEstadoGeneralInactivo = $this->obtenerEstado('GENERAL', 2, 2);

            $tnDepositoActivo = $this->obtenerEstado('DEPOSITO', 1, $tnEstadoGeneralActivo);
            $tnDepositoInactivo = $this->obtenerEstado('DEPOSITO', 2, $tnEstadoGeneralInactivo);
            $tnStockDepositoActivo = $this->obtenerEstado('STOCKDEPOSITO', 1, $tnEstadoGeneralActivo);
            $tnMovimientoDepositoRegistrado = $this->obtenerEstado('MOVIMIENTODEPOSITO', 1, $tnEstadoGeneralActivo);
            $tnCeldaOcupacionActiva = $this->obtenerEstado('CELDAOCUPACION', 1, $tnEstadoGeneralActivo);
            $tnCeldaConflictoAbierto = $this->obtenerEstado('CELDACONFLICTO', 1, $tnEstadoGeneralActivo);
            $tnProductoDisenoActivo = $this->obtenerEstado('PRODUCTODISENO', 1, $tnEstadoGeneralActivo);

            if (DB::connection($this->pcConexion)->getSchemaBuilder()->hasTable('DEPOSITO')) {
                DB::connection($this->pcConexion)->table('DEPOSITO')
                    ->where('Estado', $tnEstadoGeneralActivo)
                    ->update(['Estado' => $tnDepositoActivo]);
                DB::connection($this->pcConexion)->table('DEPOSITO')
                    ->where('Estado', $tnEstadoGeneralInactivo)
                    ->update(['Estado' => $tnDepositoInactivo]);
            }

            if (DB::connection($this->pcConexion)->getSchemaBuilder()->hasTable('STOCKDEPOSITO')) {
                DB::connection($this->pcConexion)->table('STOCKDEPOSITO')
                    ->where('Estado', $tnEstadoGeneralActivo)
                    ->update(['Estado' => $tnStockDepositoActivo]);
            }

            if (DB::connection($this->pcConexion)->getSchemaBuilder()->hasTable('MOVIMIENTODEPOSITO')) {
                DB::connection($this->pcConexion)->table('MOVIMIENTODEPOSITO')
                    ->where('Estado', $tnEstadoGeneralActivo)
                    ->update(['Estado' => $tnMovimientoDepositoRegistrado]);
            }

            if (DB::connection($this->pcConexion)->getSchemaBuilder()->hasTable('CELDAOCUPACION')) {
                DB::connection($this->pcConexion)->table('CELDAOCUPACION')
                    ->where('Estado', $tnEstadoGeneralActivo)
                    ->update(['Estado' => $tnCeldaOcupacionActiva]);
            }

            if (DB::connection($this->pcConexion)->getSchemaBuilder()->hasTable('CELDACONFLICTO')) {
                DB::connection($this->pcConexion)->table('CELDACONFLICTO')
                    ->where('Estado', $tnEstadoGeneralActivo)
                    ->update(['Estado' => $tnCeldaConflictoAbierto]);
            }

            if (DB::connection($this->pcConexion)->getSchemaBuilder()->hasTable('PRODUCTODISENO')) {
                DB::connection($this->pcConexion)->table('PRODUCTODISENO')
                    ->where('Estado', $tnEstadoGeneralActivo)
                    ->update(['Estado' => $tnProductoDisenoActivo]);
            }

            $this->upsertTipoImagen('FONDO', 'Imagen de fondo para diseno de producto', $tnEstadoGeneralActivo, $tcFecha, $tcHora);
            $this->upsertTipoImagen('OFERTA', 'Imagen promocional para diseno de producto', $tnEstadoGeneralActivo, $tcFecha, $tcHora);

            $this->upsertTipoMovimiento('REPOSICION', 1, 'Entrada por reposicion de maquina', $tnEstadoGeneralActivo, $tcFecha, $tcHora);
            $this->upsertTipoMovimiento('DEPOSITO_ENTRADA', 1, 'Entrada de inventario al deposito', $tnEstadoGeneralActivo, $tcFecha, $tcHora);
            $this->upsertTipoMovimiento('DEPOSITO_SALIDA', -1, 'Salida de inventario desde deposito', $tnEstadoGeneralActivo, $tcFecha, $tcHora);
            $this->upsertTipoMovimiento('DEPOSITO_TRANSFERENCIA', 1, 'Transferencia de deposito a maquina', $tnEstadoGeneralActivo, $tcFecha, $tcHora);
        });
    }

    public function down(): void
    {
        // Sin rollback por tratarse de catalogos.
    }

    private function upsertEstado(
        string $tcEntidad,
        int $tnCodigoEstado,
        string $tcNombre,
        string $tcDescripcion,
        int $tnOrden,
        string $tcFecha,
        string $tcHora
    ): void {
        $loExiste = DB::connection($this->pcConexion)
            ->table('ESTADO')
            ->where('Entidad', $tcEntidad)
            ->where('CodigoEstado', $tnCodigoEstado)
            ->first();

        if ($loExiste) {
            DB::connection($this->pcConexion)->table('ESTADO')->where('Estado', (int)$loExiste->Estado)->update([
                'NombreEstado' => $tcNombre,
                'Descripcion' => $tcDescripcion,
                'Orden' => $tnOrden,
                'UsrFecha' => $tcFecha,
                'UsrHora' => $tcHora,
            ]);
            return;
        }

        DB::connection($this->pcConexion)->table('ESTADO')->insert([
            'Entidad' => $tcEntidad,
            'CodigoEstado' => $tnCodigoEstado,
            'NombreEstado' => $tcNombre,
            'Descripcion' => $tcDescripcion,
            'Orden' => $tnOrden,
            'Usr' => 0,
            'UsrFecha' => $tcFecha,
            'UsrHora' => $tcHora,
        ]);
    }

    private function upsertTipoImagen(string $tcNombre, string $tcDescripcion, int $tnEstado, string $tcFecha, string $tcHora): void
    {
        $loExiste = DB::connection($this->pcConexion)->table('TIPOIMAGEN')->where('NombreTipoImagen', $tcNombre)->first();

        if ($loExiste) {
            DB::connection($this->pcConexion)->table('TIPOIMAGEN')->where('TipoImagen', (int)$loExiste->TipoImagen)->update([
                'Descripcion' => $tcDescripcion,
                'Estado' => $tnEstado,
                'UsrFecha' => $tcFecha,
                'UsrHora' => $tcHora,
            ]);
            return;
        }

        DB::connection($this->pcConexion)->table('TIPOIMAGEN')->insert([
            'NombreTipoImagen' => $tcNombre,
            'Descripcion' => $tcDescripcion,
            'Estado' => $tnEstado,
            'Usr' => 0,
            'UsrFecha' => $tcFecha,
            'UsrHora' => $tcHora,
        ]);
    }

    private function upsertTipoMovimiento(string $tcNombre, int $tnFactor, string $tcDescripcion, int $tnEstado, string $tcFecha, string $tcHora): void
    {
        if (!DB::connection($this->pcConexion)->getSchemaBuilder()->hasTable('TIPOMOVIMIENTOINVENTARIO')) {
            return;
        }

        $loExiste = DB::connection($this->pcConexion)
            ->table('TIPOMOVIMIENTOINVENTARIO')
            ->where('NombreTipoMovimientoInventario', $tcNombre)
            ->first();

        if ($loExiste) {
            DB::connection($this->pcConexion)->table('TIPOMOVIMIENTOINVENTARIO')
                ->where('TipoMovimientoInventario', (int)$loExiste->TipoMovimientoInventario)
                ->update([
                    'Factor' => $tnFactor,
                    'Descripcion' => $tcDescripcion,
                    'Estado' => $tnEstado,
                    'UsrFecha' => $tcFecha,
                    'UsrHora' => $tcHora,
                ]);
            return;
        }

        DB::connection($this->pcConexion)->table('TIPOMOVIMIENTOINVENTARIO')->insert([
            'NombreTipoMovimientoInventario' => $tcNombre,
            'Factor' => $tnFactor,
            'Descripcion' => $tcDescripcion,
            'Estado' => $tnEstado,
            'Usr' => 0,
            'UsrFecha' => $tcFecha,
            'UsrHora' => $tcHora,
        ]);
    }

    private function obtenerEstado(string $tcEntidad, int $tnCodigo, int $tnFallback): int
    {
        $loEstado = DB::connection($this->pcConexion)
            ->table('ESTADO')
            ->select('Estado')
            ->where('Entidad', $tcEntidad)
            ->where('CodigoEstado', $tnCodigo)
            ->first();

        return $loEstado ? (int)$loEstado->Estado : $tnFallback;
    }
};


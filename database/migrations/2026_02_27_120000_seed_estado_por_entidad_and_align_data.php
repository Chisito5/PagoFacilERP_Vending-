<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::connection('mysqlNegocio')->transaction(function () {
            $tdHoy = now();
            $tcFecha = $tdHoy->toDateString();
            $tcHora = $tdHoy->format('H:i:s');

            $this->upsertEstado('RESERVA', 1, 'CREADA', 'Reserva creada', 1, $tcFecha, $tcHora);
            $this->upsertEstado('RESERVA', 2, 'CONFIRMADA', 'Reserva confirmada', 2, $tcFecha, $tcHora);
            $this->upsertEstado('RESERVA', 3, 'EXPIRADA', 'Reserva expirada', 3, $tcFecha, $tcHora);
            $this->upsertEstado('RESERVA', 4, 'CANCELADA', 'Reserva cancelada', 4, $tcFecha, $tcHora);

            $this->upsertEstado('VENTA', 1, 'ACTIVA', 'Venta activa', 1, $tcFecha, $tcHora);
            $this->upsertEstado('VENTA', 2, 'REVERTIDA', 'Venta revertida/anulada', 2, $tcFecha, $tcHora);

            $this->upsertEstado('REPOSICION', 1, 'REGISTRADA', 'Reposicion registrada', 1, $tcFecha, $tcHora);
            $this->upsertEstado('REPOSICION', 2, 'CERRADA', 'Reposicion cerrada', 2, $tcFecha, $tcHora);
            $this->upsertEstado('REPOSICION', 3, 'ANULADA', 'Reposicion anulada', 3, $tcFecha, $tcHora);

            $tnGeneralActivo = $this->obtenerEstadoId('GENERAL', 1);
            $tnGeneralInactivo = $this->obtenerEstadoId('GENERAL', 2);

            $tnReservaCreada = $this->obtenerEstadoId('RESERVA', 1);
            $tnReservaConfirmada = $this->obtenerEstadoId('RESERVA', 2);
            $tnReservaExpirada = $this->obtenerEstadoId('RESERVA', 3);
            $tnReservaCancelada = $this->obtenerEstadoId('RESERVA', 4);

            $tnVentaActiva = $this->obtenerEstadoId('VENTA', 1);
            $tnVentaRevertida = $this->obtenerEstadoId('VENTA', 2);

            $tnReposicionRegistrada = $this->obtenerEstadoId('REPOSICION', 1);
            $tnReposicionCerrada = $this->obtenerEstadoId('REPOSICION', 2);
            $tnReposicionAnulada = $this->obtenerEstadoId('REPOSICION', 3);

            DB::connection('mysqlNegocio')->table('RESERVA')
                ->where('Estado', 1)
                ->update(['Estado' => $tnReservaCreada]);
            DB::connection('mysqlNegocio')->table('RESERVA')
                ->where('Estado', 2)
                ->update(['Estado' => $tnReservaConfirmada]);
            DB::connection('mysqlNegocio')->table('RESERVA')
                ->where('Estado', 3)
                ->update(['Estado' => $tnReservaExpirada]);
            DB::connection('mysqlNegocio')->table('RESERVA')
                ->where('Estado', 4)
                ->update(['Estado' => $tnReservaCancelada]);

            DB::connection('mysqlNegocio')->table('VENTA')
                ->where('Estado', $tnGeneralActivo)
                ->update(['Estado' => $tnVentaActiva]);
            DB::connection('mysqlNegocio')->table('VENTA')
                ->where('Estado', $tnGeneralInactivo)
                ->update(['Estado' => $tnVentaRevertida]);

            DB::connection('mysqlNegocio')->table('REPOSICION')
                ->where('Estado', $tnGeneralActivo)
                ->update(['Estado' => $tnReposicionRegistrada]);
            DB::connection('mysqlNegocio')->table('REPOSICION')
                ->where('Estado', $tnGeneralInactivo)
                ->update(['Estado' => $tnReposicionCerrada]);
            DB::connection('mysqlNegocio')->table('REPOSICION')
                ->where('Estado', 3)
                ->update(['Estado' => $tnReposicionAnulada]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Se deja sin rollback para no desalinear historico de estados en produccion.
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
        $loExiste = DB::connection('mysqlNegocio')
            ->table('ESTADO')
            ->where('Entidad', $tcEntidad)
            ->where('CodigoEstado', $tnCodigoEstado)
            ->first();

        if ($loExiste) {
            DB::connection('mysqlNegocio')
                ->table('ESTADO')
                ->where('Estado', (int)$loExiste->Estado)
                ->update([
                    'NombreEstado' => $tcNombre,
                    'Descripcion' => $tcDescripcion,
                    'Orden' => $tnOrden,
                    'UsrFecha' => $tcFecha,
                    'UsrHora' => $tcHora,
                ]);
            return;
        }

        DB::connection('mysqlNegocio')
            ->table('ESTADO')
            ->insert([
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

    private function obtenerEstadoId(string $tcEntidad, int $tnCodigoEstado): int
    {
        $loEstado = DB::connection('mysqlNegocio')
            ->table('ESTADO')
            ->select('Estado')
            ->where('Entidad', $tcEntidad)
            ->where('CodigoEstado', $tnCodigoEstado)
            ->first();

        if (!$loEstado) {
            throw new RuntimeException("No existe estado {$tcEntidad}/{$tnCodigoEstado}");
        }

        return (int)$loEstado->Estado;
    }
};

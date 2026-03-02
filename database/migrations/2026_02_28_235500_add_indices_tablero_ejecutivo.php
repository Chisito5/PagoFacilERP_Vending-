<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    private string $pcConexion = 'mysqlNegocio';

    public function up(): void
    {
        $this->crearIndiceSiNoExiste('VENTA', 'IX_VENTA_MAQ_FECHA_ESTADO', 'CREATE INDEX IX_VENTA_MAQ_FECHA_ESTADO ON VENTA (Maquina, FechaVenta, Estado)');
        $this->crearIndiceSiNoExiste('ALERTA', 'IX_ALERTA_MAQ_ESTADO_FECHA_PRIORIDAD', 'CREATE INDEX IX_ALERTA_MAQ_ESTADO_FECHA_PRIORIDAD ON ALERTA (Maquina, Estado, FechaHoraGeneracion, Prioridad)');
        $this->crearIndiceSiNoExiste('USUARIOMAQUINA', 'IX_USUARIOMAQUINA_MAQ_USUARIO_ESTADO', 'CREATE INDEX IX_USUARIOMAQUINA_MAQ_USUARIO_ESTADO ON USUARIOMAQUINA (Maquina, Usuario, Estado)');
        $this->crearIndiceSiNoExiste('MAQUINA', 'IX_MAQUINA_ESTADO_UBICACION', 'CREATE INDEX IX_MAQUINA_ESTADO_UBICACION ON MAQUINA (Estado, UbicacionActual)');
        $this->crearIndiceSiNoExiste('UBICACION', 'IX_UBICACION_EMPRESA_UBICACION', 'CREATE INDEX IX_UBICACION_EMPRESA_UBICACION ON UBICACION (Empresa, Ubicacion)');
        $this->crearIndiceSiNoExiste('PLANOGRAMACELDA', 'IX_PLANOGRAMACELDA_CELDA_PRODUCTO_ESTADO', 'CREATE INDEX IX_PLANOGRAMACELDA_CELDA_PRODUCTO_ESTADO ON PLANOGRAMACELDA (Celda, ProductoEmpresa, Estado)');
        $this->crearIndiceSiNoExiste('MOVIMIENTOINVENTARIO', 'IX_MOVINVENTARIO_MAQ_FECHA_PRODUCTO_LOTE', 'CREATE INDEX IX_MOVINVENTARIO_MAQ_FECHA_PRODUCTO_LOTE ON MOVIMIENTOINVENTARIO (Maquina, FechaHora, ProductoEmpresa, Lote)');
    }

    public function down(): void
    {
        $this->eliminarIndiceSiExiste('VENTA', 'IX_VENTA_MAQ_FECHA_ESTADO');
        $this->eliminarIndiceSiExiste('ALERTA', 'IX_ALERTA_MAQ_ESTADO_FECHA_PRIORIDAD');
        $this->eliminarIndiceSiExiste('USUARIOMAQUINA', 'IX_USUARIOMAQUINA_MAQ_USUARIO_ESTADO');
        $this->eliminarIndiceSiExiste('MAQUINA', 'IX_MAQUINA_ESTADO_UBICACION');
        $this->eliminarIndiceSiExiste('UBICACION', 'IX_UBICACION_EMPRESA_UBICACION');
        $this->eliminarIndiceSiExiste('PLANOGRAMACELDA', 'IX_PLANOGRAMACELDA_CELDA_PRODUCTO_ESTADO');
        $this->eliminarIndiceSiExiste('MOVIMIENTOINVENTARIO', 'IX_MOVINVENTARIO_MAQ_FECHA_PRODUCTO_LOTE');
    }

    private function crearIndiceSiNoExiste(string $tcTabla, string $tcIndice, string $tcSql): void
    {
        if (!Schema::connection($this->pcConexion)->hasTable($tcTabla)) {
            return;
        }
        if ($this->existeIndice($tcTabla, $tcIndice)) {
            return;
        }
        DB::connection($this->pcConexion)->statement($tcSql);
    }

    private function eliminarIndiceSiExiste(string $tcTabla, string $tcIndice): void
    {
        if (!Schema::connection($this->pcConexion)->hasTable($tcTabla)) {
            return;
        }
        if (!$this->existeIndice($tcTabla, $tcIndice)) {
            return;
        }
        DB::connection($this->pcConexion)->statement("DROP INDEX {$tcIndice} ON {$tcTabla}");
    }

    private function existeIndice(string $tcTabla, string $tcIndice): bool
    {
        $la = DB::connection($this->pcConexion)->select("SHOW INDEX FROM {$tcTabla} WHERE Key_name = ?", [$tcIndice]);
        return count($la) > 0;
    }
};


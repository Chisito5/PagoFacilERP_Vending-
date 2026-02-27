<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::connection('mysqlNegocio')->hasColumn('LOTE', 'CantidadInicial')) {
            Schema::connection('mysqlNegocio')->table('LOTE', function (Blueprint $table) {
                $table->integer('CantidadInicial')->default(0)->after('FechaRegistro');
            });
        }

        // Backfill: deja CantidadInicial igual al total actualmente asignado en celdas para ese lote.
        DB::connection('mysqlNegocio')->statement("
            UPDATE LOTE l
            LEFT JOIN (
                SELECT Lote, SUM(CantidadDisponible + CantidadReservada) AS Asignado
                FROM EXISTENCIACELDA
                WHERE Lote IS NOT NULL
                GROUP BY Lote
            ) x ON x.Lote = l.Lote
            SET l.CantidadInicial = COALESCE(x.Asignado, 0)
            WHERE l.CantidadInicial = 0
        ");

        // Check simple si el motor lo soporta.
        try {
            DB::connection('mysqlNegocio')->statement(
                "ALTER TABLE LOTE ADD CONSTRAINT CHK_LOTE_CANTIDADINICIAL_NONNEG CHECK (CantidadInicial >= 0)"
            );
        } catch (\Throwable $toEx) {
            // No-op en motores/versiones donde ya exista o no aplique.
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        try {
            DB::connection('mysqlNegocio')->statement(
                "ALTER TABLE LOTE DROP CHECK CHK_LOTE_CANTIDADINICIAL_NONNEG"
            );
        } catch (\Throwable $toEx) {
            // No-op
        }

        if (Schema::connection('mysqlNegocio')->hasColumn('LOTE', 'CantidadInicial')) {
            Schema::connection('mysqlNegocio')->table('LOTE', function (Blueprint $table) {
                $table->dropColumn('CantidadInicial');
            });
        }
    }
};

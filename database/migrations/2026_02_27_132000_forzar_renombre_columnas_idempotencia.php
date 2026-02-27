<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $tcBd = (string)DB::connection('mysqlNegocio')->getDatabaseName();
        $loTabla = DB::connection('mysqlNegocio')->selectOne(
            "SELECT COUNT(*) AS Total FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'idempotencia'",
            [$tcBd]
        );

        if ((int)($loTabla->Total ?? 0) === 0) {
            return;
        }

        $this->renombrarSiExiste($tcBd, 'HashBody', "ALTER TABLE idempotencia CHANGE HashBody HashCuerpo CHAR(64) NOT NULL");
        $this->renombrarSiExiste($tcBd, 'CodigoHttp', "ALTER TABLE idempotencia CHANGE CodigoHttp CodigoRespuesta INT NULL");
        $this->renombrarSiExiste($tcBd, 'RespuestaJson', "ALTER TABLE idempotencia CHANGE RespuestaJson Respuesta LONGTEXT NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tcBd = (string)DB::connection('mysqlNegocio')->getDatabaseName();
        $this->renombrarSiExiste($tcBd, 'HashCuerpo', "ALTER TABLE idempotencia CHANGE HashCuerpo HashBody CHAR(64) NOT NULL");
        $this->renombrarSiExiste($tcBd, 'CodigoRespuesta', "ALTER TABLE idempotencia CHANGE CodigoRespuesta CodigoHttp INT NULL");
        $this->renombrarSiExiste($tcBd, 'Respuesta', "ALTER TABLE idempotencia CHANGE Respuesta RespuestaJson LONGTEXT NULL");
    }

    private function renombrarSiExiste(string $tcBd, string $tcColumna, string $tcSql): void
    {
        $loColumna = DB::connection('mysqlNegocio')->selectOne(
            "SELECT COUNT(*) AS Total
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'idempotencia' AND COLUMN_NAME = ?",
            [$tcBd, $tcColumna]
        );

        if ((int)($loColumna->Total ?? 0) > 0) {
            DB::connection('mysqlNegocio')->statement($tcSql);
        }
    }
};

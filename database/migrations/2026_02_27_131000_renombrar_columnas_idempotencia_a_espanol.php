<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::connection('mysqlNegocio')->hasTable('IDEMPOTENCIA')) {
            return;
        }

        if (Schema::connection('mysqlNegocio')->hasColumn('IDEMPOTENCIA', 'HashBody')) {
            DB::connection('mysqlNegocio')->statement("ALTER TABLE IDEMPOTENCIA CHANGE HashBody HashCuerpo CHAR(64) NOT NULL");
        }

        if (Schema::connection('mysqlNegocio')->hasColumn('IDEMPOTENCIA', 'CodigoHttp')) {
            DB::connection('mysqlNegocio')->statement("ALTER TABLE IDEMPOTENCIA CHANGE CodigoHttp CodigoRespuesta INT NULL");
        }

        if (Schema::connection('mysqlNegocio')->hasColumn('IDEMPOTENCIA', 'RespuestaJson')) {
            DB::connection('mysqlNegocio')->statement("ALTER TABLE IDEMPOTENCIA CHANGE RespuestaJson Respuesta LONGTEXT NULL");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::connection('mysqlNegocio')->hasTable('IDEMPOTENCIA')) {
            return;
        }

        if (Schema::connection('mysqlNegocio')->hasColumn('IDEMPOTENCIA', 'HashCuerpo')) {
            DB::connection('mysqlNegocio')->statement("ALTER TABLE IDEMPOTENCIA CHANGE HashCuerpo HashBody CHAR(64) NOT NULL");
        }

        if (Schema::connection('mysqlNegocio')->hasColumn('IDEMPOTENCIA', 'CodigoRespuesta')) {
            DB::connection('mysqlNegocio')->statement("ALTER TABLE IDEMPOTENCIA CHANGE CodigoRespuesta CodigoHttp INT NULL");
        }

        if (Schema::connection('mysqlNegocio')->hasColumn('IDEMPOTENCIA', 'Respuesta')) {
            DB::connection('mysqlNegocio')->statement("ALTER TABLE IDEMPOTENCIA CHANGE Respuesta RespuestaJson LONGTEXT NULL");
        }
    }
};

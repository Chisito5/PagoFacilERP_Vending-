<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::connection('mysqlNegocio')->table('BITACORA', function (Blueprint $toTable): void {
            if (!Schema::connection('mysqlNegocio')->hasColumn('BITACORA', 'Motivo')) {
                $toTable->string('Motivo', 255)->nullable()->after('Accion');
            }
        });

        Schema::connection('mysqlNegocio')->create('APROBACION', function (Blueprint $toTable): void {
            $toTable->bigIncrements('Aprobacion');
            $toTable->string('Entidad', 80);
            $toTable->string('EntidadId', 80)->nullable();
            $toTable->string('AccionSolicitada', 30);
            $toTable->json('DatosPropuestos')->nullable();
            $toTable->string('Motivo', 255)->nullable();
            $toTable->unsignedTinyInteger('Estado')->default(1); // 1=Solicitada,2=Aprobada,3=Rechazada
            $toTable->unsignedInteger('SolicitadoPor')->nullable();
            $toTable->unsignedInteger('AprobadoPor')->nullable();
            $toTable->dateTime('FechaSolicitud');
            $toTable->dateTime('FechaResolucion')->nullable();
            $toTable->string('ComentarioResolucion', 255)->nullable();
            $toTable->unsignedInteger('Usr')->default(0);
            $toTable->date('UsrFecha');
            $toTable->string('UsrHora', 8);

            $toTable->index(['Entidad', 'Estado'], 'IX_APROBACION_ENTIDAD_ESTADO');
            $toTable->index('FechaSolicitud', 'IX_APROBACION_FECHA_SOLICITUD');
            $toTable->index('FechaResolucion', 'IX_APROBACION_FECHA_RESOLUCION');
        });
    }

    public function down(): void
    {
        Schema::connection('mysqlNegocio')->dropIfExists('APROBACION');

        Schema::connection('mysqlNegocio')->table('BITACORA', function (Blueprint $toTable): void {
            if (Schema::connection('mysqlNegocio')->hasColumn('BITACORA', 'Motivo')) {
                $toTable->dropColumn('Motivo');
            }
        });
    }
};

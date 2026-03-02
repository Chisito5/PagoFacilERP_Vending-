<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::connection('mysqlNegocio')->hasTable('PERMISO')) {
            Schema::connection('mysqlNegocio')->create('PERMISO', function (Blueprint $toTable): void {
                $toTable->increments('Permiso');
                $toTable->string('CodigoPermiso', 120)->unique('UqPermisoCodigo');
                $toTable->string('NombrePermiso', 120);
                $toTable->string('Descripcion', 255)->nullable();
                $toTable->integer('Estado');
                $toTable->integer('Usr')->default(0);
                $toTable->date('UsrFecha');
                $toTable->string('UsrHora', 8);

                $toTable->index(['Estado'], 'IxPermisoEstado');
            });
        }

        if (!Schema::connection('mysqlNegocio')->hasTable('ROLPERMISO')) {
            Schema::connection('mysqlNegocio')->create('ROLPERMISO', function (Blueprint $toTable): void {
                $toTable->increments('RolPermiso');
                $toTable->integer('Rol');
                $toTable->integer('Permiso');
                $toTable->integer('Estado');
                $toTable->integer('Usr')->default(0);
                $toTable->date('UsrFecha');
                $toTable->string('UsrHora', 8);

                $toTable->unique(['Rol', 'Permiso'], 'UqRolPermiso');
                $toTable->index(['Estado'], 'IxRolPermisoEstado');
            });
        }
    }

    public function down(): void
    {
        if (Schema::connection('mysqlNegocio')->hasTable('ROLPERMISO')) {
            Schema::connection('mysqlNegocio')->drop('ROLPERMISO');
        }

        if (Schema::connection('mysqlNegocio')->hasTable('PERMISO')) {
            Schema::connection('mysqlNegocio')->drop('PERMISO');
        }
    }
};

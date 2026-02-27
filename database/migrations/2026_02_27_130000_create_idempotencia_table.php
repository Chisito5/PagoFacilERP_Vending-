<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::connection('mysqlNegocio')->hasTable('IDEMPOTENCIA')) {
            return;
        }

        Schema::connection('mysqlNegocio')->create('IDEMPOTENCIA', function (Blueprint $table) {
            $table->bigIncrements('Idempotencia');
            $table->string('Llave', 120);
            $table->string('Ruta', 180);
            $table->string('Metodo', 10)->default('POST');
            $table->char('HashBody', 64);
            $table->integer('CodigoHttp')->nullable();
            $table->longText('RespuestaJson')->nullable();
            $table->boolean('Procesado')->default(false);
            $table->dateTime('CreadoEn');
            $table->integer('Usr')->default(0);
            $table->date('UsrFecha');
            $table->string('UsrHora', 8);

            $table->unique(['Llave', 'Metodo', 'Ruta'], 'UqIdempotenciaLlaveMetodoRuta');
            $table->index('Llave', 'IxIdempotenciaLlave');
            $table->index('CreadoEn', 'IxIdempotenciaCreadoEn');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::connection('mysqlNegocio')->hasTable('IDEMPOTENCIA')) {
            Schema::connection('mysqlNegocio')->drop('IDEMPOTENCIA');
        }
    }
};

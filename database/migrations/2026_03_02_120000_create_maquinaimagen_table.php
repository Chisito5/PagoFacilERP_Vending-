<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    private string $pcConexion = 'mysqlNegocio';

    public function up(): void
    {
        if (Schema::connection($this->pcConexion)->hasTable('MAQUINAIMAGEN')) {
            return;
        }

        Schema::connection($this->pcConexion)->create('MAQUINAIMAGEN', function (Blueprint $toTable): void {
            $toTable->integer('MaquinaImagen', true);
            $toTable->integer('Maquina');
            $toTable->string('TipoFoto', 40)->default('GENERAL');
            $toTable->string('RutaImagen', 255);
            $toTable->integer('Orden')->default(1);
            $toTable->string('Observacion', 255)->nullable();
            $toTable->integer('Estado');
            $toTable->integer('Usr')->default(0);
            $toTable->date('UsrFecha');
            $toTable->string('UsrHora', 8);

            $toTable->index(['Maquina', 'Estado', 'Orden'], 'IX_MAQUINAIMAGEN_MAQUINA_ESTADO_ORDEN');
        });

        DB::connection($this->pcConexion)->statement(
            'ALTER TABLE MAQUINAIMAGEN ADD CONSTRAINT FK_MAQUINAIMAGEN_MAQUINA FOREIGN KEY (Maquina) REFERENCES MAQUINA(Maquina)'
        );
        DB::connection($this->pcConexion)->statement(
            'ALTER TABLE MAQUINAIMAGEN ADD CONSTRAINT FK_MAQUINAIMAGEN_ESTADO FOREIGN KEY (Estado) REFERENCES ESTADO(Estado)'
        );
    }

    public function down(): void
    {
        Schema::connection($this->pcConexion)->dropIfExists('MAQUINAIMAGEN');
    }
};


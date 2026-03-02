<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::connection('mysqlNegocio')->hasTable('SESIONAPI')) {
            return;
        }

        Schema::connection('mysqlNegocio')->create('SESIONAPI', function (Blueprint $toTable): void {
            $toTable->increments('SesionApi');
            $toTable->integer('Usuario');
            $toTable->char('HashTokenAcceso', 64)->unique('UqSesionApiHashAcceso');
            $toTable->char('HashTokenRefresco', 64)->unique('UqSesionApiHashRefresco');
            $toTable->dateTime('ExpiraAccesoEn');
            $toTable->dateTime('ExpiraRefrescoEn');
            $toTable->dateTime('FechaHoraRevocacion')->nullable();
            $toTable->string('Ip', 45)->nullable();
            $toTable->string('Agente', 255)->nullable();
            $toTable->dateTime('UltimoUsoEn')->nullable();
            $toTable->integer('Estado');
            $toTable->integer('Usr')->default(0);
            $toTable->date('UsrFecha');
            $toTable->string('UsrHora', 8);
            $toTable->dateTime('CreadoEn')->useCurrent();

            $toTable->index(['Usuario', 'Estado'], 'IxSesionApiUsuarioEstado');
            $toTable->index(['ExpiraAccesoEn'], 'IxSesionApiExpiraAcceso');
            $toTable->index(['ExpiraRefrescoEn'], 'IxSesionApiExpiraRefresco');

            $toTable->foreign('Usuario', 'FkSesionApiUsuario')->references('Usuario')->on('USUARIO');
            $toTable->foreign('Estado', 'FkSesionApiEstado')->references('Estado')->on('ESTADO');
        });
    }

    public function down(): void
    {
        if (!Schema::connection('mysqlNegocio')->hasTable('SESIONAPI')) {
            return;
        }

        Schema::connection('mysqlNegocio')->drop('SESIONAPI');
    }
};

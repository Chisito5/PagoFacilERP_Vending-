<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::connection('mysqlNegocio')->dropIfExists('MAQUINAESTADOOPERATIVOHISTORIAL');
        Schema::connection('mysqlNegocio')->dropIfExists('MAQUINAESTADOOPERATIVO');
        Schema::connection('mysqlNegocio')->dropIfExists('IOTEVENTOINTENTO');
        Schema::connection('mysqlNegocio')->dropIfExists('IOTEVENTO');
        Schema::connection('mysqlNegocio')->dropIfExists('REPORTEARCHIVO');
        Schema::connection('mysqlNegocio')->dropIfExists('REPORTEGENERADO');
        Schema::connection('mysqlNegocio')->dropIfExists('MERMAEVIDENCIA');
        Schema::connection('mysqlNegocio')->dropIfExists('ANUNCIOPRODUCTO');
        Schema::connection('mysqlNegocio')->dropIfExists('REGLAALERTA');

        Schema::connection('mysqlNegocio')->create('REGLAALERTA', function (Blueprint $toTable): void {
            $toTable->integer('ReglaAlerta', true);
            $toTable->integer('Empresa')->nullable();
            $toTable->integer('Maquina')->nullable();
            $toTable->integer('Celda')->nullable();
            $toTable->integer('TipoAlerta')->nullable();
            $toTable->integer('TipoTelemetria')->nullable();
            $toTable->string('TipoRegla', 40);
            $toTable->decimal('UmbralMinimo', 18, 6)->nullable();
            $toTable->decimal('UmbralMaximo', 18, 6)->nullable();
            $toTable->unsignedTinyInteger('Prioridad')->default(1);
            $toTable->string('MensajeRegla', 255)->nullable();
            $toTable->integer('Estado');
            $toTable->integer('Usr')->default(0);
            $toTable->date('UsrFecha');
            $toTable->string('UsrHora', 8);

            $toTable->index(['Empresa', 'Estado'], 'IX_REGLAALERTA_EMPRESA_ESTADO');
            $toTable->index(['Maquina', 'Estado'], 'IX_REGLAALERTA_MAQUINA_ESTADO');
            $toTable->index(['TipoRegla', 'Estado'], 'IX_REGLAALERTA_TIPOREGLA_ESTADO');
        });

        Schema::connection('mysqlNegocio')->create('ANUNCIOPRODUCTO', function (Blueprint $toTable): void {
            $toTable->integer('AnuncioProducto', true);
            $toTable->integer('Anuncio');
            $toTable->integer('Producto');
            $toTable->integer('Estado');
            $toTable->integer('Usr')->default(0);
            $toTable->date('UsrFecha');
            $toTable->string('UsrHora', 8);

            $toTable->unique(['Anuncio', 'Producto'], 'UQ_ANUNCIOPRODUCTO');
            $toTable->index(['Producto', 'Estado'], 'IX_ANUNCIOPRODUCTO_PRODUCTO_ESTADO');
        });

        Schema::connection('mysqlNegocio')->create('MERMAEVIDENCIA', function (Blueprint $toTable): void {
            $toTable->integer('MermaEvidencia', true);
            $toTable->integer('Merma');
            $toTable->string('NombreArchivo', 180);
            $toTable->string('RutaArchivo', 255);
            $toTable->string('MimeArchivo', 80)->nullable();
            $toTable->unsignedBigInteger('TamanoBytes')->default(0);
            $toTable->integer('Estado');
            $toTable->integer('Usr')->default(0);
            $toTable->date('UsrFecha');
            $toTable->string('UsrHora', 8);

            $toTable->index(['Merma', 'Estado'], 'IX_MERMAEVIDENCIA_MERMA_ESTADO');
        });

        Schema::connection('mysqlNegocio')->create('REPORTEGENERADO', function (Blueprint $toTable): void {
            $toTable->integer('ReporteGenerado', true);
            $toTable->integer('Empresa')->nullable();
            $toTable->integer('UsuarioSolicitante');
            $toTable->string('TipoReporte', 80);
            $toTable->string('Formato', 10);
            $toTable->json('Filtros')->nullable();
            $toTable->integer('Estado');
            $toTable->dateTime('FechaSolicitud');
            $toTable->dateTime('FechaInicioProceso')->nullable();
            $toTable->dateTime('FechaFinProceso')->nullable();
            $toTable->dateTime('FechaExpiracion')->nullable();
            $toTable->string('MensajeEstado', 255)->nullable();
            $toTable->integer('Usr')->default(0);
            $toTable->date('UsrFecha');
            $toTable->string('UsrHora', 8);

            $toTable->index(['UsuarioSolicitante', 'Estado'], 'IX_REPORTE_USUARIO_ESTADO');
            $toTable->index(['Empresa', 'Estado'], 'IX_REPORTE_EMPRESA_ESTADO');
            $toTable->index('FechaSolicitud', 'IX_REPORTE_FECHA_SOLICITUD');
        });

        Schema::connection('mysqlNegocio')->create('REPORTEARCHIVO', function (Blueprint $toTable): void {
            $toTable->integer('ReporteArchivo', true);
            $toTable->integer('ReporteGenerado');
            $toTable->string('NombreArchivo', 180);
            $toTable->string('RutaArchivo', 255);
            $toTable->string('MimeArchivo', 80)->nullable();
            $toTable->unsignedBigInteger('TamanoBytes')->default(0);
            $toTable->integer('Estado');
            $toTable->integer('Usr')->default(0);
            $toTable->date('UsrFecha');
            $toTable->string('UsrHora', 8);

            $toTable->index(['ReporteGenerado', 'Estado'], 'IX_REPORTEARCHIVO_REPORTE_ESTADO');
        });

        Schema::connection('mysqlNegocio')->create('IOTEVENTO', function (Blueprint $toTable): void {
            $toTable->integer('IotEvento', true);
            $toTable->string('Origen', 120);
            $toTable->string('EventId', 120);
            $toTable->dateTime('FechaEvento');
            $toTable->json('Payload');
            $toTable->string('Firma', 255)->nullable();
            $toTable->integer('IntentosProcesamiento')->default(0);
            $toTable->integer('Estado');
            $toTable->dateTime('FechaRecepcion');
            $toTable->dateTime('FechaProcesamiento')->nullable();
            $toTable->string('ErrorUltimo', 255)->nullable();
            $toTable->integer('Usr')->default(0);
            $toTable->date('UsrFecha');
            $toTable->string('UsrHora', 8);

            $toTable->unique(['Origen', 'EventId'], 'UQ_IOTEVENTO_ORIGEN_EVENTID');
            $toTable->index(['Estado', 'FechaRecepcion'], 'IX_IOTEVENTO_ESTADO_FECHA');
        });

        Schema::connection('mysqlNegocio')->create('IOTEVENTOINTENTO', function (Blueprint $toTable): void {
            $toTable->integer('IotEventoIntento', true);
            $toTable->integer('IotEvento');
            $toTable->integer('NumeroIntento');
            $toTable->dateTime('FechaIntento');
            $toTable->integer('Estado');
            $toTable->string('Mensaje', 255)->nullable();
            $toTable->integer('Usr')->default(0);
            $toTable->date('UsrFecha');
            $toTable->string('UsrHora', 8);

            $toTable->index(['IotEvento', 'NumeroIntento'], 'IX_IOTEVENTOINTENTO_EVENTO_INTENTO');
        });

        Schema::connection('mysqlNegocio')->create('MAQUINAESTADOOPERATIVO', function (Blueprint $toTable): void {
            $toTable->integer('MaquinaEstadoOperativo', true);
            $toTable->integer('Maquina');
            $toTable->string('EstadoOperativo', 40);
            $toTable->string('Motivo', 255)->nullable();
            $toTable->integer('Estado');
            $toTable->integer('Usr')->default(0);
            $toTable->date('UsrFecha');
            $toTable->string('UsrHora', 8);

            $toTable->unique(['Maquina'], 'UQ_MAQUINAESTADOOPERATIVO_MAQUINA');
        });

        Schema::connection('mysqlNegocio')->create('MAQUINAESTADOOPERATIVOHISTORIAL', function (Blueprint $toTable): void {
            $toTable->integer('MaquinaEstadoOperativoHistorial', true);
            $toTable->integer('Maquina');
            $toTable->string('EstadoOperativoAnterior', 40)->nullable();
            $toTable->string('EstadoOperativoNuevo', 40);
            $toTable->string('Motivo', 255)->nullable();
            $toTable->dateTime('FechaCambio');
            $toTable->integer('Estado');
            $toTable->integer('Usr')->default(0);
            $toTable->date('UsrFecha');
            $toTable->string('UsrHora', 8);

            $toTable->index(['Maquina', 'FechaCambio'], 'IX_MAQESTHIST_MAQ_FECHA');
        });

        DB::connection('mysqlNegocio')->statement('ALTER TABLE ANUNCIOPRODUCTO ADD CONSTRAINT FK_ANUNCIOPRODUCTO_ANUNCIO FOREIGN KEY (Anuncio) REFERENCES ANUNCIO(Anuncio)');
        DB::connection('mysqlNegocio')->statement('ALTER TABLE ANUNCIOPRODUCTO ADD CONSTRAINT FK_ANUNCIOPRODUCTO_PRODUCTO FOREIGN KEY (Producto) REFERENCES PRODUCTO(Producto)');
        DB::connection('mysqlNegocio')->statement('ALTER TABLE ANUNCIOPRODUCTO ADD CONSTRAINT FK_ANUNCIOPRODUCTO_ESTADO FOREIGN KEY (Estado) REFERENCES ESTADO(Estado)');

        DB::connection('mysqlNegocio')->statement('ALTER TABLE MERMAEVIDENCIA ADD CONSTRAINT FK_MERMAEVIDENCIA_MERMA FOREIGN KEY (Merma) REFERENCES MERMA(Merma)');
        DB::connection('mysqlNegocio')->statement('ALTER TABLE MERMAEVIDENCIA ADD CONSTRAINT FK_MERMAEVIDENCIA_ESTADO FOREIGN KEY (Estado) REFERENCES ESTADO(Estado)');

        DB::connection('mysqlNegocio')->statement('ALTER TABLE REPORTEGENERADO ADD CONSTRAINT FK_REPORTEGEN_USUARIO FOREIGN KEY (UsuarioSolicitante) REFERENCES USUARIO(Usuario)');
        DB::connection('mysqlNegocio')->statement('ALTER TABLE REPORTEGENERADO ADD CONSTRAINT FK_REPORTEGEN_EMPRESA FOREIGN KEY (Empresa) REFERENCES EMPRESA(Empresa)');
        DB::connection('mysqlNegocio')->statement('ALTER TABLE REPORTEGENERADO ADD CONSTRAINT FK_REPORTEGEN_ESTADO FOREIGN KEY (Estado) REFERENCES ESTADO(Estado)');

        DB::connection('mysqlNegocio')->statement('ALTER TABLE REPORTEARCHIVO ADD CONSTRAINT FK_REPORTEARCHIVO_REPORTE FOREIGN KEY (ReporteGenerado) REFERENCES REPORTEGENERADO(ReporteGenerado)');
        DB::connection('mysqlNegocio')->statement('ALTER TABLE REPORTEARCHIVO ADD CONSTRAINT FK_REPORTEARCHIVO_ESTADO FOREIGN KEY (Estado) REFERENCES ESTADO(Estado)');

        DB::connection('mysqlNegocio')->statement('ALTER TABLE IOTEVENTO ADD CONSTRAINT FK_IOTEVENTO_ESTADO FOREIGN KEY (Estado) REFERENCES ESTADO(Estado)');
        DB::connection('mysqlNegocio')->statement('ALTER TABLE IOTEVENTOINTENTO ADD CONSTRAINT FK_IOTEVENTOINTENTO_EVENTO FOREIGN KEY (IotEvento) REFERENCES IOTEVENTO(IotEvento)');
        DB::connection('mysqlNegocio')->statement('ALTER TABLE IOTEVENTOINTENTO ADD CONSTRAINT FK_IOTEVENTOINTENTO_ESTADO FOREIGN KEY (Estado) REFERENCES ESTADO(Estado)');

        DB::connection('mysqlNegocio')->statement('ALTER TABLE MAQUINAESTADOOPERATIVO ADD CONSTRAINT FK_MAQEST_MAQ FOREIGN KEY (Maquina) REFERENCES MAQUINA(Maquina)');
        DB::connection('mysqlNegocio')->statement('ALTER TABLE MAQUINAESTADOOPERATIVO ADD CONSTRAINT FK_MAQEST_ESTADO FOREIGN KEY (Estado) REFERENCES ESTADO(Estado)');

        DB::connection('mysqlNegocio')->statement('ALTER TABLE MAQUINAESTADOOPERATIVOHISTORIAL ADD CONSTRAINT FK_MAQESTHIST_MAQ FOREIGN KEY (Maquina) REFERENCES MAQUINA(Maquina)');
        DB::connection('mysqlNegocio')->statement('ALTER TABLE MAQUINAESTADOOPERATIVOHISTORIAL ADD CONSTRAINT FK_MAQESTHIST_ESTADO FOREIGN KEY (Estado) REFERENCES ESTADO(Estado)');

        DB::connection('mysqlNegocio')->statement('ALTER TABLE REGLAALERTA ADD CONSTRAINT FK_REGLAALERTA_EMPRESA FOREIGN KEY (Empresa) REFERENCES EMPRESA(Empresa)');
        DB::connection('mysqlNegocio')->statement('ALTER TABLE REGLAALERTA ADD CONSTRAINT FK_REGLAALERTA_MAQUINA FOREIGN KEY (Maquina) REFERENCES MAQUINA(Maquina)');
        DB::connection('mysqlNegocio')->statement('ALTER TABLE REGLAALERTA ADD CONSTRAINT FK_REGLAALERTA_CELDA FOREIGN KEY (Celda) REFERENCES CELDA(Celda)');
        DB::connection('mysqlNegocio')->statement('ALTER TABLE REGLAALERTA ADD CONSTRAINT FK_REGLAALERTA_TIPOALERTA FOREIGN KEY (TipoAlerta) REFERENCES TIPOALERTA(TipoAlerta)');
        DB::connection('mysqlNegocio')->statement('ALTER TABLE REGLAALERTA ADD CONSTRAINT FK_REGLAALERTA_TIPOTELEMETRIA FOREIGN KEY (TipoTelemetria) REFERENCES TIPOTELEMETRIA(TipoTelemetria)');
        DB::connection('mysqlNegocio')->statement('ALTER TABLE REGLAALERTA ADD CONSTRAINT FK_REGLAALERTA_ESTADO FOREIGN KEY (Estado) REFERENCES ESTADO(Estado)');
    }

    public function down(): void
    {
        Schema::connection('mysqlNegocio')->dropIfExists('MAQUINAESTADOOPERATIVOHISTORIAL');
        Schema::connection('mysqlNegocio')->dropIfExists('MAQUINAESTADOOPERATIVO');
        Schema::connection('mysqlNegocio')->dropIfExists('IOTEVENTOINTENTO');
        Schema::connection('mysqlNegocio')->dropIfExists('IOTEVENTO');
        Schema::connection('mysqlNegocio')->dropIfExists('REPORTEARCHIVO');
        Schema::connection('mysqlNegocio')->dropIfExists('REPORTEGENERADO');
        Schema::connection('mysqlNegocio')->dropIfExists('MERMAEVIDENCIA');
        Schema::connection('mysqlNegocio')->dropIfExists('ANUNCIOPRODUCTO');
        Schema::connection('mysqlNegocio')->dropIfExists('REGLAALERTA');
    }
};


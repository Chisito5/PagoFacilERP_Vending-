<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    private string $pcConexion = 'mysqlNegocio';

    public function up(): void
    {
        $this->crearCatalogos();
        $this->alterarTablas();
        $this->sembrarCatalogos();
        $this->aplicarBackfill();
        $this->crearRestricciones();
    }

    public function down(): void
    {
        $this->eliminarRestricciones();

        if (Schema::connection($this->pcConexion)->hasColumn('MAQUINA', 'ConsumoKwhMensual')) {
            Schema::connection($this->pcConexion)->table('MAQUINA', function (Blueprint $toTable): void {
                $toTable->dropColumn('ConsumoKwhMensual');
            });
        }
        if (Schema::connection($this->pcConexion)->hasColumn('MAQUINA', 'TipoInternet')) {
            Schema::connection($this->pcConexion)->table('MAQUINA', function (Blueprint $toTable): void {
                $toTable->dropColumn('TipoInternet');
            });
        }
        if (Schema::connection($this->pcConexion)->hasColumn('UBICACION', 'TipoLugarInstalacion')) {
            Schema::connection($this->pcConexion)->table('UBICACION', function (Blueprint $toTable): void {
                $toTable->dropColumn('TipoLugarInstalacion');
            });
        }

        Schema::connection($this->pcConexion)->dropIfExists('TIPOLUGARINSTALACION');
        Schema::connection($this->pcConexion)->dropIfExists('TIPOINTERNET');
    }

    private function crearCatalogos(): void
    {
        if (!Schema::connection($this->pcConexion)->hasTable('TIPOINTERNET')) {
            Schema::connection($this->pcConexion)->create('TIPOINTERNET', function (Blueprint $toTable): void {
                $toTable->integer('TipoInternet', true);
                $toTable->string('CodigoTipoInternet', 40);
                $toTable->string('NombreTipoInternet', 80);
                $toTable->string('Descripcion', 255)->nullable();
                $toTable->integer('Estado');
                $toTable->integer('Usr')->default(0);
                $toTable->date('UsrFecha');
                $toTable->string('UsrHora', 8);

                $toTable->unique(['CodigoTipoInternet'], 'UQ_TIPOINTERNET_CODIGO');
                $toTable->index(['Estado'], 'IX_TIPOINTERNET_ESTADO');
            });
        }

        if (!Schema::connection($this->pcConexion)->hasTable('TIPOLUGARINSTALACION')) {
            Schema::connection($this->pcConexion)->create('TIPOLUGARINSTALACION', function (Blueprint $toTable): void {
                $toTable->integer('TipoLugarInstalacion', true);
                $toTable->string('CodigoTipoLugar', 50);
                $toTable->string('NombreTipoLugar', 100);
                $toTable->string('Descripcion', 255)->nullable();
                $toTable->integer('Estado');
                $toTable->integer('Usr')->default(0);
                $toTable->date('UsrFecha');
                $toTable->string('UsrHora', 8);

                $toTable->unique(['CodigoTipoLugar'], 'UQ_TIPOLUGAR_CODIGO');
                $toTable->index(['Estado'], 'IX_TIPOLUGAR_ESTADO');
            });
        }
    }

    private function alterarTablas(): void
    {
        if (!Schema::connection($this->pcConexion)->hasColumn('MAQUINA', 'TipoInternet')) {
            Schema::connection($this->pcConexion)->table('MAQUINA', function (Blueprint $toTable): void {
                $toTable->integer('TipoInternet')->nullable()->after('IdentificadorConexion');
            });
        }

        if (!Schema::connection($this->pcConexion)->hasColumn('MAQUINA', 'ConsumoKwhMensual')) {
            Schema::connection($this->pcConexion)->table('MAQUINA', function (Blueprint $toTable): void {
                $toTable->decimal('ConsumoKwhMensual', 10, 2)->default(0)->after('TipoInternet');
            });
        }

        if (!Schema::connection($this->pcConexion)->hasColumn('UBICACION', 'TipoLugarInstalacion')) {
            Schema::connection($this->pcConexion)->table('UBICACION', function (Blueprint $toTable): void {
                $toTable->integer('TipoLugarInstalacion')->nullable()->after('NombreUbicacion');
            });
        }
    }

    private function sembrarCatalogos(): void
    {
        $tnEstadoActivo = $this->obtenerEstado('GENERAL', 1, 1);
        $tdAhora = now();
        $tcFecha = $tdAhora->toDateString();
        $tcHora = $tdAhora->format('H:i:s');

        $laTiposInternet = [
            ['CodigoTipoInternet' => 'ETHERNET', 'NombreTipoInternet' => 'Ethernet', 'Descripcion' => 'Conexion por cable de red'],
            ['CodigoTipoInternet' => 'ANTENA', 'NombreTipoInternet' => 'Antena', 'Descripcion' => 'Conexion inalambrica por antena externa'],
            ['CodigoTipoInternet' => 'CHIP_RED', 'NombreTipoInternet' => 'Chip de red', 'Descripcion' => 'Conexion por SIM o chip de datos'],
            ['CodigoTipoInternet' => 'WIFI', 'NombreTipoInternet' => 'Wifi', 'Descripcion' => 'Conexion inalambrica wifi'],
            ['CodigoTipoInternet' => 'OTRO', 'NombreTipoInternet' => 'Otro', 'Descripcion' => 'Otro tipo de conexion'],
        ];

        foreach ($laTiposInternet as $laFila) {
            DB::connection($this->pcConexion)->table('TIPOINTERNET')->updateOrInsert(
                ['CodigoTipoInternet' => $laFila['CodigoTipoInternet']],
                [
                    'NombreTipoInternet' => $laFila['NombreTipoInternet'],
                    'Descripcion' => $laFila['Descripcion'],
                    'Estado' => $tnEstadoActivo,
                    'Usr' => 0,
                    'UsrFecha' => $tcFecha,
                    'UsrHora' => $tcHora,
                ]
            );
        }

        $laTiposLugar = [
            ['CodigoTipoLugar' => 'OFICINA', 'NombreTipoLugar' => 'Oficina', 'Descripcion' => 'Instalacion en oficina'],
            ['CodigoTipoLugar' => 'SUPERMERCADO', 'NombreTipoLugar' => 'Supermercado', 'Descripcion' => 'Instalacion en supermercado'],
            ['CodigoTipoLugar' => 'CAMPO_FUTBOL', 'NombreTipoLugar' => 'Campo de futbol', 'Descripcion' => 'Instalacion en complejo deportivo o campo'],
            ['CodigoTipoLugar' => 'CENTRO_COMERCIAL', 'NombreTipoLugar' => 'Centro comercial', 'Descripcion' => 'Instalacion en centro comercial'],
            ['CodigoTipoLugar' => 'OTRO', 'NombreTipoLugar' => 'Otro', 'Descripcion' => 'Otro tipo de lugar'],
        ];

        foreach ($laTiposLugar as $laFila) {
            DB::connection($this->pcConexion)->table('TIPOLUGARINSTALACION')->updateOrInsert(
                ['CodigoTipoLugar' => $laFila['CodigoTipoLugar']],
                [
                    'NombreTipoLugar' => $laFila['NombreTipoLugar'],
                    'Descripcion' => $laFila['Descripcion'],
                    'Estado' => $tnEstadoActivo,
                    'Usr' => 0,
                    'UsrFecha' => $tcFecha,
                    'UsrHora' => $tcHora,
                ]
            );
        }
    }

    private function aplicarBackfill(): void
    {
        $tdAhora = now();
        $tcFecha = $tdAhora->toDateString();
        $tcHora = $tdAhora->format('H:i:s');

        $tnTipoInternetOtro = (int)(DB::connection($this->pcConexion)
            ->table('TIPOINTERNET')
            ->where('CodigoTipoInternet', 'OTRO')
            ->value('TipoInternet') ?? 0);

        $tnTipoLugarOtro = (int)(DB::connection($this->pcConexion)
            ->table('TIPOLUGARINSTALACION')
            ->where('CodigoTipoLugar', 'OTRO')
            ->value('TipoLugarInstalacion') ?? 0);

        DB::connection($this->pcConexion)
            ->table('MAQUINA')
            ->whereNull('ConsumoKwhMensual')
            ->update([
                'ConsumoKwhMensual' => 0,
                'Usr' => 0,
                'UsrFecha' => $tcFecha,
                'UsrHora' => $tcHora,
            ]);

        if ($tnTipoInternetOtro > 0) {
            DB::connection($this->pcConexion)
                ->table('MAQUINA')
                ->whereNull('TipoInternet')
                ->update([
                    'TipoInternet' => $tnTipoInternetOtro,
                    'Usr' => 0,
                    'UsrFecha' => $tcFecha,
                    'UsrHora' => $tcHora,
                ]);
        }

        if ($tnTipoLugarOtro > 0) {
            DB::connection($this->pcConexion)
                ->table('UBICACION')
                ->whereNull('TipoLugarInstalacion')
                ->update([
                    'TipoLugarInstalacion' => $tnTipoLugarOtro,
                    'Usr' => 0,
                    'UsrFecha' => $tcFecha,
                    'UsrHora' => $tcHora,
                ]);
        }
    }

    private function crearRestricciones(): void
    {
        if (!$this->existeRestriccion('TIPOINTERNET', 'FK_TIPOINTERNET_ESTADO')) {
            DB::connection($this->pcConexion)->statement('ALTER TABLE TIPOINTERNET ADD CONSTRAINT FK_TIPOINTERNET_ESTADO FOREIGN KEY (Estado) REFERENCES ESTADO(Estado)');
        }

        if (!$this->existeRestriccion('TIPOLUGARINSTALACION', 'FK_TIPOLUGAR_ESTADO')) {
            DB::connection($this->pcConexion)->statement('ALTER TABLE TIPOLUGARINSTALACION ADD CONSTRAINT FK_TIPOLUGAR_ESTADO FOREIGN KEY (Estado) REFERENCES ESTADO(Estado)');
        }

        if (!$this->existeRestriccion('MAQUINA', 'FK_MAQUINA_TIPOINTERNET')) {
            DB::connection($this->pcConexion)->statement('ALTER TABLE MAQUINA ADD CONSTRAINT FK_MAQUINA_TIPOINTERNET FOREIGN KEY (TipoInternet) REFERENCES TIPOINTERNET(TipoInternet)');
        }

        if (!$this->existeRestriccion('UBICACION', 'FK_UBICACION_TIPOLUGAR')) {
            DB::connection($this->pcConexion)->statement('ALTER TABLE UBICACION ADD CONSTRAINT FK_UBICACION_TIPOLUGAR FOREIGN KEY (TipoLugarInstalacion) REFERENCES TIPOLUGARINSTALACION(TipoLugarInstalacion)');
        }
    }

    private function eliminarRestricciones(): void
    {
        if ($this->existeRestriccion('UBICACION', 'FK_UBICACION_TIPOLUGAR')) {
            DB::connection($this->pcConexion)->statement('ALTER TABLE UBICACION DROP FOREIGN KEY FK_UBICACION_TIPOLUGAR');
        }
        if ($this->existeRestriccion('MAQUINA', 'FK_MAQUINA_TIPOINTERNET')) {
            DB::connection($this->pcConexion)->statement('ALTER TABLE MAQUINA DROP FOREIGN KEY FK_MAQUINA_TIPOINTERNET');
        }
        if ($this->existeRestriccion('TIPOLUGARINSTALACION', 'FK_TIPOLUGAR_ESTADO')) {
            DB::connection($this->pcConexion)->statement('ALTER TABLE TIPOLUGARINSTALACION DROP FOREIGN KEY FK_TIPOLUGAR_ESTADO');
        }
        if ($this->existeRestriccion('TIPOINTERNET', 'FK_TIPOINTERNET_ESTADO')) {
            DB::connection($this->pcConexion)->statement('ALTER TABLE TIPOINTERNET DROP FOREIGN KEY FK_TIPOINTERNET_ESTADO');
        }
    }

    private function existeRestriccion(string $tcTabla, string $tcRestriccion): bool
    {
        $tcBase = DB::connection($this->pcConexion)->getDatabaseName();

        return DB::connection($this->pcConexion)
            ->table('information_schema.table_constraints')
            ->where('table_schema', $tcBase)
            ->where('table_name', $tcTabla)
            ->where('constraint_name', $tcRestriccion)
            ->exists();
    }

    private function obtenerEstado(string $tcEntidad, int $tnCodigoEstado, int $tnFallback): int
    {
        $loEstado = DB::connection($this->pcConexion)
            ->table('ESTADO')
            ->select('Estado')
            ->where('Entidad', $tcEntidad)
            ->where('CodigoEstado', $tnCodigoEstado)
            ->first();

        return $loEstado ? (int)$loEstado->Estado : $tnFallback;
    }
};

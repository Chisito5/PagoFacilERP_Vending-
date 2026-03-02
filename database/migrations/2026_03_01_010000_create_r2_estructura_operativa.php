<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    private string $pcConexion = 'mysqlNegocio';

    public function up(): void
    {
        $this->crearTablasR2();
        $this->alterarTablasExistentes();
        $this->normalizarMatrizCeldas();
    }

    public function down(): void
    {
        Schema::connection($this->pcConexion)->dropIfExists('PRODUCTODISENOCAPA');
        Schema::connection($this->pcConexion)->dropIfExists('PRODUCTODISENO');
        Schema::connection($this->pcConexion)->dropIfExists('CELDACONFLICTO');
        Schema::connection($this->pcConexion)->dropIfExists('CELDAOCUPACIONDETALLE');
        Schema::connection($this->pcConexion)->dropIfExists('CELDAOCUPACION');
        Schema::connection($this->pcConexion)->dropIfExists('MOVIMIENTODEPOSITO');
        Schema::connection($this->pcConexion)->dropIfExists('STOCKDEPOSITO');
        Schema::connection($this->pcConexion)->dropIfExists('DEPOSITO');
    }

    private function crearTablasR2(): void
    {
        $tnEstadoActivo = $this->obtenerEstado('GENERAL', 1, 1);

        if (!Schema::connection($this->pcConexion)->hasTable('DEPOSITO')) {
            Schema::connection($this->pcConexion)->create('DEPOSITO', function (Blueprint $toTable): void {
                $toTable->integer('Deposito', true);
                $toTable->integer('Empresa');
                $toTable->string('NombreDeposito', 120);
                $toTable->string('Descripcion', 255)->nullable();
                $toTable->integer('Estado');
                $toTable->integer('Usr')->default(0);
                $toTable->date('UsrFecha');
                $toTable->string('UsrHora', 8);

                $toTable->unique(['Empresa', 'NombreDeposito'], 'UQ_DEPOSITO_EMPRESA_NOMBRE');
                $toTable->index(['Empresa', 'Estado'], 'IX_DEPOSITO_EMPRESA_ESTADO');
            });

            DB::connection($this->pcConexion)->statement('ALTER TABLE DEPOSITO ADD CONSTRAINT FK_DEPOSITO_EMPRESA FOREIGN KEY (Empresa) REFERENCES EMPRESA(Empresa)');
            DB::connection($this->pcConexion)->statement('ALTER TABLE DEPOSITO ADD CONSTRAINT FK_DEPOSITO_ESTADO FOREIGN KEY (Estado) REFERENCES ESTADO(Estado)');

            $tdAhora = now();
            $laEmpresas = DB::connection($this->pcConexion)
                ->table('EMPRESA')
                ->select('Empresa')
                ->where('Estado', $tnEstadoActivo)
                ->get();

            foreach ($laEmpresas as $loEmpresa) {
                DB::connection($this->pcConexion)->table('DEPOSITO')->insert([
                    'Empresa' => (int)$loEmpresa->Empresa,
                    'NombreDeposito' => 'DEPOSITO PRINCIPAL',
                    'Descripcion' => 'Deposito principal de empresa',
                    'Estado' => $tnEstadoActivo,
                    'Usr' => 0,
                    'UsrFecha' => $tdAhora->toDateString(),
                    'UsrHora' => $tdAhora->format('H:i:s'),
                ]);
            }
        }

        if (!Schema::connection($this->pcConexion)->hasTable('STOCKDEPOSITO')) {
            Schema::connection($this->pcConexion)->create('STOCKDEPOSITO', function (Blueprint $toTable): void {
                $toTable->integer('StockDeposito', true);
                $toTable->integer('Deposito');
                $toTable->integer('Producto');
                $toTable->integer('Lote')->nullable();
                $toTable->integer('CantidadDisponible')->default(0);
                $toTable->integer('CantidadReservada')->default(0);
                $toTable->integer('Estado');
                $toTable->integer('Usr')->default(0);
                $toTable->date('UsrFecha');
                $toTable->string('UsrHora', 8);

                $toTable->unique(['Deposito', 'Producto', 'Lote'], 'UQ_STOCKDEPOSITO');
                $toTable->index(['Deposito', 'Estado'], 'IX_STOCKDEPOSITO_DEPOSITO_ESTADO');
                $toTable->index(['Producto', 'Lote', 'Estado'], 'IX_STOCKDEPOSITO_PRODUCTO_LOTE_ESTADO');
            });

            DB::connection($this->pcConexion)->statement('ALTER TABLE STOCKDEPOSITO ADD CONSTRAINT FK_STOCKDEPOSITO_DEPOSITO FOREIGN KEY (Deposito) REFERENCES DEPOSITO(Deposito)');
            DB::connection($this->pcConexion)->statement('ALTER TABLE STOCKDEPOSITO ADD CONSTRAINT FK_STOCKDEPOSITO_PRODUCTO FOREIGN KEY (Producto) REFERENCES PRODUCTO(Producto)');
            DB::connection($this->pcConexion)->statement('ALTER TABLE STOCKDEPOSITO ADD CONSTRAINT FK_STOCKDEPOSITO_LOTE FOREIGN KEY (Lote) REFERENCES LOTE(Lote)');
            DB::connection($this->pcConexion)->statement('ALTER TABLE STOCKDEPOSITO ADD CONSTRAINT FK_STOCKDEPOSITO_ESTADO FOREIGN KEY (Estado) REFERENCES ESTADO(Estado)');
        }

        if (!Schema::connection($this->pcConexion)->hasTable('MOVIMIENTODEPOSITO')) {
            Schema::connection($this->pcConexion)->create('MOVIMIENTODEPOSITO', function (Blueprint $toTable): void {
                $toTable->bigInteger('MovimientoDeposito', true);
                $toTable->integer('Deposito');
                $toTable->string('TipoMovimiento', 20);
                $toTable->integer('Producto');
                $toTable->integer('Lote')->nullable();
                $toTable->integer('Cantidad');
                $toTable->string('Referencia', 120)->nullable();
                $toTable->string('Observacion', 255)->nullable();
                $toTable->integer('Maquina')->nullable();
                $toTable->integer('Celda')->nullable();
                $toTable->dateTime('FechaHora');
                $toTable->integer('Estado');
                $toTable->integer('Usr')->default(0);
                $toTable->date('UsrFecha');
                $toTable->string('UsrHora', 8);

                $toTable->index(['Deposito', 'FechaHora', 'TipoMovimiento'], 'IX_MOVDEP_DEPOSITO_FECHA_TIPO');
                $toTable->index(['Maquina', 'Celda'], 'IX_MOVDEP_MAQUINA_CELDA');
            });

            DB::connection($this->pcConexion)->statement('ALTER TABLE MOVIMIENTODEPOSITO ADD CONSTRAINT FK_MOVDEPOSITO_DEPOSITO FOREIGN KEY (Deposito) REFERENCES DEPOSITO(Deposito)');
            DB::connection($this->pcConexion)->statement('ALTER TABLE MOVIMIENTODEPOSITO ADD CONSTRAINT FK_MOVDEPOSITO_PRODUCTO FOREIGN KEY (Producto) REFERENCES PRODUCTO(Producto)');
            DB::connection($this->pcConexion)->statement('ALTER TABLE MOVIMIENTODEPOSITO ADD CONSTRAINT FK_MOVDEPOSITO_LOTE FOREIGN KEY (Lote) REFERENCES LOTE(Lote)');
            DB::connection($this->pcConexion)->statement('ALTER TABLE MOVIMIENTODEPOSITO ADD CONSTRAINT FK_MOVDEPOSITO_MAQUINA FOREIGN KEY (Maquina) REFERENCES MAQUINA(Maquina)');
            DB::connection($this->pcConexion)->statement('ALTER TABLE MOVIMIENTODEPOSITO ADD CONSTRAINT FK_MOVDEPOSITO_CELDA FOREIGN KEY (Celda) REFERENCES CELDA(Celda)');
            DB::connection($this->pcConexion)->statement('ALTER TABLE MOVIMIENTODEPOSITO ADD CONSTRAINT FK_MOVDEPOSITO_ESTADO FOREIGN KEY (Estado) REFERENCES ESTADO(Estado)');
        }

        if (!Schema::connection($this->pcConexion)->hasTable('CELDAOCUPACION')) {
            Schema::connection($this->pcConexion)->create('CELDAOCUPACION', function (Blueprint $toTable): void {
                $toTable->integer('CeldaOcupacion', true);
                $toTable->integer('Maquina');
                $toTable->integer('CeldaAncla');
                $toTable->integer('Producto');
                $toTable->integer('Lote')->nullable();
                $toTable->integer('Cantidad')->default(0);
                $toTable->integer('SpanColumnas')->default(1);
                $toTable->integer('SpanFilas')->default(1);
                $toTable->integer('Estado');
                $toTable->integer('Usr')->default(0);
                $toTable->date('UsrFecha');
                $toTable->string('UsrHora', 8);

                $toTable->index(['Maquina', 'Estado', 'CeldaAncla'], 'IX_CELDAOCUPACION_MAQUINA_ESTADO_ANCLA');
            });

            DB::connection($this->pcConexion)->statement('ALTER TABLE CELDAOCUPACION ADD CONSTRAINT FK_CELDAO_MAQUINA FOREIGN KEY (Maquina) REFERENCES MAQUINA(Maquina)');
            DB::connection($this->pcConexion)->statement('ALTER TABLE CELDAOCUPACION ADD CONSTRAINT FK_CELDAO_ANCLA FOREIGN KEY (CeldaAncla) REFERENCES CELDA(Celda)');
            DB::connection($this->pcConexion)->statement('ALTER TABLE CELDAOCUPACION ADD CONSTRAINT FK_CELDAO_PRODUCTO FOREIGN KEY (Producto) REFERENCES PRODUCTO(Producto)');
            DB::connection($this->pcConexion)->statement('ALTER TABLE CELDAOCUPACION ADD CONSTRAINT FK_CELDAO_LOTE FOREIGN KEY (Lote) REFERENCES LOTE(Lote)');
            DB::connection($this->pcConexion)->statement('ALTER TABLE CELDAOCUPACION ADD CONSTRAINT FK_CELDAO_ESTADO FOREIGN KEY (Estado) REFERENCES ESTADO(Estado)');
        }

        if (!Schema::connection($this->pcConexion)->hasTable('CELDAOCUPACIONDETALLE')) {
            Schema::connection($this->pcConexion)->create('CELDAOCUPACIONDETALLE', function (Blueprint $toTable): void {
                $toTable->integer('CeldaOcupacionDetalle', true);
                $toTable->integer('CeldaOcupacion');
                $toTable->integer('Celda');
                $toTable->string('TipoBloqueo', 20);
                $toTable->integer('Estado');
                $toTable->integer('Usr')->default(0);
                $toTable->date('UsrFecha');
                $toTable->string('UsrHora', 8);

                $toTable->unique(['CeldaOcupacion', 'Celda'], 'UQ_CELDAOCUPACIONDETALLE_OCUPACION_CELDA');
                $toTable->index(['Celda', 'Estado'], 'IX_CELDAOCUPACIONDETALLE_CELDA_ESTADO');
            });

            DB::connection($this->pcConexion)->statement('ALTER TABLE CELDAOCUPACIONDETALLE ADD CONSTRAINT FK_CELDAOD_OCUPACION FOREIGN KEY (CeldaOcupacion) REFERENCES CELDAOCUPACION(CeldaOcupacion)');
            DB::connection($this->pcConexion)->statement('ALTER TABLE CELDAOCUPACIONDETALLE ADD CONSTRAINT FK_CELDAOD_CELDA FOREIGN KEY (Celda) REFERENCES CELDA(Celda)');
            DB::connection($this->pcConexion)->statement('ALTER TABLE CELDAOCUPACIONDETALLE ADD CONSTRAINT FK_CELDAOD_ESTADO FOREIGN KEY (Estado) REFERENCES ESTADO(Estado)');
        }

        if (!Schema::connection($this->pcConexion)->hasTable('CELDACONFLICTO')) {
            Schema::connection($this->pcConexion)->create('CELDACONFLICTO', function (Blueprint $toTable): void {
                $toTable->bigInteger('CeldaConflicto', true);
                $toTable->integer('Maquina');
                $toTable->integer('Celda')->nullable();
                $toTable->string('Tipo', 60);
                $toTable->string('Detalle', 255);
                $toTable->dateTime('FechaHora');
                $toTable->integer('Estado');
                $toTable->integer('Usr')->default(0);
                $toTable->date('UsrFecha');
                $toTable->string('UsrHora', 8);

                $toTable->index(['Maquina', 'FechaHora'], 'IX_CELDACONFLICTO_MAQUINA_FECHA');
                $toTable->index(['Estado', 'Tipo'], 'IX_CELDACONFLICTO_ESTADO_TIPO');
            });

            DB::connection($this->pcConexion)->statement('ALTER TABLE CELDACONFLICTO ADD CONSTRAINT FK_CELDACONFLICTO_MAQUINA FOREIGN KEY (Maquina) REFERENCES MAQUINA(Maquina)');
            DB::connection($this->pcConexion)->statement('ALTER TABLE CELDACONFLICTO ADD CONSTRAINT FK_CELDACONFLICTO_CELDA FOREIGN KEY (Celda) REFERENCES CELDA(Celda)');
            DB::connection($this->pcConexion)->statement('ALTER TABLE CELDACONFLICTO ADD CONSTRAINT FK_CELDACONFLICTO_ESTADO FOREIGN KEY (Estado) REFERENCES ESTADO(Estado)');
        }

        if (!Schema::connection($this->pcConexion)->hasTable('PRODUCTODISENO')) {
            Schema::connection($this->pcConexion)->create('PRODUCTODISENO', function (Blueprint $toTable): void {
                $toTable->integer('ProductoDiseno', true);
                $toTable->integer('Producto');
                $toTable->integer('LienzoAncho')->default(900);
                $toTable->integer('LienzoAlto')->default(500);
                $toTable->json('JsonDiseno')->nullable();
                $toTable->integer('Estado');
                $toTable->integer('Usr')->default(0);
                $toTable->date('UsrFecha');
                $toTable->string('UsrHora', 8);

                $toTable->unique(['Producto'], 'UQ_PRODUCTODISENO_PRODUCTO');
                $toTable->index(['Estado'], 'IX_PRODUCTODISENO_ESTADO');
            });

            DB::connection($this->pcConexion)->statement('ALTER TABLE PRODUCTODISENO ADD CONSTRAINT FK_PRODUCTODISENO_PRODUCTO FOREIGN KEY (Producto) REFERENCES PRODUCTO(Producto)');
            DB::connection($this->pcConexion)->statement('ALTER TABLE PRODUCTODISENO ADD CONSTRAINT FK_PRODUCTODISENO_ESTADO FOREIGN KEY (Estado) REFERENCES ESTADO(Estado)');
        }

        if (!Schema::connection($this->pcConexion)->hasTable('PRODUCTODISENOCAPA')) {
            Schema::connection($this->pcConexion)->create('PRODUCTODISENOCAPA', function (Blueprint $toTable): void {
                $toTable->integer('ProductoDisenoCapa', true);
                $toTable->integer('ProductoDiseno');
                $toTable->string('CapaIdExterno', 80);
                $toTable->string('Tipo', 40);
                $toTable->integer('Orden')->default(1);
                $toTable->decimal('X', 12, 2)->default(0);
                $toTable->decimal('Y', 12, 2)->default(0);
                $toTable->decimal('Ancho', 12, 2)->default(0);
                $toTable->decimal('Alto', 12, 2)->default(0);
                $toTable->decimal('Opacidad', 4, 2)->default(1);
                $toTable->decimal('Rotacion', 8, 2)->default(0);
                $toTable->string('Texto', 255)->nullable();
                $toTable->string('Color', 20)->nullable();
                $toTable->string('Fuente', 80)->nullable();
                $toTable->string('Recurso', 255)->nullable();
                $toTable->integer('Estado');
                $toTable->integer('Usr')->default(0);
                $toTable->date('UsrFecha');
                $toTable->string('UsrHora', 8);

                $toTable->index(['ProductoDiseno', 'Orden', 'Estado'], 'IX_PRODUCTODISENOCAPA_DISENO_ORDEN_ESTADO');
            });

            DB::connection($this->pcConexion)->statement('ALTER TABLE PRODUCTODISENOCAPA ADD CONSTRAINT FK_PRODUCTODISENOCAPA_DISENO FOREIGN KEY (ProductoDiseno) REFERENCES PRODUCTODISENO(ProductoDiseno)');
            DB::connection($this->pcConexion)->statement('ALTER TABLE PRODUCTODISENOCAPA ADD CONSTRAINT FK_PRODUCTODISENOCAPA_ESTADO FOREIGN KEY (Estado) REFERENCES ESTADO(Estado)');
        }
    }

    private function alterarTablasExistentes(): void
    {
        if (!Schema::connection($this->pcConexion)->hasColumn('PRODUCTO', 'CodigoProducto')) {
            Schema::connection($this->pcConexion)->table('PRODUCTO', function (Blueprint $toTable): void {
                $toTable->string('CodigoProducto', 60)->nullable()->after('CodigoSku');
            });
        }

        if (!Schema::connection($this->pcConexion)->hasColumn('PRODUCTO', 'Precio')) {
            Schema::connection($this->pcConexion)->table('PRODUCTO', function (Blueprint $toTable): void {
                $toTable->decimal('Precio', 12, 2)->nullable()->after('NombreProducto');
            });
        }

        if (!Schema::connection($this->pcConexion)->hasColumn('PRODUCTO', 'AnchoMm')) {
            Schema::connection($this->pcConexion)->table('PRODUCTO', function (Blueprint $toTable): void {
                $toTable->integer('AnchoMm')->nullable()->after('Precio');
            });
        }

        if (!Schema::connection($this->pcConexion)->hasColumn('PRODUCTO', 'AltoMm')) {
            Schema::connection($this->pcConexion)->table('PRODUCTO', function (Blueprint $toTable): void {
                $toTable->integer('AltoMm')->nullable()->after('AnchoMm');
            });
        }

        if (!Schema::connection($this->pcConexion)->hasColumn('PRODUCTO', 'ProfundidadMm')) {
            Schema::connection($this->pcConexion)->table('PRODUCTO', function (Blueprint $toTable): void {
                $toTable->integer('ProfundidadMm')->nullable()->after('AltoMm');
            });
        }

        if (!Schema::connection($this->pcConexion)->hasColumn('PRODUCTO', 'PesoGr')) {
            Schema::connection($this->pcConexion)->table('PRODUCTO', function (Blueprint $toTable): void {
                $toTable->decimal('PesoGr', 12, 3)->nullable()->after('PesoGramos');
            });
        }

        if (!Schema::connection($this->pcConexion)->hasColumn('PRODUCTO', 'Orientacion')) {
            Schema::connection($this->pcConexion)->table('PRODUCTO', function (Blueprint $toTable): void {
                $toTable->string('Orientacion', 20)->nullable()->after('ProfundidadMm');
            });
        }

        if (!Schema::connection($this->pcConexion)->hasColumn('PRODUCTO', 'PermiteGiro')) {
            Schema::connection($this->pcConexion)->table('PRODUCTO', function (Blueprint $toTable): void {
                $toTable->unsignedTinyInteger('PermiteGiro')->nullable()->after('Orientacion');
            });
        }

        if (!Schema::connection($this->pcConexion)->hasColumn('PRODUCTO', 'UnidadEmpaque')) {
            Schema::connection($this->pcConexion)->table('PRODUCTO', function (Blueprint $toTable): void {
                $toTable->string('UnidadEmpaque', 40)->nullable()->after('PermiteGiro');
            });
        }

        if (!Schema::connection($this->pcConexion)->hasColumn('CELDA', 'AnchoMaximoMm')) {
            Schema::connection($this->pcConexion)->table('CELDA', function (Blueprint $toTable): void {
                $toTable->integer('AnchoMaximoMm')->nullable()->after('CapacidadMaxima');
            });
        }

        if (!Schema::connection($this->pcConexion)->hasColumn('CELDA', 'AltoMaximoMm')) {
            Schema::connection($this->pcConexion)->table('CELDA', function (Blueprint $toTable): void {
                $toTable->integer('AltoMaximoMm')->nullable()->after('AnchoMaximoMm');
            });
        }

        if (!Schema::connection($this->pcConexion)->hasColumn('CELDA', 'ProfundidadMaximaMm')) {
            Schema::connection($this->pcConexion)->table('CELDA', function (Blueprint $toTable): void {
                $toTable->integer('ProfundidadMaximaMm')->nullable()->after('AltoMaximoMm');
            });
        }

        if (!Schema::connection($this->pcConexion)->hasColumn('CELDA', 'PesoMaximoGr')) {
            Schema::connection($this->pcConexion)->table('CELDA', function (Blueprint $toTable): void {
                $toTable->decimal('PesoMaximoGr', 12, 3)->nullable()->after('ProfundidadMaximaMm');
            });
        }

        if (!Schema::connection($this->pcConexion)->hasColumn('CELDA', 'PermiteGiro')) {
            Schema::connection($this->pcConexion)->table('CELDA', function (Blueprint $toTable): void {
                $toTable->unsignedTinyInteger('PermiteGiro')->nullable()->after('PesoMaximoGr');
            });
        }

        if (!Schema::connection($this->pcConexion)->hasColumn('MAQUINA', 'FilasMatriz')) {
            Schema::connection($this->pcConexion)->table('MAQUINA', function (Blueprint $toTable): void {
                $toTable->integer('FilasMatriz')->default(6)->after('UbicacionActual');
            });
        }

        if (!Schema::connection($this->pcConexion)->hasColumn('MAQUINA', 'ColumnasMatriz')) {
            Schema::connection($this->pcConexion)->table('MAQUINA', function (Blueprint $toTable): void {
                $toTable->integer('ColumnasMatriz')->default(9)->after('FilasMatriz');
            });
        }

        $loIndiceCelda = DB::connection($this->pcConexion)
            ->table('information_schema.statistics')
            ->where('table_schema', DB::connection($this->pcConexion)->getDatabaseName())
            ->where('table_name', 'celda')
            ->where('index_name', 'IX_CELDA_MAQUINA_FILA_COLUMNA_ESTADO')
            ->first();
        if (!$loIndiceCelda) {
            Schema::connection($this->pcConexion)->table('CELDA', function (Blueprint $toTable): void {
                $toTable->index(['Maquina', 'Fila', 'Columna', 'Estado'], 'IX_CELDA_MAQUINA_FILA_COLUMNA_ESTADO');
            });
        }
    }

    private function normalizarMatrizCeldas(): void
    {
        $tnEstadoActivo = $this->obtenerEstado('GENERAL', 1, 1);
        $tnEstadoInactivo = $this->obtenerEstado('GENERAL', 2, 2);
        $tdAhora = now();
        $tcFecha = $tdAhora->toDateString();
        $tcHora = $tdAhora->format('H:i:s');

        $laMaquinas = DB::connection($this->pcConexion)->table('MAQUINA')->select('Maquina')->get();
        foreach ($laMaquinas as $loMaquina) {
            $tnMaquina = (int)$loMaquina->Maquina;

            DB::connection($this->pcConexion)
                ->table('MAQUINA')
                ->where('Maquina', $tnMaquina)
                ->update([
                    'FilasMatriz' => 6,
                    'ColumnasMatriz' => 9,
                    'Usr' => 0,
                    'UsrFecha' => $tcFecha,
                    'UsrHora' => $tcHora,
                ]);

            $laActivas = DB::connection($this->pcConexion)
                ->table('CELDA')
                ->where('Maquina', $tnMaquina)
                ->where('Estado', $tnEstadoActivo)
                ->orderBy('Celda')
                ->get();

            $laPorPosicion = [];
            $laDesactivar = [];
            foreach ($laActivas as $loCelda) {
                $tnFila = (int)($loCelda->Fila ?? 0);
                $tnColumna = (int)($loCelda->Columna ?? 0);
                $tcLlave = $tnFila . '-' . $tnColumna;

                if ($tnFila < 1 || $tnFila > 6 || $tnColumna < 1 || $tnColumna > 9) {
                    $laDesactivar[] = (int)$loCelda->Celda;
                    continue;
                }

                if (isset($laPorPosicion[$tcLlave])) {
                    $laDesactivar[] = (int)$loCelda->Celda;
                    continue;
                }

                $laPorPosicion[$tcLlave] = (int)$loCelda->Celda;
            }

            for ($tnFila = 1; $tnFila <= 6; $tnFila++) {
                for ($tnColumna = 1; $tnColumna <= 9; $tnColumna++) {
                    $tcLlave = $tnFila . '-' . $tnColumna;
                    if (isset($laPorPosicion[$tcLlave])) {
                        continue;
                    }

                    $tcCodigo = $this->generarCodigoSeleccion($tnMaquina, $tnFila, $tnColumna);
                    DB::connection($this->pcConexion)->table('CELDA')->insert([
                        'Maquina' => $tnMaquina,
                        'CodigoSeleccion' => $tcCodigo,
                        'Fila' => $tnFila,
                        'Columna' => $tnColumna,
                        'CapacidadMaxima' => 0,
                        'Estado' => $tnEstadoActivo,
                        'Usr' => 0,
                        'UsrFecha' => $tcFecha,
                        'UsrHora' => $tcHora,
                    ]);
                }
            }

            if (count($laDesactivar) > 0) {
                DB::connection($this->pcConexion)
                    ->table('CELDA')
                    ->whereIn('Celda', $laDesactivar)
                    ->update([
                        'Estado' => $tnEstadoInactivo,
                        'Usr' => 0,
                        'UsrFecha' => $tcFecha,
                        'UsrHora' => $tcHora,
                    ]);
            }
        }
    }

    private function generarCodigoSeleccion(int $tnMaquina, int $tnFila, int $tnColumna): string
    {
        $tcLetraFila = chr(64 + $tnFila);
        $tcBase = $tcLetraFila . $tnColumna;
        $tcCodigo = $tcBase;
        $tnSecuencia = 2;

        while (DB::connection($this->pcConexion)
            ->table('CELDA')
            ->where('Maquina', $tnMaquina)
            ->where('CodigoSeleccion', $tcCodigo)
            ->exists()) {
            $tcCodigo = $tcBase . '_' . $tnSecuencia;
            $tnSecuencia++;
        }

        return $tcCodigo;
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

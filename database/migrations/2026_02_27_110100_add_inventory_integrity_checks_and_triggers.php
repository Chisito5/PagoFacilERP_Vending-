<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Checks basicos de no negativos.
        try {
            DB::connection('mysqlNegocio')->statement(
                "ALTER TABLE EXISTENCIACELDA ADD CONSTRAINT CHK_EXISTENCIACELDA_DISP_NONNEG CHECK (CantidadDisponible >= 0)"
            );
        } catch (\Throwable $toEx) {
            // No-op
        }

        try {
            DB::connection('mysqlNegocio')->statement(
                "ALTER TABLE EXISTENCIACELDA ADD CONSTRAINT CHK_EXISTENCIACELDA_RES_NONNEG CHECK (CantidadReservada >= 0)"
            );
        } catch (\Throwable $toEx) {
            // No-op
        }

        DB::connection('mysqlNegocio')->unprepared("DROP TRIGGER IF EXISTS trg_existenciacelda_bi_integridad");
        DB::connection('mysqlNegocio')->unprepared("DROP TRIGGER IF EXISTS trg_existenciacelda_bu_integridad");

        DB::connection('mysqlNegocio')->unprepared("
            CREATE TRIGGER trg_existenciacelda_bi_integridad
            BEFORE INSERT ON EXISTENCIACELDA
            FOR EACH ROW
            BEGIN
                DECLARE vnCapacidadMaxima INT DEFAULT 0;
                DECLARE vnLoteCapacidad INT DEFAULT 0;
                DECLARE vnTotalLote INT DEFAULT 0;

                IF NEW.CantidadDisponible < 0 OR NEW.CantidadReservada < 0 THEN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Integridad inventario: cantidades no pueden ser negativas';
                END IF;

                SELECT CapacidadMaxima
                INTO vnCapacidadMaxima
                FROM CELDA
                WHERE Celda = NEW.Celda;

                IF vnCapacidadMaxima IS NULL OR vnCapacidadMaxima <= 0 THEN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Integridad inventario: capacidad de celda no configurada';
                END IF;

                IF (NEW.CantidadDisponible + NEW.CantidadReservada) > vnCapacidadMaxima THEN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Integridad inventario: capacidad de celda excedida';
                END IF;

                IF NEW.Lote IS NOT NULL THEN
                    SELECT CantidadInicial
                    INTO vnLoteCapacidad
                    FROM LOTE
                    WHERE Lote = NEW.Lote;

                    IF vnLoteCapacidad IS NULL OR vnLoteCapacidad <= 0 THEN
                        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Integridad inventario: lote sin capacidad definida';
                    END IF;

                    SELECT COALESCE(SUM(CantidadDisponible + CantidadReservada), 0)
                    INTO vnTotalLote
                    FROM EXISTENCIACELDA
                    WHERE Lote = NEW.Lote;

                    IF (vnTotalLote + NEW.CantidadDisponible + NEW.CantidadReservada) > vnLoteCapacidad THEN
                        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Integridad inventario: lote insuficiente';
                    END IF;
                END IF;
            END
        ");

        DB::connection('mysqlNegocio')->unprepared("
            CREATE TRIGGER trg_existenciacelda_bu_integridad
            BEFORE UPDATE ON EXISTENCIACELDA
            FOR EACH ROW
            BEGIN
                DECLARE vnCapacidadMaxima INT DEFAULT 0;
                DECLARE vnLoteCapacidad INT DEFAULT 0;
                DECLARE vnTotalLote INT DEFAULT 0;

                IF NEW.CantidadDisponible < 0 OR NEW.CantidadReservada < 0 THEN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Integridad inventario: cantidades no pueden ser negativas';
                END IF;

                SELECT CapacidadMaxima
                INTO vnCapacidadMaxima
                FROM CELDA
                WHERE Celda = NEW.Celda;

                IF vnCapacidadMaxima IS NULL OR vnCapacidadMaxima <= 0 THEN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Integridad inventario: capacidad de celda no configurada';
                END IF;

                IF (NEW.CantidadDisponible + NEW.CantidadReservada) > vnCapacidadMaxima THEN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Integridad inventario: capacidad de celda excedida';
                END IF;

                IF NEW.Lote IS NOT NULL THEN
                    SELECT CantidadInicial
                    INTO vnLoteCapacidad
                    FROM LOTE
                    WHERE Lote = NEW.Lote;

                    IF vnLoteCapacidad IS NULL OR vnLoteCapacidad <= 0 THEN
                        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Integridad inventario: lote sin capacidad definida';
                    END IF;

                    SELECT COALESCE(SUM(CantidadDisponible + CantidadReservada), 0)
                    INTO vnTotalLote
                    FROM EXISTENCIACELDA
                    WHERE Lote = NEW.Lote
                      AND ExistenciaCelda <> OLD.ExistenciaCelda;

                    IF (vnTotalLote + NEW.CantidadDisponible + NEW.CantidadReservada) > vnLoteCapacidad THEN
                        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Integridad inventario: lote insuficiente';
                    END IF;
                END IF;
            END
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::connection('mysqlNegocio')->unprepared("DROP TRIGGER IF EXISTS trg_existenciacelda_bi_integridad");
        DB::connection('mysqlNegocio')->unprepared("DROP TRIGGER IF EXISTS trg_existenciacelda_bu_integridad");

        try {
            DB::connection('mysqlNegocio')->statement(
                "ALTER TABLE EXISTENCIACELDA DROP CHECK CHK_EXISTENCIACELDA_DISP_NONNEG"
            );
        } catch (\Throwable $toEx) {
            // No-op
        }

        try {
            DB::connection('mysqlNegocio')->statement(
                "ALTER TABLE EXISTENCIACELDA DROP CHECK CHK_EXISTENCIACELDA_RES_NONNEG"
            );
        } catch (\Throwable $toEx) {
            // No-op
        }
    }
};

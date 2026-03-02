<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::connection('mysqlNegocio')->transaction(function () {
            $tdAhora = now();
            $tcFecha = $tdAhora->toDateString();
            $tcHora = $tdAhora->format('H:i:s');

            $this->upsertEstado('USUARIO', 1, 'ACTIVO', 'Usuario activo', 1, $tcFecha, $tcHora);
            $this->upsertEstado('USUARIO', 2, 'INACTIVO', 'Usuario inactivo', 2, $tcFecha, $tcHora);

            $this->upsertEstado('MERMA', 1, 'REGISTRADA', 'Merma registrada', 1, $tcFecha, $tcHora);
            $this->upsertEstado('MERMA', 2, 'APROBADA', 'Merma aprobada', 2, $tcFecha, $tcHora);
            $this->upsertEstado('MERMA', 3, 'RECHAZADA', 'Merma rechazada', 3, $tcFecha, $tcHora);

            $this->upsertEstado('ALERTA', 1, 'ABIERTA', 'Alerta abierta', 1, $tcFecha, $tcHora);
            $this->upsertEstado('ALERTA', 2, 'ATENDIDA', 'Alerta atendida', 2, $tcFecha, $tcHora);
            $this->upsertEstado('ALERTA', 3, 'ESCALADA', 'Alerta escalada', 3, $tcFecha, $tcHora);
            $this->upsertEstado('ALERTA', 4, 'CERRADA', 'Alerta cerrada', 4, $tcFecha, $tcHora);

            $this->upsertEstado('ANUNCIO', 1, 'BORRADOR', 'Anuncio borrador', 1, $tcFecha, $tcHora);
            $this->upsertEstado('ANUNCIO', 2, 'PUBLICADO', 'Anuncio publicado', 2, $tcFecha, $tcHora);
            $this->upsertEstado('ANUNCIO', 3, 'DETENIDO', 'Anuncio detenido', 3, $tcFecha, $tcHora);
            $this->upsertEstado('ANUNCIO', 4, 'INACTIVO', 'Anuncio inactivo', 4, $tcFecha, $tcHora);

            $this->upsertEstado('REPORTE', 1, 'PENDIENTE', 'Reporte pendiente', 1, $tcFecha, $tcHora);
            $this->upsertEstado('REPORTE', 2, 'PROCESANDO', 'Reporte procesando', 2, $tcFecha, $tcHora);
            $this->upsertEstado('REPORTE', 3, 'LISTO', 'Reporte listo', 3, $tcFecha, $tcHora);
            $this->upsertEstado('REPORTE', 4, 'ERROR', 'Reporte con error', 4, $tcFecha, $tcHora);
            $this->upsertEstado('REPORTE', 5, 'EXPIRADO', 'Reporte expirado', 5, $tcFecha, $tcHora);

            $this->upsertEstado('IOTEVENTO', 1, 'RECIBIDO', 'Evento recibido', 1, $tcFecha, $tcHora);
            $this->upsertEstado('IOTEVENTO', 2, 'PROCESADO', 'Evento procesado', 2, $tcFecha, $tcHora);
            $this->upsertEstado('IOTEVENTO', 3, 'ERROR', 'Evento error', 3, $tcFecha, $tcHora);

            $this->upsertEstado('MAQUINAOPERATIVO', 1, 'OPERATIVA', 'Maquina operativa', 1, $tcFecha, $tcHora);
            $this->upsertEstado('MAQUINAOPERATIVO', 2, 'MANTENIMIENTO', 'Maquina en mantenimiento', 2, $tcFecha, $tcHora);
            $this->upsertEstado('MAQUINAOPERATIVO', 3, 'FUERA_SERVICIO', 'Maquina fuera de servicio', 3, $tcFecha, $tcHora);

            $tnEstadoActivoGeneral = $this->obtenerEstado('GENERAL', 1);

            $this->upsertRol('Dueno', 'Propietario del ecosistema', $tnEstadoActivoGeneral, $tcFecha, $tcHora);
            $this->upsertRol('Admin', 'Administrador de empresa', $tnEstadoActivoGeneral, $tcFecha, $tcHora);
            $this->upsertRol('Operador', 'Operador de campo', $tnEstadoActivoGeneral, $tcFecha, $tcHora);

            $this->upsertTipoImagen('FRENTE', 'Imagen frontal', $tnEstadoActivoGeneral, $tcFecha, $tcHora);
            $this->upsertTipoImagen('REVERSO', 'Imagen reversa', $tnEstadoActivoGeneral, $tcFecha, $tcHora);
            $this->upsertTipoImagen('ANVERSO', 'Imagen anversa', $tnEstadoActivoGeneral, $tcFecha, $tcHora);
            $this->upsertTipoImagen('DETALLE', 'Imagen detalle', $tnEstadoActivoGeneral, $tcFecha, $tcHora);
        });
    }

    public function down(): void
    {
        // No rollback por tratarse de catalogos transversales.
    }

    private function upsertEstado(string $tcEntidad, int $tnCodigo, string $tcNombre, string $tcDescripcion, int $tnOrden, string $tcFecha, string $tcHora): void
    {
        $loExiste = DB::connection('mysqlNegocio')
            ->table('ESTADO')
            ->where('Entidad', $tcEntidad)
            ->where('CodigoEstado', $tnCodigo)
            ->first();

        if ($loExiste) {
            DB::connection('mysqlNegocio')->table('ESTADO')->where('Estado', (int)$loExiste->Estado)->update([
                'NombreEstado' => $tcNombre,
                'Descripcion' => $tcDescripcion,
                'Orden' => $tnOrden,
                'UsrFecha' => $tcFecha,
                'UsrHora' => $tcHora,
            ]);
            return;
        }

        DB::connection('mysqlNegocio')->table('ESTADO')->insert([
            'Entidad' => $tcEntidad,
            'CodigoEstado' => $tnCodigo,
            'NombreEstado' => $tcNombre,
            'Descripcion' => $tcDescripcion,
            'Orden' => $tnOrden,
            'Usr' => 0,
            'UsrFecha' => $tcFecha,
            'UsrHora' => $tcHora,
        ]);
    }

    private function upsertRol(string $tcNombreRol, string $tcDescripcion, int $tnEstado, string $tcFecha, string $tcHora): void
    {
        $loExiste = DB::connection('mysqlNegocio')->table('ROL')->where('NombreRol', $tcNombreRol)->first();

        if ($loExiste) {
            DB::connection('mysqlNegocio')->table('ROL')->where('Rol', (int)$loExiste->Rol)->update([
                'Descripcion' => $tcDescripcion,
                'Estado' => $tnEstado,
                'UsrFecha' => $tcFecha,
                'UsrHora' => $tcHora,
            ]);
            return;
        }

        DB::connection('mysqlNegocio')->table('ROL')->insert([
            'NombreRol' => $tcNombreRol,
            'Descripcion' => $tcDescripcion,
            'Estado' => $tnEstado,
            'Usr' => 0,
            'UsrFecha' => $tcFecha,
            'UsrHora' => $tcHora,
        ]);
    }

    private function upsertTipoImagen(string $tcNombre, string $tcDescripcion, int $tnEstado, string $tcFecha, string $tcHora): void
    {
        $loExiste = DB::connection('mysqlNegocio')->table('TIPOIMAGEN')->where('NombreTipoImagen', $tcNombre)->first();

        if ($loExiste) {
            DB::connection('mysqlNegocio')->table('TIPOIMAGEN')->where('TipoImagen', (int)$loExiste->TipoImagen)->update([
                'Descripcion' => $tcDescripcion,
                'Estado' => $tnEstado,
                'UsrFecha' => $tcFecha,
                'UsrHora' => $tcHora,
            ]);
            return;
        }

        DB::connection('mysqlNegocio')->table('TIPOIMAGEN')->insert([
            'NombreTipoImagen' => $tcNombre,
            'Descripcion' => $tcDescripcion,
            'Estado' => $tnEstado,
            'Usr' => 0,
            'UsrFecha' => $tcFecha,
            'UsrHora' => $tcHora,
        ]);
    }

    private function obtenerEstado(string $tcEntidad, int $tnCodigo): int
    {
        $lo = DB::connection('mysqlNegocio')
            ->table('ESTADO')
            ->select('Estado')
            ->where('Entidad', $tcEntidad)
            ->where('CodigoEstado', $tnCodigo)
            ->first();

        if (!$lo) {
            throw new \RuntimeException("No se encontro estado {$tcEntidad}/{$tnCodigo}");
        }

        return (int)$lo->Estado;
    }
};


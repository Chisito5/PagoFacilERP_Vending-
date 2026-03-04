# ESTANDAR NOMENCLATURA SYSCOOP

## Objetivo

Unificar nomenclatura de objetos nuevos sin romper estructuras existentes en produccion.

## Reglas obligatorias para nuevos objetos

1. Tabla en mayuscula (`MAQUINA`, `PRODUCTOIMAGEN`).
2. PK = nombre de tabla en singular (`MAQUINA.Maquina`, `ROJO.Rojo`).
3. FK = nombre exacto de entidad referenciada (`USUARIO`, `EMPRESA`, `MAQUINA`).
4. Campos de auditoria estandar: `Usr`, `UsrFecha`, `UsrHora`.
5. Estado por catalogo central (`ESTADO`) y no por flags ambiguos.
6. Endpoints mantienen contrato uniforme `{Ok, Mensaje, Datos, Errores, Meta}`.
7. No hacer renombres destructivos directos: usar estrategia aditiva + backfill + switch.

## Convencion de compatibilidad durante refactor

- Fase 1/2: crear vistas/adaptadores para homogenizar naming logico.
- Fase 3: introducir columnas/estructuras nuevas en paralelo.
- Fase 4: deprecacion controlada con observabilidad.

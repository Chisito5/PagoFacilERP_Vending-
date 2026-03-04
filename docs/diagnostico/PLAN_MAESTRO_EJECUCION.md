# PLAN MAESTRO EJECUCION BD (FASES COMPATIBLES)

Fecha de corte: `2026-03-03`
Base analizada: `mysqlNegocio`

## Objetivo

Ejecutar mejora estructural de BD en fases sin ruptura de frontend ni transacciones criticas.

## Fase 1 - Diagnostico real

Estado: completado (evidencia generada)

Entregables:
- `docs/diagnostico/MATRIZ_API_TABLA.csv`
- `docs/diagnostico/MATRIZ_TABLA_USO.csv`
- `docs/diagnostico/CLASIFICACION_TABLAS.md`
- `docs/diagnostico/BASELINE_METRICAS.md`

Salida clave:
- Inventario de rutas y tablas reales usadas por codigo.
- Clasificacion inicial por riesgo: `CRITICA`, `CANDIDATA_UNIFICAR`, `LEGACY_NO_USADA`, `ACTIVA`.

## Fase 2 - Compatibilidad sin ruptura

Estado: iniciado (base entregada)

Entregables:
- `docs/diagnostico/ESTANDAR_NOMENCLATURA_SYSCOOP.md`
- `docs/diagnostico/MAPA_EQUIVALENCIAS_TABLA_CAMPO.md`
- `sql/compat/vw_maquina_operativa.sql`
- `sql/compat/vw_stock_celda_detalle.sql`
- `sql/compat/vw_transaccion_comercial.sql`
- `sql/compat/vw_usuario_acceso.sql`

Objetivo operativo:
- Consolidar lecturas de reportes/tablero via vistas.
- Mantener contratos API actuales y resolver naming en adaptadores internos.

## Fase 3 - Migraciones controladas

Estado: pendiente

Secuencia obligatoria por dominio:
1. Cambio aditivo (nuevas columnas/tablas).
2. Backfill y validacion de paridad.
3. Switch de lectura controlado.
4. Deprecacion de lectura legacy.

Regla:
- No renombres destructivos directos en tablas criticas.

## Fase 4 - Cutover y saneamiento

Estado: pendiente

Criterio de paso:
1. p95 no degradado > 10%.
2. Sin aumento de errores de negocio.
3. Sin violaciones de integridad.
4. Validacion funcional de endpoints transaccionales y tablero.

Acciones:
- Congelar legacy no usada.
- Observabilidad en ventana de estabilidad.
- Limpieza definitiva solo con evidencia de cero lectura/escritura.

## Comando de regeneracion del diagnostico

```bash
php scripts/diagnostico/generar_diagnostico.php
```

## Nota de metodo

La matriz `API -> Tabla` se genera en modo heuristico por modulo/servicio para no afectar codigo productivo.
Para cambios de Fase 3 se recomienda complementar con trazas SQL runtime en staging replica.

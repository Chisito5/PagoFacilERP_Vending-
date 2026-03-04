# BASELINE DE METRICAS

Fecha de corte: **2026-03-03 13:53:02**
Entorno objetivo de medicion: **Staging replica**

## Inventario base

- Rutas API detectadas: **191**.
- Filas en matriz API-TABLA (desagregadas por tabla): **868**.
- Total tablas BD: **90**.
- Tablas con referencia en codigo: **59**.
- Tablas sin referencia en codigo: **31**.

## Inventario de datos y crecimiento

- Filas por tabla: disponible en `MATRIZ_TABLA_USO.csv` (columna `FilasBD`).
- Crecimiento 30 dias: **pendiente de serie historica** (requiere snapshots diarios para comparacion).

## Top prefijos API (conteo de rutas)

| Prefijo | Rutas |
|---|---:|
| maquina | 22 |
| producto | 15 |
| anuncio | 13 |
| merma | 12 |
| usuario | 11 |
| planogramacelda | 7 |
| tablero | 7 |
| empresa | 6 |
| celda | 6 |
| lote | 6 |
| productofamilia | 6 |
| productogrupo | 6 |
| productosubgrupo | 6 |
| productoimagen | 6 |
| deposito | 6 |
| reglaalerta | 6 |
| analitica | 6 |
| auth | 5 |
| reposicion | 5 |
| alerta | 5 |

## Top tablas por referencia en codigo

| Tabla | Referencias | Lecturas | Escrituras |
|---|---:|---:|---:|
| EXISTENCIACELDA | 29 | 9 | 20 |
| CELDA | 27 | 14 | 13 |
| MAQUINA | 24 | 14 | 10 |
| VENTA | 23 | 19 | 4 |
| PRODUCTO | 21 | 10 | 11 |
| USUARIO | 17 | 12 | 5 |
| LOTE | 15 | 7 | 8 |
| MERMA | 15 | 6 | 9 |
| PLANOGRAMACELDA | 14 | 7 | 7 |
| USUARIOMAQUINA | 14 | 8 | 6 |
| ALERTA | 12 | 8 | 4 |
| MAQUINAIMAGEN | 12 | 6 | 6 |
| ANUNCIOMAQUINA | 11 | 4 | 7 |
| ANUNCIOPRODUCTO | 11 | 4 | 7 |
| EMPRESA | 11 | 6 | 5 |
| ANUNCIO | 10 | 3 | 7 |
| RESERVA | 10 | 2 | 8 |
| MOVIMIENTOINVENTARIO | 9 | 6 | 3 |
| PRODUCTOIMAGEN | 9 | 4 | 5 |
| REPORTEGENERADO | 9 | 4 | 5 |

## Metricas obligatorias antes/despues (Fase 3/Fase 4)

1. p50/p95/p99 por endpoint critico (`/api/reserva/*`, `/api/venta*`, `/api/reposicion*`, `/api/stock/*`, `/api/tablero/*`).
2. Queries SQL por request y tiempo total SQL por request.
3. Deadlocks, lock wait timeout, errores 409/500.
4. Tamano de tablas e indices por dominio.
5. Throughput transaccional (RPS por modulo transaccional).

## Umbrales de aceptacion

- No degradar p95 > 10%.
- No aumentar errores de negocio.
- Cero violaciones de integridad referencial.

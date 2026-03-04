# CLASIFICACION DE TABLAS

Fecha de corte: **2026-03-03 13:53:02**
Base: **PagoFacil_VendingMachine**

## Resumen

- Total tablas en BD: **90**
- Tablas referenciadas por codigo (`app/*`): **59**
- Tablas no referenciadas por codigo: **31**
- Criticas: **20**
- Candidatas a unificar: **19**
- Legacy no usadas (sin referencia + 0 filas): **27**

## 1) Tablas criticas (no tocar estructural al inicio)

- `RESERVA` (Filas: 40)
- `RESERVADETALLE` (Filas: 40)
- `VENTA` (Filas: 977)
- `VENTAREVERSA` (Filas: 4)
- `REPOSICION` (Filas: 180)
- `REPOSICIONDETALLE` (Filas: 180)
- `EXISTENCIACELDA` (Filas: 225)
- `LOTE` (Filas: 96)
- `MOVIMIENTOINVENTARIO` (Filas: 0)
- `PLANOGRAMA` (Filas: 4)
- `PLANOGRAMACELDA` (Filas: 225)
- `MAQUINA` (Filas: 4)
- `CELDA` (Filas: 234)
- `PRODUCTO` (Filas: 66)
- `PRODUCTOEMPRESA` (Filas: 66)
- `USUARIO` (Filas: 3)
- `USUARIOROL` (Filas: 2)
- `USUARIOMAQUINA` (Filas: 7)
- `SESIONAPI` (Filas: 159)
- `IDEMPOTENCIA` (Filas: 60)

## 2) Candidatas a unificar (despues de capa de compatibilidad)

- `ALERTA` (Filas: 120, Referencias: 12)
- `ANUNCIO` (Filas: 0, Referencias: 10)
- `ANUNCIOMAQUINA` (Filas: 0, Referencias: 11)
- `ANUNCIOPRODUCTO` (Filas: 0, Referencias: 11)
- `MAQUINAESTADOOPERATIVO` (Filas: 4, Referencias: 6)
- `MAQUINAESTADOOPERATIVOHISTORIAL` (Filas: 0, Referencias: 3)
- `PRODUCTODISENO` (Filas: 0, Referencias: 7)
- `PRODUCTODISENOCAPA` (Filas: 0, Referencias: 3)
- `PRODUCTOFAMILIA` (Filas: 0, Referencias: 3)
- `PRODUCTOGRUPO` (Filas: 0, Referencias: 3)
- `PRODUCTOIMAGEN` (Filas: 132, Referencias: 9)
- `PRODUCTOSUBGRUPO` (Filas: 0, Referencias: 3)
- `REGLAALERTA` (Filas: 0, Referencias: 8)
- `TIPOALERTA` (Filas: 2, Referencias: 0)
- `TIPOEMPRESA` (Filas: 2, Referencias: 1)
- `TIPOIMAGEN` (Filas: 6, Referencias: 3)
- `TIPOINTERNET` (Filas: 5, Referencias: 2)
- `TIPOLUGARINSTALACION` (Filas: 5, Referencias: 1)
- `TIPOMOVIMIENTOINVENTARIO` (Filas: 4, Referencias: 5)

## 3) Legacy no usadas (congelar primero)

- `ALCANCEANUNCIO`
- `ASIGNACIONMAQUINA`
- `CACHE`
- `CACHE_LOCKS`
- `COMANDODISPOSITIVO`
- `DISPOSITIVO`
- `EVENTOMAQUINA`
- `FAILED_JOBS`
- `JOBS`
- `JOB_BATCHES`
- `MAQUINADISPOSITIVO`
- `METODOPAGO`
- `PAGO`
- `PASSWORD_RESET_TOKENS`
- `PLANCONVENIO`
- `PLANTILLAVISUAL`
- `SESSIONS`
- `TIPOALIMENTACIONELECTRICA`
- `TIPOCANALVENTA`
- `TIPOCOMANDODISPOSITIVO`
- `TIPOEVENTOMAQUINA`
- `TIPOMERMA`
- `TIPOTELEMETRIA`
- `TRANSACCION`
- `TRANSACCIONDETALLE`
- `UNIDADMEDIDA`
- `USERS`

## 4) Criterio aplicado

- `CRITICA`: lista transaccional cerrada por negocio.
- `CANDIDATA_UNIFICAR`: catalogos repetidos o dominios duplicados con potencial de consolidacion.
- `LEGACY_NO_USADA`: sin referencia en `app/*` y sin filas.
- `ACTIVA`: con referencia en codigo y fuera de lista critica.
- `SIN_REFERENCIA`: sin referencia pero con filas (requiere analisis funcional antes de tocar).

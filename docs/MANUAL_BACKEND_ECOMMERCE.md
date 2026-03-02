# MANUAL_BACKEND_ECOMMERCE

Manual tecnico integral para el equipo que construira el nuevo e-commerce sobre este backend ERP Vending.

Fecha de corte: **2026-02-28**

## 1) Objetivo

Este documento consolida:

- contexto funcional del backend,
- reglas transversales obligatorias,
- flujo de autenticacion y seguridad,
- inventario de APIs por modulo,
- flujo de compra recomendado para e-commerce,
- operacion (scheduler, colas, tiempo real, reportes e IoT).

## 2) Stack y arquitectura

- Framework: Laravel 12
- PHP: 8.2+
- Base de datos principal de negocio: `mysqlNegocio`
- Tiempo real: Laravel Reverb
- Cola asincrona: `database queue`
- Storage de archivos: disco local publico (R1)
- Estructura modular: `app/Modulos/*`

Modulos actuales:

- `Alerta`, `Analitica`, `Anuncio`, `Aprobacion`, `Auditoria`, `Autenticacion`
- `CatalogoAvanzado`, `Celda`, `Empresa`, `Estado`, `ExistenciaCelda`
- `IntegracionIot`, `Lote`, `Maquina`, `MaquinaOperativo`, `Merma`
- `PlanogramaCelda`, `Producto`, `Reporte`, `Reposicion`, `Reserva`
- `Stock`, `Tablero`, `TipoEmpresa`, `Usuario`, `Venta`

## 3) Contrato de respuesta (obligatorio)

Todas las APIs devuelven:

```json
{
  "Ok": true,
  "Mensaje": "texto",
  "Datos": {},
  "Errores": [],
  "Meta": {}
}
```

Reglas:

- `Errores` siempre arreglo.
- `Meta` siempre objeto.
- Se aplica igual en 200, 4xx y 5xx (incluye validacion, auth y autorizacion).

## 4) Seguridad, sesion y permisos

### 4.1 Endpoints publicos

- `POST /api/auth/login`
- `POST /api/auth/refresh`
- `POST /api/integracion/iot/webhook` (no usa bearer, usa firma HMAC)

### 4.2 Endpoints protegidos

Todo el resto de `/api/*` exige:

`Authorization: Bearer <Token>`

### 4.3 Sesion opaca (no JWT)

- Token acceso: 10 minutos
- Refresh token: 7 dias
- Tokens guardados hasheados en `SESIONAPI`

### 4.4 Login ejemplo

`POST /api/auth/login`

```json
{
  "Usuario": "Vladimir",
  "Clave": "Asadito7"
}
```

Respuesta `Datos`:

```json
{
  "Token": "token_acceso",
  "RefreshToken": "token_refresco",
  "Usuario": "Vladimir",
  "Rol": "Admin",
  "Permisos": ["*"],
  "EmpresaDefault": 1
}
```

## 5) Reglas transversales de negocio

## 5.1 Idempotencia

Header obligatorio en POST transaccionales:

`Clave-Idempotencia: <uuid|string>`

Aplica en:

- `POST /api/reserva`
- `POST /api/reserva/confirmar`
- `POST /api/reserva/cancelar`
- `POST /api/venta`
- `POST /api/venta/reversa`
- `POST /api/reposicion`

Comportamiento:

- falta clave -> `400`
- misma clave + mismo body -> replay (misma respuesta)
- misma clave + body distinto -> `409`

## 5.2 Versionado optimista

En mutaciones `PUT/PATCH/DELETE` de CRUD:

- se exige `Version`,
- si no coincide -> `409 CONFLICTO_VERSION`.

## 5.3 Soft delete

- no hay borrado fisico de negocio,
- `DELETE` es logico (cambio de estado).

## 5.4 Auditoria

Toda mutacion sensible registra:

- entidad, entidadId, accion,
- antes, despues, motivo,
- usuario y fecha/hora.

Consulta:

- `GET /api/auditoria/{Entidad}/{EntidadId}`

## 5.5 Aprobaciones

Flujo:

- `POST /api/aprobaciones/solicitar`
- `POST /api/aprobaciones/{id}/aprobar`
- `POST /api/aprobaciones/{id}/rechazar`
- `GET /api/aprobaciones`

## 6) Flujo de compra recomendado para e-commerce

## 6.1 Flujo recomendado (reserva -> confirmacion)

1. Cliente consulta catalogo/stock por maquina.
2. `POST /api/reserva` con `Clave-Idempotencia`.
3. Backend responde `Reserva`, `ReservaExterna`, `ExpiraEn`.
4. E-commerce procesa pago.
5. `POST /api/reserva/confirmar` (idempotente).
6. Backend registra `VENTA` y cierra reserva.

## 6.2 Cancelacion de reserva

`POST /api/reserva/cancelar` (idempotente)

## 6.3 Venta directa (sin reserva)

`POST /api/venta` (idempotente)

## 6.4 Reversa de venta

`POST /api/venta/reversa` (idempotente)

## 7) Inventario completo de APIs por modulo

Total rutas API registradas: **166**

Resumen por prefijo:

- `alerta` (5), `analitica` (6), `anuncio` (13), `aprobaciones` (4), `auditoria` (1)
- `auth` (5), `celda` (6), `empresa` (6), `estado` (1), `existenciacelda` (2)
- `integracion/iot` (3), `lote` (6), `maquina` (14), `merma` (12), `planogramacelda` (7)
- `producto` (8), `productofamilia` (6), `productogrupo` (6), `productoimagen` (6), `productosubgrupo` (6)
- `reglaalerta` (6), `reporte` (4), `reposicion` (5), `reserva` (3), `stock` (3)
- `tablero` (6), `tipoempresa` (1), `usuario` (11), `venta` (4)

### 7.1 Auth

- `POST /api/auth/login`
- `POST /api/auth/refresh`
- `POST /api/auth/logout`
- `GET /api/auth/me`
- `GET /api/auth/permisos`

### 7.2 Empresa / Usuario / Roles

- CRUD `empresa`
- CRUD `usuario`
- `GET/PUT /api/usuario/{tnUsuario}/rol`
- `GET/POST/DELETE /api/usuario/{tnUsuario}/maquina`
- `GET /api/maquina/{tnMaquina}/administradores`
- `GET /api/maquina/{tnMaquina}/operadores`

### 7.3 Maquina / Ubicacion / Operativo

- CRUD `maquina`
- `GET /api/maquina/{IdMaquina}/celda`
- `GET/PUT /api/maquina/{tnMaquina}/ubicacion`
- `GET/POST /api/maquina/{tnMaquina}/estado-operativo`
- `GET /api/maquina/{tnMaquina}/estado-operativo/historial`

### 7.4 Catalogo y stock

- CRUD `producto`
- CRUD `productofamilia`, `productogrupo`, `productosubgrupo`
- CRUD `productoimagen`
- `POST /api/producto/{tnProducto}/imagen/subir`
- `DELETE /api/producto/{tnProducto}/imagen/{tnProductoImagen}`
- CRUD `lote`, CRUD `celda`, CRUD `planogramacelda`
- `PATCH /api/planogramacelda/{tnPlanogramaCelda}/precio`
- `GET /api/existenciacelda`
- `GET /api/existenciacelda/celda/{IdCelda}`
- `GET /api/stock/maquina/{IdMaquina}`
- `GET /api/stock/maquina/{IdMaquina}/seleccion/{CodigoSeleccion}`
- `GET /api/stock/movimientos`

### 7.5 Reposicion / Reserva / Venta

- `POST /api/reposicion/prevalidar`
- `POST /api/reposicion` (idempotente)
- `GET /api/reposicion`
- `GET /api/reposicion/{tnReposicion}`
- `GET /api/reposicion/maquina/{tnMaquina}`

- `POST /api/reserva` (idempotente)
- `POST /api/reserva/confirmar` (idempotente)
- `POST /api/reserva/cancelar` (idempotente)

- `POST /api/venta` (idempotente)
- `POST /api/venta/reversa` (idempotente)
- `GET /api/venta`
- `GET /api/venta/maquina/{tnMaquina}`

### 7.6 Tablero y analitica

- `GET /api/tablero/resumen`
- `GET /api/tablero/ejecutivo/resumen`
- `GET /api/tablero/ejecutivo/maquinas`
- `GET /api/tablero/ejecutivo/mapa`
- `GET /api/tablero/ejecutivo/ranking`
- `GET /api/tablero/ejecutivo/maquina/{tnMaquina}/detalle`

- `GET /api/analitica/ventas`
- `GET /api/analitica/rotacion`
- `GET /api/analitica/stockout`
- `GET /api/analitica/rentabilidad`
- `GET /api/analitica/mermas`
- `GET /api/analitica/resumen`

### 7.7 Anuncios, alertas, mermas, reportes

- CRUD `anuncio` + asignaciones maquina/producto + `publicar`/`detener` + `impacto`
- CRUD `reglaalerta`
- `GET /api/alerta` + detalle + `atender`/`escalar`/`cerrar`
- CRUD `merma` + detalle + evidencia + `aprobar`/`rechazar`
- `POST /api/reporte/generar`
- `GET /api/reporte`
- `GET /api/reporte/{tnReporte}`
- `GET /api/reporte/{tnReporte}/descargar`

### 7.8 IoT

- `POST /api/integracion/iot/webhook`
- `GET /api/integracion/iot/evento`
- `GET /api/integracion/iot/evento/{tnEvento}`

## 8) Tiempo real (Reverb)

Canales privados:

- `empresa.{Empresa}`
- `maquina.{Maquina}`

Eventos de negocio utilizados:

- `stock.actualizado`
- `reposicion.creada`
- `reserva.creada`
- `reserva.confirmada`
- `reserva.cancelada`
- `venta.creada`
- `venta.reversada`
- `tablero.maquina.actualizada`
- `tablero.alerta.actualizada`
- `tablero.venta.actualizada`

## 9) Integracion IoT (firma HMAC)

Headers obligatorios:

- `X-IoT-EventId`
- `X-IoT-Timestamp`
- `X-IoT-Firma`
- `X-IoT-Origen`

Base de firma:

`timestamp + "\n" + eventId + "\n" + rawBody`

Algoritmo:

`HMAC-SHA256` con `IOT_CLAVE_SECRETA`

Reglas:

- anti-replay por ventana de tiempo (`IOT_VENTANA_SEGUNDOS`, default 300)
- idempotencia por `Origen + EventId`
- trazabilidad de recepcion/procesamiento/reintentos

## 10) Scheduler y comandos operativos

Programados en `bootstrap/app.php`:

- `reservas:expirar` (cada minuto)
- `alertas:generar` (cada minuto)
- `iot:evento:reencolar --max-intentos=5 --limite=200` (cada 5 minutos)
- `idempotencia:limpiar --dias=7` (diario)
- `reportes:limpiar --dias=7` (diario)

Comandos utiles:

- `php artisan reservas:expirar`
- `php artisan alertas:generar`
- `php artisan iot:evento:reencolar --max-intentos=5 --limite=200`
- `php artisan idempotencia:limpiar --dias=7`
- `php artisan reportes:limpiar --dias=7`
- `php artisan demo:cargar-productos-celdas --max-celdas=54`
- `php artisan lotes:backfill-cantidad-inicial --solo-cero=1`

## 11) Arranque de entorno local

Comandos base:

```bash
composer install
php artisan key:generate
php artisan migrate --database=mysqlNegocio
php artisan optimize:clear
php artisan route:list --path=api --except-vendor
```

Procesos que deben estar activos:

```bash
php artisan serve
php artisan queue:work
php artisan schedule:work
php artisan reverb:start
```

## 12) Variables de entorno importantes

- conexion negocio: `DB_NEGOCIO_HOST`, `DB_NEGOCIO_PORT`, `DB_NEGOCIO_DATABASE`, `DB_NEGOCIO_USERNAME`, `DB_NEGOCIO_PASSWORD`
- `QUEUE_CONNECTION=database`
- `BROADCAST_CONNECTION=reverb`
- `IOT_CLAVE_SECRETA=...`
- `IOT_VENTANA_SEGUNDOS=300`

## 13) Postman y colecciones

Archivos listos:

- `docs/postman/API-ERP-Completa.postman_collection.json`
- `docs/postman/Reserva-Cierre-Completo-7-Casos.postman_collection.json`
- `docs/postman/Reserva-Local.postman_environment.json`

Recomendacion:

1. Login y guardar `Token` en variable `token`.
2. Definir `baseUrl`, `empresaId`, `maquinaId`, `fechaDesde`, `fechaHasta`.
3. Para POST transaccionales, enviar siempre `Clave-Idempotencia`.

## 14) Usuario QA

Usuario de pruebas:

- Usuario: `Vladimir`
- Clave: `Asadito7`
- Rol: `Admin`
- Permiso: `*`
- Estado: Activo

## 15) Checklist minimo para equipo e-commerce

1. Login / refresh / logout funcionando.
2. Consulta catalogo + stock por maquina.
3. Compra con reserva (`reserva -> confirmar`) usando idempotencia.
4. Manejo de cancelacion/expiracion de reserva.
5. Consulta ventas y reversa idempotente.
6. Dashboard ejecutivo consumido con paginacion.
7. Manejo de errores 401/403/409/422 respetando contrato.
8. Suscripcion a canales Reverb de empresa y maquina.

## 16) Comando para inventario exacto de rutas

Para verificar endpoints reales en cualquier ambiente:

```bash
php artisan route:list --path=api --except-vendor
```

Y en JSON para integraciones automaticas:

```bash
php artisan route:list --path=api --except-vendor --json
```

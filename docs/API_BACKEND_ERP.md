# API_BACKEND_ERP

Documento tecnico del backend ERP (R1 + R2) actualizado al **01-03-2026**.

Manual integral para equipo e-commerce:
- `docs/MANUAL_BACKEND_ECOMMERCE.md`

## 1. Contrato unificado de respuesta
Todas las APIs JSON responden con:

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
- Validaciones (`422`), auth (`401/403`), conflictos (`409`) y errores de negocio respetan el contrato.

## 2. Seguridad y sesion
- Auth: `Authorization: Bearer <Token>`
- Publicas:
  - `POST /api/auth/login`
  - `POST /api/auth/refresh`
  - `POST /api/integracion/iot/webhook`
- Resto de `/api/*`: autenticadas.

Auth implementado:
- `POST /api/auth/login`
- `POST /api/auth/refresh`
- `POST /api/auth/logout`
- `GET /api/auth/me`
- `GET /api/auth/permisos`

## 3. Reglas transversales activas
- Paginacion homogena:
  - `Meta.PaginaActual`
  - `Meta.TamanoPagina`
  - `Meta.TotalRegistros`
  - `Meta.TotalPaginas`
- Versionado optimista en mutaciones CRUD (`Version` obligatoria en `PUT/PATCH/DELETE`).
- Borrado logico (sin `DELETE` fisico de negocio).
- Auditoria de mutaciones (`Antes`, `Despues`, `Motivo`, `Usuario`, `FechaHora`).
- Aprobaciones en cambios sensibles (`/api/aprobaciones/*`).

## 4. Idempotencia en POST criticos
Header obligatorio:
- `Clave-Idempotencia: <uuid|string>`

Aplica en:
- `POST /api/reserva`
- `POST /api/reserva/confirmar`
- `POST /api/reserva/cancelar`
- `POST /api/venta`
- `POST /api/venta/reversa`
- `POST /api/reposicion`

Comportamiento:
- Sin clave: `400`.
- Misma clave + mismo body: replay exacto.
- Misma clave + body distinto: `409`.

## 5. Endpoints implementados por bloque R1

### Bloque 1: Multiempresa jerarquica (Dueno/Admin/Operador)
- `GET /api/usuario`
- `GET /api/usuario/{Usuario}`
- `POST /api/usuario`
- `PUT /api/usuario/{Usuario}`
- `PATCH /api/usuario/{Usuario}`
- `DELETE /api/usuario/{Usuario}` (logico)
- `GET /api/usuario/{Usuario}/rol`
- `PUT /api/usuario/{Usuario}/rol`
- `GET /api/usuario/{Usuario}/maquina`
- `POST /api/usuario/{Usuario}/maquina`
- `DELETE /api/usuario/{Usuario}/maquina/{Maquina}` (logico)
- `GET /api/maquina/{Maquina}/administradores`
- `GET /api/maquina/{Maquina}/operadores`

### Bloque 2: Ubicacion y estado operativo de maquina
- `GET /api/maquina/{Maquina}/ubicacion`
- `PUT /api/maquina/{Maquina}/ubicacion`
- `GET /api/maquina/{Maquina}/estado-operativo`
- `POST /api/maquina/{Maquina}/estado-operativo`
- `GET /api/maquina/{Maquina}/estado-operativo/historial`

#### Edicion de maquina (campos habilitados)
En `PUT/PATCH /api/maquina/{IdMaquina}` se pueden editar:
- `CodigoMaquina`
- `NumeroSerie`
- `Marca`
- `Modelo`
- `IdentificadorConexion`
- `UbicacionActual`
- `FilasMatriz`
- `ColumnasMatriz`
- `Estado` (requiere `Aprobacion` valida)

Campos gestionados por backend (no editables desde cliente):
- `Maquina` (autoincremental)
- `Usr`
- `UsrFecha`
- `UsrHora`

#### Fotos de maquina (apartado nuevo)
- `GET /api/maquina/{IdMaquina}/foto`
- `POST /api/maquina/{IdMaquina}/foto/lote-subir`
- `DELETE /api/maquina/{IdMaquina}/foto/{IdMaquinaFoto}`

Reglas criticas:
- Minimo operativo: `3` fotos activas por maquina.
- Si intentas registrar menos de 3 en carga inicial: `409`.
- Si intentas eliminar y dejar menos de 3: `409`.
- Auditoria automatica en backend para alta/baja de fotos.

### Bloque 3: Catalogo avanzado
- `GET/POST/PUT/PATCH/DELETE /api/productofamilia`
- `GET/POST/PUT/PATCH/DELETE /api/productogrupo`
- `GET/POST/PUT/PATCH/DELETE /api/productosubgrupo`
- `GET/POST/PUT/PATCH/DELETE /api/productoimagen`
- `POST /api/producto/{Producto}/imagen/subir`
- `DELETE /api/producto/{Producto}/imagen/{ProductoImagen}` (logico)

Tipos de imagen soportados:
- `FRENTE`
- `REVERSO`
- `ANVERSO`
- `DETALLE`

### Bloque 4: Campanas/anuncios
- `GET/POST/PUT/PATCH/DELETE /api/anuncio`
- `POST /api/anuncio/{Anuncio}/maquina`
- `DELETE /api/anuncio/{Anuncio}/maquina/{Maquina}` (logico)
- `POST /api/anuncio/{Anuncio}/producto`
- `DELETE /api/anuncio/{Anuncio}/producto/{Producto}` (logico)
- `POST /api/anuncio/{Anuncio}/publicar`
- `POST /api/anuncio/{Anuncio}/detener`
- `GET /api/anuncio/{Anuncio}/impacto`

### Bloque 5: Mermas con aprobacion
- `GET/POST/PUT/PATCH/DELETE /api/merma`
- `POST /api/merma/{Merma}/detalle`
- `DELETE /api/merma/{Merma}/detalle/{MermaDetalle}` (logico)
- `POST /api/merma/{Merma}/evidencia/subir`
- `GET /api/merma/{Merma}/evidencia`
- `POST /api/merma/{Merma}/aprobar`
- `POST /api/merma/{Merma}/rechazar`

Regla funcional:
- `REGISTRADA` no mueve stock.
- `APROBADA` aplica ajuste en `EXISTENCIACELDA` y crea `MOVIMIENTOINVENTARIO`.
- `RECHAZADA` no mueve stock.

### Bloque 6: Alertas operativas
- `GET/POST/PUT/PATCH/DELETE /api/reglaalerta`
- `GET /api/alerta`
- `GET /api/alerta/{Alerta}`
- `POST /api/alerta/{Alerta}/atender`
- `POST /api/alerta/{Alerta}/escalar`
- `POST /api/alerta/{Alerta}/cerrar`

Comando scheduler:
- `php artisan alertas:generar` (cada minuto)

### Bloque 7: Reportes exportables async
- `POST /api/reporte/generar`
- `GET /api/reporte`
- `GET /api/reporte/{Reporte}`
- `GET /api/reporte/{Reporte}/descargar`

Formatos:
- `CSV`
- `XLSX`
- `PDF`

Estados:
- `PENDIENTE`
- `PROCESANDO`
- `LISTO`
- `ERROR`
- `EXPIRADO`

Comando scheduler:
- `php artisan reportes:limpiar --dias=7` (diario)

### Bloque 8: Integracion IoT separada
- `POST /api/integracion/iot/webhook` (sin bearer)
- `GET /api/integracion/iot/evento`
- `GET /api/integracion/iot/evento/{Evento}`

Headers webhook obligatorios:
- `X-IoT-EventId`
- `X-IoT-Timestamp`
- `X-IoT-Firma`
- `X-IoT-Origen`

Firma:
- Algoritmo: `HMAC-SHA256`
- Base de firma: `X-IoT-Timestamp + "\\n" + X-IoT-EventId + "\\n" + rawBody`
- Secreto: `IOT_CLAVE_SECRETA`
- Ventana anti-replay (segundos): `IOT_VENTANA_SEGUNDOS` (default `300`)
- Idempotencia: `Origen + EventId` unico en BD.

Ejemplo rapido para Postman (Pre-request Script):

```javascript
const eventId = pm.variables.replaceIn("{{$guid}}");
const timestamp = String(Math.floor(Date.now() / 1000));
const raw = pm.request.body ? pm.request.body.raw : "";
const base = `${timestamp}\n${eventId}\n${raw}`;
const firma = CryptoJS.HmacSHA256(base, pm.environment.get("iotSecreto")).toString();
pm.variables.set("iotEventId", eventId);
pm.variables.set("iotTimestamp", timestamp);
pm.variables.set("iotFirma", firma);
```

Comando scheduler:
- `php artisan iot:evento:reencolar --max-intentos=5 --limite=200` (cada 5 minutos)

### Bloque 9: Analitica ejecutiva
- `GET /api/analitica/ventas`
- `GET /api/analitica/rotacion`
- `GET /api/analitica/stockout`
- `GET /api/analitica/rentabilidad`
- `GET /api/analitica/mermas`
- `GET /api/analitica/resumen`

Filtros comunes:
- `Empresa`
- `FechaDesde`
- `FechaHasta`
- `Maquina`
- `Producto`
- `Pagina`
- `TamanoPagina`

## 6. Otros modulos core activos
- `GET /api/tablero/resumen`
- `GET /api/tablero/ejecutivo/resumen?Empresa=&FechaDesde=&FechaHasta=`
- `GET /api/tablero/ejecutivo/maquinas?Empresa=&Estado=&Busqueda=&Pagina=&TamanoPagina=&Orden=ventas|ingresos|alertas`
- `GET /api/tablero/ejecutivo/mapa?Empresa=&Estado=&SoloConCoordenadas=1&FechaDesde=&FechaHasta=`
- `GET /api/tablero/ejecutivo/ranking?Empresa=&FechaDesde=&FechaHasta=&Top=10&Por=ventas|ingresos|alertas|margen`
- `GET /api/tablero/ejecutivo/maquina/{IdMaquina}/detalle?FechaDesde=&FechaHasta=`
- `GET /api/maquina`, `GET /api/maquina/{IdMaquina}`, `GET /api/maquina/{IdMaquina}/celda`
- `GET /api/stock/maquina/{IdMaquina}`
- `GET /api/stock/maquina/{IdMaquina}/seleccion/{CodigoSeleccion}`
- `GET /api/stock/movimientos`
- `POST /api/reposicion/prevalidar`
- `POST /api/reposicion`
- `GET /api/reposicion`
- `GET /api/reposicion/maquina/{IdMaquina}`
- `GET /api/reposicion/{IdReposicion}`
- `POST /api/reserva`
- `POST /api/reserva/confirmar`
- `POST /api/reserva/cancelar`
- `POST /api/venta`
- `POST /api/venta/reversa`
- `GET /api/venta`
- `GET /api/venta/maquina/{IdMaquina}`
- `GET /api/auditoria/{Entidad}/{EntidadId}`

### 6.2 R2: Celdas inteligentes + Deposito + Diseno/Galeria

#### Celdas inteligentes por maquina (6x9 = 54)
- `GET /api/maquina/{IdMaquina}/celdas/matriz`
- `POST /api/maquina/{IdMaquina}/celdas/simular-ocupacion`
- `POST /api/maquina/{IdMaquina}/celdas/asignar-producto` (idempotente)
- `POST /api/maquina/{IdMaquina}/celdas/liberar` (idempotente)
- `GET /api/maquina/{IdMaquina}/celdas/conflictos`

Request base simulacion/asignacion:

```json
{
  "CeldaAncla": 1,
  "Producto": 1,
  "Lote": 1,
  "Cantidad": 5,
  "SpanColumnas": 1,
  "SpanFilas": 1,
  "Version": "2026-03-01|10:00:00",
  "Motivo": "Operacion R2"
}
```

#### Deposito por empresa
- `GET /api/deposito`
- `GET /api/deposito/{IdDeposito}/stock`
- `GET /api/deposito/movimientos`
- `POST /api/deposito/movimiento/entrada` (idempotente)
- `POST /api/deposito/movimiento/salida` (idempotente)
- `POST /api/deposito/transferir-a-maquina` (idempotente)

Request base transferencia:

```json
{
  "Deposito": 1,
  "Maquina": 1,
  "Celda": 1,
  "Producto": 1,
  "Lote": 1,
  "Cantidad": 2,
  "Motivo": "Transferencia operativa"
}
```

#### Diseno de producto por capas y galeria
- `GET /api/producto/{IdProducto}/diseno`
- `POST /api/producto/{IdProducto}/diseno`
- `PATCH /api/producto/{IdProducto}/diseno`
- `POST /api/producto/{IdProducto}/diseno/render`
- `GET /api/producto/{IdProducto}/galeria`
- `POST /api/producto/{IdProducto}/imagen/lote-subir`
- `POST /api/producto/{IdProducto}/galeria/reordenar`

Request base diseno:

```json
{
  "Version": "2026-03-01|10:00:00",
  "Lienzo": {
    "Ancho": 900,
    "Alto": 500
  },
  "Capas": [
    {
      "Id": "CAPA-1",
      "Tipo": "FONDO",
      "Orden": 1,
      "X": 0,
      "Y": 0,
      "Ancho": 900,
      "Alto": 500,
      "Opacidad": 1,
      "Rotacion": 0,
      "Texto": null,
      "Color": null,
      "Fuente": null,
      "Recurso": "https://mi-cdn/fondo.png"
    }
  ],
  "Motivo": "Actualizacion visual"
}
```

### 6.3 Matriz de compatibilidad R2 (Frontend ↔ Backend)
Rutas alineadas con `ProyectoVendingFrontERP`:

- `GET /api/maquina/{IdMaquina}/celdas/matriz` ↔ `maquina/{id}/celdas/matriz`
- `POST /api/maquina/{IdMaquina}/celdas/simular-ocupacion` ↔ `maquina/{id}/celdas/simular-ocupacion`
- `POST /api/maquina/{IdMaquina}/celdas/asignar-producto` ↔ `maquina/{id}/celdas/asignar-producto`
- `POST /api/maquina/{IdMaquina}/celdas/liberar` ↔ `maquina/{id}/celdas/liberar`
- `GET /api/maquina/{IdMaquina}/celdas/conflictos` ↔ `maquina/{id}/celdas/conflictos`
- `GET /api/deposito` ↔ `deposito`
- `GET /api/deposito/{IdDeposito}/stock` ↔ `deposito/{id}/stock`
- `GET /api/deposito/movimientos` ↔ `deposito/movimientos`
- `POST /api/deposito/movimiento/entrada` ↔ `deposito/movimiento/entrada`
- `POST /api/deposito/movimiento/salida` ↔ `deposito/movimiento/salida`
- `POST /api/deposito/transferir-a-maquina` ↔ `deposito/transferir-a-maquina`
- `GET /api/producto/{IdProducto}/diseno` ↔ `producto/{id}/diseno`
- `POST /api/producto/{IdProducto}/diseno` ↔ `producto/{id}/diseno`
- `PATCH /api/producto/{IdProducto}/diseno` ↔ `producto/{id}/diseno`
- `POST /api/producto/{IdProducto}/diseno/render` ↔ `producto/{id}/diseno/render`
- `GET /api/producto/{IdProducto}/galeria` ↔ `producto/{id}/galeria`
- `POST /api/producto/{IdProducto}/imagen/lote-subir` ↔ `producto/{id}/imagen/lote-subir`
- `POST /api/producto/{IdProducto}/galeria/reordenar` ↔ `producto/{id}/galeria/reordenar`

Notas operativas:
- POST criticos R2 usan `Clave-Idempotencia`.
- Repeticion idempotente devuelve header `X-Repeticion-Idempotencia: si`.
- `docs/postman/API-ERP-Completa.postman_collection.json` incluye carpeta `13 R2 Operativo` con 18 requests.
- Variables nuevas de coleccion: `depositoId`, `disenoVersion`.

### 6.4 Tablero ejecutivo (detalle de payload)
- `GET /api/tablero/ejecutivo/resumen`
  - `Datos`: `TotalMaquinas`, `MaquinasActivas`, `MaquinasConAlerta`, `VentasTotal`, `IngresosTotal`, `MaquinaTopVentas`, `ProductoTopGlobal`.
- `GET /api/tablero/ejecutivo/maquinas`
  - `Datos[]`: `IdMaquina`, `CodigoMaquina`, `NombreMaquina`, `TipoMaquina`, `EstadoMaquina`, `EstadoOperativo`, `Ubicacion`, `Responsables`, `VentasPeriodo`, `ProductoEstrella`, `Alertas`, `Version`, `UsrFecha`, `UsrHora`.
  - `Meta`: paginacion estandar.
- `GET /api/tablero/ejecutivo/mapa`
  - `Datos[]`: `IdMaquina`, `CodigoMaquina`, `Latitud`, `Longitud`, `EstadoOperativo`, `NivelAlerta`, `IngresosPeriodo`, `VentasPeriodo`.
- `GET /api/tablero/ejecutivo/ranking`
  - `Datos[]`: `IdMaquina`, `CodigoMaquina`, `NombreMaquina`, `Valor`.
- `GET /api/tablero/ejecutivo/maquina/{IdMaquina}/detalle`
  - `Datos`: `Maquina`, `Responsables`, `VentasResumen`, `ProductoEstrella`, `TopProductos`, `AlertasActivas`, `VentasPorDia`, `MermasResumen`, `UltimosMovimientosStock`, `Alertas`.

## 7. Tiempo real (Reverb)
Canales privados:
- `empresa.{id}`
- `maquina.{id}`

Eventos emitidos:
- `stock.actualizado`
- `reposicion.creada`
- `reserva.creada`
- `reserva.confirmada`
- `reserva.cancelada`
- `venta.creada`
- `venta.reversada`

## 8. Usuario QA
Usuario de pruebas:
- Usuario: `Vladimir`
- Clave: `Asadito7`
- Rol: `Admin`
- Permiso: `*`
- Estado: activo

## 9. Variables de entorno clave
Agregar en `.env`:

```env
QUEUE_CONNECTION=database
BROADCAST_CONNECTION=reverb
IOT_CLAVE_SECRETA=tu_clave_iot
IOT_VENTANA_SEGUNDOS=300
```

## 10. Comandos de despliegue/operacion

```bash
composer install
php artisan migrate --database=mysqlNegocio --force
php artisan route:list --path=api --except-vendor
php artisan queue:work --queue=default
php artisan schedule:work
php artisan reverb:start
```

## 11. Checklist de validacion rapida
1. Login correcto y uso de token en endpoint protegido.
2. POST idempotente repetido con misma `Clave-Idempotencia` no duplica.
3. `PUT/PATCH` con `Version` vieja devuelve `409`.
4. Webhook IoT con firma correcta devuelve `202` y queda evento registrado.
5. Repetir mismo `X-IoT-EventId + X-IoT-Origen` devuelve respuesta idempotente.
6. Generar reporte y verificar transicion a `LISTO` + descarga.
7. Crear merma, aprobar con aprobacion valida y validar movimiento de stock.

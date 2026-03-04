# API ERP v1 Frontend

Base oficial ERP:
- `/api/erp/v1`

## Contrato
```json
{
  "Ok": true,
  "Mensaje": "texto",
  "Datos": {},
  "Errores": [],
  "Meta": {}
}
```

## Reglas cerradas
1. JSON en PascalCase SYSCOOP.
2. Timezone: `America/La_Paz`.
3. Fechas en ISO 8601 con offset (`-04:00`).
4. `TamanoPagina > 200` -> `422`.
5. Moneda MVP: solo `BOB`.
6. Idempotencia en POST sensibles con `Clave-Idempotencia`.
7. Replay headers:
   - `X-Repeticion-Idempotencia: si`
   - `X-Idempotent-Replay: true`

## Endpoints ERP v1

### Auth
- `POST /auth/login`
- `POST /auth/refresh`
- `POST /auth/logout`
- `GET /auth/perfil`
- `GET /auth/permisos`

### Tablero
- `GET /tablero/ejecutivo/unificado`
- `GET /tablero/ejecutivo/resumen`
- `GET /tablero/ejecutivo/maquinas`
- `GET /tablero/ejecutivo/mapa`
- `GET /tablero/ejecutivo/ranking`
- `GET /tablero/ejecutivo/maquina/{IdMaquina}/detalle`

### Venta
- `POST /venta`
- `GET /venta`
- `GET /venta/{IdVenta}`
- `GET /venta/maquina/{IdMaquina}`
- `POST /venta/reversa`
- `GET /venta/{IdVenta}/historial`

### Transacciones
- `GET /transacciones`
- `GET /transacciones/{IdTransaccion}`

### Analitica
- `GET /analitica/resumen`
- `GET /analitica/ventas`
- `GET /analitica/rotacion`
- `GET /analitica/stockout`
- `GET /analitica/rentabilidad`
- `GET /analitica/mermas`

### Catalogo
- `GET /catalogo/maquina`
- `GET /catalogo/producto`
- `GET /catalogo/oferta`
- `GET /catalogo/metodo-pago`
- `GET /catalogo/estado-transaccion`

## Historial de venta
`GET /venta/{IdVenta}/historial?Pagina=1&TamanoPagina=20`

`Datos`:
- `IdVenta`
- `Eventos[]`:
  - `IdEvento`
  - `TipoEvento` (`VENTA_CREADA`, `VENTA_REVERTIDA`)
  - `FechaHora`
  - `Estado`
  - `Usuario`
  - `Motivo`
  - `Antes`
  - `Despues`
  - `Referencia`

Orden:
- `FechaHora DESC`, desempate por `IdEvento`.

## Búsqueda
`Busqueda` aplica a:
1. `IdVenta` / `IdTransaccion`
2. `CodigoMaquina`
3. `NombreProducto`
4. `CodigoProducto`
5. `NumeroCasilla`
6. `ReferenciaOperacion` (cuando aplique)

## Errores estandarizados
- `AUTH_401`
- `AUTH_403`
- `REQ_400`
- `VAL_422`
- `NEG_404`
- `BUS_409`
- `IDEMPOTENCY_CONFLICT_409`
- `IDEMPOTENCY_IN_PROGRESS_409`
- `API_500`

## Observabilidad
Todos los endpoints ERP v1 devuelven:
- `X-Request-Id`
- `X-Correlation-Id`

## Feature flag / rollback
Variable:
- `ERP_V1_HABILITADO=true|false`

Si está en `false`, `/api/erp/v1/*` responde `503` y legacy `/api/*` sigue activo.


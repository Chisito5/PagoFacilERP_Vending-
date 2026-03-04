# API_KIOSKO_V1

Documento tecnico para frontend ecommerce/kiosko sobre la capa dedicada `kiosko/v1`.

## 1. Base y seguridad
- Base: `/api/kiosko/v1`
- Auth: `Authorization: Bearer <token>`
- Idempotencia POST: `Clave-Idempotencia: <valor-unico>`
- Header replay (si aplica): `X-Repeticion-Idempotencia: si|no`

## 2. Contrato de respuesta
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
1. `Errores` siempre arreglo.
2. `Meta` siempre objeto.
3. Estructura de error: `{Codigo, Campo, Detalle}`.

## 3. Endpoints

### 3.1 Catalogo unificado de maquina
`GET /api/kiosko/v1/maquina/{Maquina}/catalogo`

No usa paginacion. Devuelve snapshot completo.

`Datos`:
- `Maquina`
- `CodigoMaquina`
- `VersionCatalogo`
- `Celdas[]`:
  - `Celda`
  - `NumeroCelda` (igual a `Celda`)
  - `CodigoSeleccion`
  - `Bloqueada` (`0|1`)
  - `Stock`
  - `TieneProducto`
  - `Producto`
  - `CodigoProducto`
  - `NombreProducto`
  - `PrecioVenta` (`number|null`)
  - `UrlImagen` (`string|null`)
  - `HashImagen` (`string|null`)
  - `FechaActualizacionImagen` (`Y-m-d H:i:s|null` en `America/La_Paz`)

`Meta`:
- `Origen`: `kiosko_unificado_v1`
- `SinPlanogramaActivo`: `true|false`
- `TotalCeldas`

Reglas cerradas:
1. `Bloqueada=1` si `CELDA.Estado != 1` o bloqueo activo en ocupacion (`TipoBloqueo='BLOQUEADA'`).
2. Precio unico desde `PLANOGRAMACELDA.PrecioVenta` del planograma activo mas reciente.
3. Si falta precio o no hay planograma activo: `TieneProducto=false` y `PrecioVenta=null`.
4. Si no hay imagen: `UrlImagen=null`, `HashImagen=null`.
5. `VersionCatalogo` = `sha1` deterministico lowercase.

Ejemplo:
```json
{
  "Ok": true,
  "Mensaje": "Catalogo recuperado correctamente",
  "Datos": {
    "Maquina": 1,
    "CodigoMaquina": "VM-001",
    "VersionCatalogo": "8c85fa1c2cc9adf39f5a3f7a65fcd5e4201ab97f",
    "Celdas": [
      {
        "Celda": 1,
        "NumeroCelda": 1,
        "CodigoSeleccion": "A1",
        "Bloqueada": 0,
        "Stock": 10,
        "TieneProducto": true,
        "Producto": 1,
        "CodigoProducto": "SKU-001",
        "NombreProducto": "Agua 600ml",
        "PrecioVenta": 0.01,
        "UrlImagen": "http://fileaws.pagofacil.com.bo/...",
        "HashImagen": "7710f163db0add29c53a576e4612406fe9f60004",
        "FechaActualizacionImagen": "2026-03-03 08:31:10"
      }
    ]
  },
  "Errores": [],
  "Meta": {
    "Origen": "kiosko_unificado_v1",
    "SinPlanogramaActivo": false,
    "TotalCeldas": 54
  }
}
```

### 3.2 Venta directa
`POST /api/kiosko/v1/venta`

Headers:
- `Authorization`
- `Clave-Idempotencia`

Body:
```json
{
  "Maquina": 1,
  "CodigoSeleccion": "A1",
  "Cantidad": 1
}
```

### 3.3 Reversa de venta
`POST /api/kiosko/v1/venta/reversa`

Body:
```json
{
  "Venta": 973,
  "Motivo": "vending_backend_failed"
}
```

### 3.4 Crear reserva
`POST /api/kiosko/v1/reserva`

Body:
```json
{
  "Maquina": 1,
  "CodigoSeleccion": "A1",
  "Cantidad": 1,
  "ExpiraSegundos": 120
}
```

### 3.5 Confirmar reserva
`POST /api/kiosko/v1/reserva/confirmar`

Body (uno de los dos):
```json
{ "Reserva": 36 }
```
o
```json
{ "ReservaExterna": "RSV-0036" }
```

### 3.6 Cancelar reserva
`POST /api/kiosko/v1/reserva/cancelar`

Body:
```json
{
  "Reserva": 36,
  "Motivo": "Usuario cancelo"
}
```

## 4. Contrato de errores

### 401
```json
{
  "Ok": false,
  "Mensaje": "No autorizado",
  "Datos": [],
  "Errores": [
    { "Codigo": "AUTH_401", "Campo": null, "Detalle": "Token invalido o expirado" }
  ],
  "Meta": {}
}
```

### 404
```json
{
  "Ok": false,
  "Mensaje": "Reserva no encontrada",
  "Datos": [],
  "Errores": [
    { "Codigo": "NEG_404", "Campo": null, "Detalle": "Reserva no encontrada" }
  ],
  "Meta": {}
}
```

### 409
```json
{
  "Ok": false,
  "Mensaje": "Conflicto de estado",
  "Datos": [],
  "Errores": [
    { "Codigo": "BUS_409", "Campo": null, "Detalle": "Reserva ya confirmada" }
  ],
  "Meta": {}
}
```

### 422
```json
{
  "Ok": false,
  "Mensaje": "Error de validacion",
  "Datos": [],
  "Errores": [
    { "Codigo": "VAL_422", "Campo": "Cantidad", "Detalle": "Debe ser mayor a 0" }
  ],
  "Meta": {}
}
```

## 5. Idempotencia obligatoria
Para endpoints transaccionales:
1. Misma `Clave-Idempotencia` + mismo body: replay exacto.
2. Misma `Clave-Idempotencia` + body distinto: `409`.
3. Respuesta replay incluye `X-Repeticion-Idempotencia: si`.

## 6. Rendimiento esperado
1. Catalogo sin N+1.
2. Objetivo operativo: `p95 < 600ms` para catalogo.
3. `VersionCatalogo` cambia cuando cambia stock, precio, bloqueo, producto o imagen.

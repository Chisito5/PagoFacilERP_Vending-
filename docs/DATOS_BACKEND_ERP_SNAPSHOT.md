# DATOS BACKEND ERP - SNAPSHOT

Fecha de corte: **2026-03-02 12:48:35**  
Base: **mysqlNegocio**

## 1) Conteos por tabla (actual)

| Tabla | Registros |
|---|---:|
| EMPRESA | 1 |
| USUARIO | 3 |
| ROL | 3 |
| MAQUINA | 1 |
| UBICACION | 1 |
| CELDA | 72 |
| EXISTENCIACELDA | 54 |
| PRODUCTO | 30 |
| PRODUCTOEMPRESA | 30 |
| LOTE | 30 |
| PLANOGRAMACELDA | 54 |
| RESERVA | 40 |
| RESERVADETALLE | 40 |
| VENTA | 46 |
| REPOSICION | 2 |
| REPOSICIONDETALLE | 2 |
| DEPOSITO | 1 |
| STOCKDEPOSITO | 0 |
| MOVIMIENTODEPOSITO | 0 |
| MAQUINAIMAGEN | 3 |
| ALERTA | 0 |
| REGLAALERTA | 0 |
| MERMA | 0 |
| MERMADETALLE | 0 |
| ANUNCIO | 0 |
| ANUNCIOMAQUINA | 0 |
| ANUNCIOPRODUCTO | 0 |
| IOTEVENTO | 0 |
| REPORTEGENERADO | 0 |
| IDEMPOTENCIA | 48 |

## 2) Resumen de precios, stock e integridad

### PRODUCTO.Precio
- Total productos: **30**
- Productos con `Precio = 0.01`: **30**
- Min precio: **0.01**
- Max precio: **0.01**

### PLANOGRAMACELDA.PrecioVenta
- Total planograma: **54**
- Filas con `PrecioVenta = 0.01`: **54**
- Min precio venta: **0.01**
- Max precio venta: **0.01**

### EXISTENCIACELDA
- Filas existencia: **54**
- Stock disponible total: **537**
- Stock reservado total: **0**
- Min disponible por fila: **7**
- Max disponible por fila: **10**

### Integridad capacidad
- Violaciones `(Disponible + Reservada) > CapacidadMaxima`: **0**

## 3) Datos operativos clave

### Maquinas
| Maquina | Codigo | Serie | Marca | Modelo | Conexion | UbicacionActual | Filas | Columnas | Estado | Ciudad | Departamento | Latitud | Longitud |
|---:|---|---|---|---|---|---:|---:|---:|---:|---|---|---:|---:|
| 1 | VM-001 | SN-0001 | GENERICA | MODELO-X | VENDING-001 | 1 | 6 | 9 | 1 | Santa Cruz de la Sierra | Santa Cruz | -17.7833000 | -63.1821000 |

### Reservas por estado
| Estado | Cantidad |
|---:|---:|
| 0 | 3 |
| 3 | 8 |
| 4 | 8 |
| 5 | 11 |
| 6 | 10 |

### Ventas por maquina
| Maquina | Ventas | Ingreso |
|---:|---:|---:|
| 1 | 46 | 617.53 |

### Fotos activas por maquina
| Maquina | FotosActivas |
|---:|---:|
| 1 | 3 |

## 4) Muestra de productos (primeros 20)

| Producto | CodigoProducto | NombreProducto | Precio | Estado |
|---:|---|---|---:|---:|
| 1 | SKU-001 | Agua 600ml | 0.01 | 1 |
| 2 | SKU-002 | Coca Cola 355ml | 0.01 | 1 |
| 3 | SKU-003 | Pepsi 355ml | 0.01 | 1 |
| 4 | SKU-004 | Sprite 355ml | 0.01 | 1 |
| 5 | SKU-005 | Fanta Naranja 355ml | 0.01 | 1 |
| 6 | SKU-006 | Agua Sin Gas 1L | 0.01 | 1 |
| 7 | SKU-007 | Agua con Gas 500ml | 0.01 | 1 |
| 8 | SKU-008 | Jugo Naranja 300ml | 0.01 | 1 |
| 9 | SKU-009 | Jugo Manzana 300ml | 0.01 | 1 |
| 10 | SKU-010 | Te Helado Limon 500ml | 0.01 | 1 |
| 11 | SKU-011 | Bebida Energetica 250ml | 0.01 | 1 |
| 12 | SKU-012 | Cafe Frio 250ml | 0.01 | 1 |
| 13 | SKU-013 | Papas Clasicas 45g | 0.01 | 1 |
| 14 | SKU-014 | Papas BBQ 45g | 0.01 | 1 |
| 15 | SKU-015 | Mani Salado 40g | 0.01 | 1 |
| 16 | SKU-016 | Barra Cereal Chocolate | 0.01 | 1 |
| 17 | SKU-017 | Barra Proteina Vainilla | 0.01 | 1 |
| 18 | SKU-018 | Galleta Chocolate 60g | 0.01 | 1 |
| 19 | SKU-019 | Galleta Vainilla 60g | 0.01 | 1 |
| 20 | SKU-020 | Chocolate Leche 40g | 0.01 | 1 |

## 5) APIs publicadas (resumen cuantitativo)

Total rutas API detectadas: **187**

| Modulo (prefijo) | Total |
|---|---:|
| maquina | 22 |
| producto | 15 |
| anuncio | 13 |
| merma | 12 |
| usuario | 11 |
| planogramacelda | 7 |
| analitica | 6 |
| tablero | 6 |
| deposito | 6 |
| empresa | 6 |
| lote | 6 |
| productofamilia | 6 |
| productogrupo | 6 |
| productoimagen | 6 |
| productosubgrupo | 6 |
| reglaalerta | 6 |
| alerta | 5 |
| auth | 5 |
| reposicion | 5 |
| aprobaciones | 4 |
| reporte | 4 |
| venta | 4 |
| reserva | 3 |
| stock | 3 |
| integracion (iot) | 3 |
| existenciacelda | 2 |
| celda | 6 |
| estado | 1 |
| tipoempresa | 1 |
| auditoria | 1 |

## 6) Columnas actuales de tablas clave

### MAQUINA
- Maquina, CodigoMaquina, NumeroSerie, Marca, Modelo, IdentificadorConexion, UbicacionActual, FilasMatriz, ColumnasMatriz, Estado, Usr, UsrFecha, UsrHora

### UBICACION
- Ubicacion, Empresa, NombreUbicacion, Departamento, Ciudad, Zona, Direccion, Referencia, Latitud, Longitud, ContactoNombre, ContactoTelefono, TipoAlimentacionElectrica, Estado, Usr, UsrFecha, UsrHora

### CELDA
- Celda, Maquina, CodigoSeleccion, Fila, Columna, CapacidadMaxima, AnchoMaximoMm, AltoMaximoMm, ProfundidadMaximaMm, PesoMaximoGr, PermiteGiro, Estado, Usr, UsrFecha, UsrHora

### EXISTENCIACELDA
- ExistenciaCelda, Celda, ProductoEmpresa, Lote, CantidadDisponible, CantidadReservada, Estado, Usr, UsrFecha, UsrHora

### PRODUCTO
- Producto, Empresa, CodigoSku, CodigoProducto, CodigoBarra, NombreProducto, Precio, AnchoMm, AltoMm, ProfundidadMm, Orientacion, PermiteGiro, UnidadEmpaque, Descripcion, Marca, ContenidoCantidad, UnidadMedidaContenido, PesoGramos, PesoGr, SubgrupoProducto, Estado, Usr, UsrFecha, UsrHora

### PLANOGRAMACELDA
- PlanogramaCelda, Planograma, Celda, ProductoEmpresa, PrecioVenta, StockMinimo, StockMaximo, PlanogramaCeldaPrincipal, Estado, Usr, UsrFecha, UsrHora

### RESERVA
- Reserva, ReservaExterna, Maquina, FechaHoraReserva, ExpiraEn, Estado, Usr, UsrFecha, UsrHora

### VENTA
- Venta, Maquina, Celda, ProductoEmpresa, Lote, Cantidad, PrecioUnitario, FechaVenta, Estado, Usr, UsrFecha, UsrHora

### DEPOSITO
- Deposito, Empresa, NombreDeposito, Descripcion, Estado, Usr, UsrFecha, UsrHora

### STOCKDEPOSITO
- StockDeposito, Deposito, Producto, Lote, CantidadDisponible, CantidadReservada, Estado, Usr, UsrFecha, UsrHora

### MAQUINAIMAGEN
- MaquinaImagen, Maquina, TipoFoto, RutaImagen, Orden, Observacion, Estado, Usr, UsrFecha, UsrHora


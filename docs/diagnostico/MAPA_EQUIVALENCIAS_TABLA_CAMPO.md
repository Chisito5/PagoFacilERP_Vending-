# MAPA EQUIVALENCIAS TABLA/CAMPO

Objetivo: documentar equivalencias entre naming actual y naming objetivo SYSCOOP para migracion gradual sin ruptura.

| Dominio | Objeto actual | Campo actual | Equivalencia objetivo | Nota |
|---|---|---|---|---|
| Producto | `PRODUCTO` | `CodigoSku` | `CodigoProducto` | Mantener ambos en fase de convivencia |
| Producto | `PRODUCTO` | `PesoGramos` | `PesoGr` | Normalizar en payloads nuevos |
| Reserva | `RESERVA` | `ReservaExterna` | `CodigoReservaExterna` | Alias logico recomendado en adaptador |
| Maquina | `MAQUINA` | `UbicacionActual` | `Ubicacion` | En dominio operativo usar nombre de entidad |
| Venta | `VENTA` | `PrecioUnitario` | `PrecioVentaUnitario` | Mantener actual por compatibilidad SQL |
| Imagenes | `PRODUCTOIMAGEN` | `RutaImagen` | `RutaObjeto` | Persistir key, resolver URL en servicio |
| Sesion API | `SESIONAPI` | `HashTokenAcceso` | `TokenAccesoHash` | Cambio solo logico en adaptador |
| Estados | `ESTADO` | `CodigoEstado` | `CodigoEstado` | Ya alineado: entidad + codigo |

## Convencion para APIs nuevas

- Exponer nombres funcionales estables al frontend.
- Resolver diferencias de nombre en capa service/adaptador.
- Evitar exponer nombres tecnicos de transicion.

DROP VIEW IF EXISTS VW_STOCK_CELDA_DETALLE;
CREATE VIEW VW_STOCK_CELDA_DETALLE AS
SELECT
    c.Maquina,
    c.Celda,
    c.CodigoSeleccion,
    c.Fila,
    c.Columna,
    c.CapacidadMaxima,
    ec.ExistenciaCelda,
    ec.ProductoEmpresa,
    p.Producto,
    p.CodigoProducto,
    p.NombreProducto,
    ec.Lote,
    ec.CantidadDisponible,
    ec.CantidadReservada,
    (ec.CantidadDisponible + ec.CantidadReservada) AS CantidadTotal
FROM CELDA c
LEFT JOIN EXISTENCIACELDA ec ON ec.Celda = c.Celda
LEFT JOIN PRODUCTOEMPRESA pe ON pe.ProductoEmpresa = ec.ProductoEmpresa
LEFT JOIN PRODUCTO p ON p.Producto = pe.Producto;

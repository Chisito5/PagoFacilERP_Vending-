DROP VIEW IF EXISTS VW_TRANSACCION_COMERCIAL;
CREATE VIEW VW_TRANSACCION_COMERCIAL AS
SELECT
    'VENTA' AS TipoTransaccion,
    v.Venta AS IdTransaccion,
    v.Maquina,
    v.Celda,
    v.ProductoEmpresa,
    v.Lote,
    v.Cantidad,
    v.PrecioUnitario,
    (v.Cantidad * v.PrecioUnitario) AS Importe,
    v.FechaVenta AS FechaHora,
    v.Estado,
    v.Usr,
    v.UsrFecha,
    v.UsrHora
FROM VENTA v
UNION ALL
SELECT
    'REPOSICION' AS TipoTransaccion,
    r.Reposicion AS IdTransaccion,
    r.Maquina,
    NULL AS Celda,
    NULL AS ProductoEmpresa,
    NULL AS Lote,
    NULL AS Cantidad,
    NULL AS PrecioUnitario,
    NULL AS Importe,
    r.FechaHoraReposicion AS FechaHora,
    r.Estado,
    r.Usr,
    r.UsrFecha,
    r.UsrHora
FROM REPOSICION r
UNION ALL
SELECT
    'RESERVA' AS TipoTransaccion,
    rs.Reserva AS IdTransaccion,
    rs.Maquina,
    NULL AS Celda,
    NULL AS ProductoEmpresa,
    NULL AS Lote,
    NULL AS Cantidad,
    NULL AS PrecioUnitario,
    NULL AS Importe,
    rs.FechaHoraReserva AS FechaHora,
    rs.Estado,
    rs.Usr,
    rs.UsrFecha,
    rs.UsrHora
FROM RESERVA rs;

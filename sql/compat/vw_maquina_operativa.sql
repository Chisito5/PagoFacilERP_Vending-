DROP VIEW IF EXISTS VW_MAQUINA_OPERATIVA;
CREATE VIEW VW_MAQUINA_OPERATIVA AS
SELECT
    m.Maquina,
    m.CodigoMaquina,
    m.NumeroSerie,
    m.Marca,
    m.Modelo,
    m.Estado AS EstadoMaquina,
    m.UbicacionActual AS Ubicacion,
    u.Empresa,
    u.NombreUbicacion,
    u.Departamento,
    u.Ciudad,
    u.Zona,
    u.Direccion,
    u.Latitud,
    u.Longitud,
    ti.TipoInternet,
    ti.CodigoTipoInternet,
    ti.NombreTipoInternet,
    tli.TipoLugarInstalacion,
    tli.CodigoTipoLugar,
    tli.NombreTipoLugar
FROM MAQUINA m
LEFT JOIN UBICACION u ON u.Ubicacion = m.UbicacionActual
LEFT JOIN TIPOINTERNET ti ON ti.TipoInternet = m.TipoInternet
LEFT JOIN TIPOLUGARINSTALACION tli ON tli.TipoLugarInstalacion = u.TipoLugarInstalacion;

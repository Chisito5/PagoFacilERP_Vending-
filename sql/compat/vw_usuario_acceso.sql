DROP VIEW IF EXISTS VW_USUARIO_ACCESO;
CREATE VIEW VW_USUARIO_ACCESO AS
SELECT
    u.Usuario,
    u.NombreUsuario,
    u.Nombres,
    u.Empresa,
    ur.Rol,
    r.CodigoRol,
    r.NombreRol,
    um.Maquina,
    um.Estado AS EstadoAsignacion
FROM USUARIO u
LEFT JOIN USUARIOROL ur ON ur.Usuario = u.Usuario
LEFT JOIN ROL r ON r.Rol = ur.Rol
LEFT JOIN USUARIOMAQUINA um ON um.Usuario = u.Usuario;

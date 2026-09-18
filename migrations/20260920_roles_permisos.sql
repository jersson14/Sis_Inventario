-- Migracion: permiso "Anular documentos" para separar vendedor de encargado
-- Fecha: 2026-09-20
-- Idempotente.
--
-- Hasta ahora cualquier usuario con Ventas o Compras podia anular. Desde esta
-- version anular exige el permiso 16 (o ser administrador). Para no cambiar
-- el comportamiento de los usuarios que ya existen, se les asigna a quienes
-- hoy tienen Ventas (4) o Compras (3). Los vendedores nuevos no lo reciben.

INSERT INTO `permiso` (`idpermiso`, `nombre`)
SELECT 16, 'Anular documentos' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `permiso` WHERE `idpermiso` = 16);

INSERT INTO `usuario_permiso` (`idusuario`, `idpermiso`)
SELECT DISTINCT up.`idusuario`, 16
FROM `usuario_permiso` up
WHERE up.`idpermiso` IN (3, 4)
  AND NOT EXISTS (SELECT 1 FROM `usuario_permiso` x WHERE x.`idusuario` = up.`idusuario` AND x.`idpermiso` = 16);

-- Migracion: control de precios del vendedor y arqueo ciego
-- Fecha: 2026-09-21
-- Idempotente.
--
-- Permiso 17 "Cambiar precios y descuentos": sin el, la venta y la cotizacion
-- solo aceptan el precio de lista (con presentacion, talla/color y precio por
-- mayor) y descuento 0. Para no cambiar nada a los usuarios que ya existen, se
-- asigna a quienes hoy tienen Ventas (4). Los vendedores nuevos no lo reciben.
--
-- configuracion_empresa.arqueo_ciego: 1 = quien no es administrador cierra su
-- caja sin ver el efectivo esperado ni la diferencia (solo el administrador).

INSERT INTO `permiso` (`idpermiso`, `nombre`)
SELECT 17, 'Cambiar precios y descuentos' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `permiso` WHERE `idpermiso` = 17);

INSERT INTO `usuario_permiso` (`idusuario`, `idpermiso`)
SELECT DISTINCT up.`idusuario`, 17
FROM `usuario_permiso` up
WHERE up.`idpermiso` = 4
  AND NOT EXISTS (SELECT 1 FROM `usuario_permiso` x WHERE x.`idusuario` = up.`idusuario` AND x.`idpermiso` = 17);

SET @schema_name = DATABASE();
SET @c = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME='configuracion_empresa' AND COLUMN_NAME='arqueo_ciego');
SET @s = IF(@c=0, 'ALTER TABLE `configuracion_empresa` ADD COLUMN `arqueo_ciego` TINYINT(1) NOT NULL DEFAULT 1', 'SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

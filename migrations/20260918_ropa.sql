-- Migracion: Ropa (variantes talla/color con stock propio, temporada y coleccion)
-- Fecha: 2026-09-18
-- Idempotente.
--
-- articulo_variante: cada combinacion talla/color de un articulo, con stock,
--   codigo de barras y precio propios (precio 0 = usa el del articulo).
--   En un articulo con variantes, articulo.stock es la SUMA de sus variantes:
--   kardex, alertas y reportes siguen trabajando a nivel articulo.
-- idvariante en detalles y ajustes: de que talla/color salio o entro.
-- Triggers: un UPDATE multitabla mueve el stock del articulo y el de la
--   variante en la misma sentencia (sin BEGIN/END, no depende de DELIMITER).
--   Con idvariante NULL el LEFT JOIN no encuentra fila y solo cambia el articulo.

SET @schema_name = DATABASE();

CREATE TABLE IF NOT EXISTS `articulo_variante` (
  `idvariante` INT(11) NOT NULL AUTO_INCREMENT,
  `idarticulo` INT(11) NOT NULL,
  `talla` VARCHAR(20) NOT NULL DEFAULT '',
  `color` VARCHAR(30) NOT NULL DEFAULT '',
  `codigo` VARCHAR(50) DEFAULT NULL,
  `stock` DECIMAL(14,3) NOT NULL DEFAULT 0.000,
  `stock_minimo` DECIMAL(14,3) NOT NULL DEFAULT 0.000,
  `precio_venta` DECIMAL(11,2) NOT NULL DEFAULT 0.00,
  `orden` INT(11) NOT NULL DEFAULT 0,
  `condicion` TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`idvariante`),
  UNIQUE KEY `uq_variante_articulo_talla_color` (`idarticulo`, `talla`, `color`),
  KEY `idx_variante_codigo` (`codigo`),
  CONSTRAINT `fk_variante_articulo` FOREIGN KEY (`idarticulo`) REFERENCES `articulo` (`idarticulo`) ON DELETE CASCADE ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

SET @c = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME='detalle_venta' AND COLUMN_NAME='idvariante');
SET @s = IF(@c=0, 'ALTER TABLE `detalle_venta` ADD COLUMN `idvariante` INT(11) DEFAULT NULL AFTER `idpresentacion`, ADD KEY `idx_detventa_variante` (`idvariante`)', 'SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @c = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME='detalle_ingreso' AND COLUMN_NAME='idvariante');
SET @s = IF(@c=0, 'ALTER TABLE `detalle_ingreso` ADD COLUMN `idvariante` INT(11) DEFAULT NULL AFTER `idpresentacion`, ADD KEY `idx_detingreso_variante` (`idvariante`)', 'SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @c = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME='detalle_cotizacion' AND COLUMN_NAME='idvariante');
SET @s = IF(@c=0, 'ALTER TABLE `detalle_cotizacion` ADD COLUMN `idvariante` INT(11) DEFAULT NULL AFTER `idpresentacion`', 'SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @c = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME='ajuste_inventario' AND COLUMN_NAME='idvariante');
SET @s = IF(@c=0, 'ALTER TABLE `ajuste_inventario` ADD COLUMN `idvariante` INT(11) DEFAULT NULL AFTER `idarticulo`', 'SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @c = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME='articulo' AND COLUMN_NAME='temporada');
SET @s = IF(@c=0, 'ALTER TABLE `articulo` ADD COLUMN `temporada` VARCHAR(40) DEFAULT NULL AFTER `descripcion`, ADD COLUMN `coleccion` VARCHAR(40) DEFAULT NULL AFTER `temporada`', 'SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

DROP TRIGGER IF EXISTS `tr_updStockIngreso`;
CREATE TRIGGER `tr_updStockIngreso` AFTER INSERT ON `detalle_ingreso` FOR EACH ROW
  UPDATE articulo a
  LEFT JOIN articulo_variante v ON v.idvariante = NEW.idvariante AND v.idarticulo = NEW.idarticulo
  SET a.stock = a.stock + (NEW.cantidad * NEW.factor),
      v.stock = v.stock + (NEW.cantidad * NEW.factor)
  WHERE a.idarticulo = NEW.idarticulo;

DROP TRIGGER IF EXISTS `tr_udpStockVenta`;
CREATE TRIGGER `tr_udpStockVenta` AFTER INSERT ON `detalle_venta` FOR EACH ROW
  UPDATE articulo a
  LEFT JOIN articulo_variante v ON v.idvariante = NEW.idvariante AND v.idarticulo = NEW.idarticulo
  SET a.stock = a.stock - (NEW.cantidad * NEW.factor),
      v.stock = v.stock - (NEW.cantidad * NEW.factor)
  WHERE a.idarticulo = NEW.idarticulo;

INSERT IGNORE INTO `migracion` (`archivo`) VALUES ('20260918_ropa.sql');

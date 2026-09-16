-- Migracion: Ferreteria (fracciones, presentaciones y precio por mayor)
-- Fecha: 2026-09-16
-- Idempotente.
--
-- 1. unidad_medida.permite_fraccion: la unidad decide si se vende con
--    decimales (1.5 m, 0.75 kg). Unidades, cajas y paquetes no.
-- 2. articulo_presentacion: empaques con equivalencia ("Caja x100" = 100
--    unidades base) y precio propio.
-- 3. articulo_precio_escala: precio unitario por mayor desde cierta cantidad.
-- 4. factor/idpresentacion en los detalles: la linea se guarda en la
--    presentacion vendida (2 cajas a 50.00) para que los importes sean
--    exactos; el stock se mueve con cantidad * factor. factor=1 en todo lo
--    existente, asi que los datos historicos no cambian.

SET @schema_name = DATABASE();

-- 1. Fraccion por unidad de medida -----------------------------------------
SET @c = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME='unidad_medida' AND COLUMN_NAME='permite_fraccion');
SET @s = IF(@c=0, 'ALTER TABLE `unidad_medida` ADD COLUMN `permite_fraccion` TINYINT(1) NOT NULL DEFAULT 0 AFTER `descripcion`', 'SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;
-- Valores iniciales solo al crear la columna, para no pisar lo que el usuario ya configuro
SET @s = IF(@c=0, 'UPDATE `unidad_medida` SET `permite_fraccion`=1 WHERE LOWER(`abreviatura`) IN (''kg'',''g'',''gr'',''lt'',''l'',''ml'',''m'',''cm'',''mm'',''gal'',''pie'',''pulg'',''m2'',''m3'')', 'SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 2. Presentaciones / equivalencias ----------------------------------------
CREATE TABLE IF NOT EXISTS `articulo_presentacion` (
  `idpresentacion` INT(11) NOT NULL AUTO_INCREMENT,
  `idarticulo` INT(11) NOT NULL,
  `nombre` VARCHAR(60) NOT NULL,
  `factor` DECIMAL(14,3) NOT NULL COMMENT 'Unidades base que contiene',
  `precio_venta` DECIMAL(11,2) NOT NULL DEFAULT 0.00,
  `precio_compra` DECIMAL(11,2) NOT NULL DEFAULT 0.00,
  `codigo` VARCHAR(50) DEFAULT NULL,
  `condicion` TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`idpresentacion`),
  UNIQUE KEY `uq_presentacion_articulo_nombre` (`idarticulo`, `nombre`),
  KEY `idx_presentacion_codigo` (`codigo`),
  CONSTRAINT `fk_presentacion_articulo` FOREIGN KEY (`idarticulo`) REFERENCES `articulo` (`idarticulo`) ON DELETE CASCADE ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- 3. Precio por mayor --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `articulo_precio_escala` (
  `idescala` INT(11) NOT NULL AUTO_INCREMENT,
  `idarticulo` INT(11) NOT NULL,
  `cantidad_minima` DECIMAL(14,3) NOT NULL,
  `precio` DECIMAL(11,2) NOT NULL,
  PRIMARY KEY (`idescala`),
  UNIQUE KEY `uq_escala_articulo_cantidad` (`idarticulo`, `cantidad_minima`),
  CONSTRAINT `fk_escala_articulo` FOREIGN KEY (`idarticulo`) REFERENCES `articulo` (`idarticulo`) ON DELETE CASCADE ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- 4. Presentacion en los detalles --------------------------------------------
SET @c = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME='detalle_venta' AND COLUMN_NAME='factor');
SET @s = IF(@c=0, 'ALTER TABLE `detalle_venta` ADD COLUMN `idpresentacion` INT(11) DEFAULT NULL AFTER `idarticulo`, ADD COLUMN `factor` DECIMAL(14,3) NOT NULL DEFAULT 1.000 AFTER `cantidad`', 'SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @c = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME='detalle_ingreso' AND COLUMN_NAME='factor');
SET @s = IF(@c=0, 'ALTER TABLE `detalle_ingreso` ADD COLUMN `idpresentacion` INT(11) DEFAULT NULL AFTER `idarticulo`, ADD COLUMN `factor` DECIMAL(14,3) NOT NULL DEFAULT 1.000 AFTER `cantidad`', 'SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @c = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME='detalle_cotizacion' AND COLUMN_NAME='factor');
SET @s = IF(@c=0, 'ALTER TABLE `detalle_cotizacion` ADD COLUMN `idpresentacion` INT(11) DEFAULT NULL AFTER `idarticulo`, ADD COLUMN `factor` DECIMAL(14,3) NOT NULL DEFAULT 1.000 AFTER `cantidad`', 'SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 5. Triggers de stock: ahora multiplican por el factor ----------------------
-- Cuerpo de una sola sentencia (sin BEGIN/END) para no depender de DELIMITER.
DROP TRIGGER IF EXISTS `tr_updStockIngreso`;
CREATE TRIGGER `tr_updStockIngreso` AFTER INSERT ON `detalle_ingreso` FOR EACH ROW
  UPDATE articulo SET stock = stock + (NEW.cantidad * NEW.factor) WHERE articulo.idarticulo = NEW.idarticulo;

DROP TRIGGER IF EXISTS `tr_udpStockVenta`;
CREATE TRIGGER `tr_udpStockVenta` AFTER INSERT ON `detalle_venta` FOR EACH ROW
  UPDATE articulo SET stock = stock - (NEW.cantidad * NEW.factor) WHERE articulo.idarticulo = NEW.idarticulo;

INSERT IGNORE INTO `migracion` (`archivo`) VALUES ('20260916_ferreteria.sql');

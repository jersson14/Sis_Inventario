-- Migracion: Abarrotes (lotes y fechas de vencimiento)
-- Fecha: 2026-09-17
-- Idempotente.
--
-- lote: stock con fecha de vencimiento y codigo de lote. Se crea al comprar o
--   al registrar una entrada. La suma de lotes nunca supera articulo.stock; la
--   diferencia es stock "sin lote" (historico o sin fecha).
-- lote_movimiento: cada consumo o reposicion de un lote, enlazado al detalle de
--   venta, al ingreso o al ajuste que lo produjo. Permite que anular una venta
--   devuelva la mercaderia a los mismos lotes de los que salio.
-- configuracion_empresa.dias_alerta_vencimiento: con cuanta anticipacion avisar.

SET @schema_name = DATABASE();

CREATE TABLE IF NOT EXISTS `lote` (
  `idlote` INT(11) NOT NULL AUTO_INCREMENT,
  `idarticulo` INT(11) NOT NULL,
  `codigo_lote` VARCHAR(40) DEFAULT NULL,
  `fecha_vencimiento` DATE DEFAULT NULL,
  `cantidad_inicial` DECIMAL(14,3) NOT NULL,
  `stock` DECIMAL(14,3) NOT NULL,
  `costo_unitario` DECIMAL(11,2) NOT NULL DEFAULT 0.00,
  `idingreso` INT(11) DEFAULT NULL,
  `idajuste` INT(11) DEFAULT NULL,
  `fecha_ingreso` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `condicion` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '0 = anulado junto con su compra',
  PRIMARY KEY (`idlote`),
  KEY `idx_lote_articulo_vence` (`idarticulo`, `fecha_vencimiento`),
  KEY `idx_lote_vence` (`fecha_vencimiento`),
  KEY `idx_lote_ingreso` (`idingreso`),
  CONSTRAINT `fk_lote_articulo` FOREIGN KEY (`idarticulo`) REFERENCES `articulo` (`idarticulo`) ON DELETE CASCADE ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `lote_movimiento` (
  `idmovimiento` INT(11) NOT NULL AUTO_INCREMENT,
  `idlote` INT(11) NOT NULL,
  `tipo` VARCHAR(20) NOT NULL COMMENT 'VENTA | AJUSTE',
  `cantidad` DECIMAL(14,3) NOT NULL COMMENT 'Unidades base retiradas del lote',
  `iddetalle_venta` INT(11) DEFAULT NULL,
  `idventa` INT(11) DEFAULT NULL,
  `idajuste` INT(11) DEFAULT NULL,
  `fecha_hora` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`idmovimiento`),
  KEY `idx_lotemov_lote` (`idlote`),
  KEY `idx_lotemov_venta` (`idventa`),
  CONSTRAINT `fk_lotemov_lote` FOREIGN KEY (`idlote`) REFERENCES `lote` (`idlote`) ON DELETE CASCADE ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

SET @c = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME='configuracion_empresa' AND COLUMN_NAME='dias_alerta_vencimiento');
SET @s = IF(@c=0, 'ALTER TABLE `configuracion_empresa` ADD COLUMN `dias_alerta_vencimiento` INT(11) NOT NULL DEFAULT 30 AFTER `tipo_negocio`', 'SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

INSERT IGNORE INTO `migracion` (`archivo`) VALUES ('20260917_abarrotes.sql');

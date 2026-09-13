-- Migracion: Cotizaciones / proformas
-- Fecha: 2026-09-13
-- Idempotente.

CREATE TABLE IF NOT EXISTS `cotizacion` (
  `idcotizacion` INT(11) NOT NULL AUTO_INCREMENT,
  `idcliente` INT(11) NOT NULL,
  `idusuario` INT(11) NOT NULL,
  `numero` VARCHAR(14) NOT NULL,
  `fecha_hora` DATETIME NOT NULL,
  `fecha_validez` DATE NOT NULL,
  `impuesto` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  `total` DECIMAL(11,2) NOT NULL DEFAULT 0.00,
  `estado` VARCHAR(20) NOT NULL DEFAULT 'PENDIENTE' COMMENT 'PENDIENTE | ACEPTADA | RECHAZADA | VENCIDA | CONVERTIDA',
  `idventa` INT(11) DEFAULT NULL,
  `observacion` VARCHAR(300) DEFAULT NULL,
  `condiciones` VARCHAR(300) DEFAULT NULL,
  `fecha_creacion` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`idcotizacion`),
  UNIQUE KEY `uq_cotizacion_numero` (`numero`),
  KEY `fk_cotizacion_cliente_idx` (`idcliente`),
  KEY `fk_cotizacion_usuario_idx` (`idusuario`),
  KEY `idx_cotizacion_fecha` (`fecha_hora`),
  KEY `idx_cotizacion_estado` (`estado`),
  CONSTRAINT `fk_cotizacion_cliente` FOREIGN KEY (`idcliente`) REFERENCES `persona` (`idpersona`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_cotizacion_usuario` FOREIGN KEY (`idusuario`) REFERENCES `usuario` (`idusuario`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `detalle_cotizacion` (
  `iddetalle_cotizacion` INT(11) NOT NULL AUTO_INCREMENT,
  `idcotizacion` INT(11) NOT NULL,
  `idarticulo` INT(11) NOT NULL,
  `cantidad` DECIMAL(14,3) NOT NULL,
  `precio` DECIMAL(11,2) NOT NULL,
  `descuento` DECIMAL(11,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`iddetalle_cotizacion`),
  KEY `fk_detcot_cotizacion_idx` (`idcotizacion`),
  KEY `fk_detcot_articulo_idx` (`idarticulo`),
  CONSTRAINT `fk_detcot_cotizacion` FOREIGN KEY (`idcotizacion`) REFERENCES `cotizacion` (`idcotizacion`) ON DELETE CASCADE ON UPDATE NO ACTION,
  CONSTRAINT `fk_detcot_articulo` FOREIGN KEY (`idarticulo`) REFERENCES `articulo` (`idarticulo`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Serie de cotizacion configurable
SET @schema_name = DATABASE();
SET @c = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME='configuracion_empresa' AND COLUMN_NAME='serie_cotizacion');
SET @s = IF(@c=0, 'ALTER TABLE `configuracion_empresa` ADD COLUMN `serie_cotizacion` VARCHAR(10) NOT NULL DEFAULT ''COT'' AFTER `serie_ticket`', 'SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

INSERT IGNORE INTO `migracion` (`archivo`) VALUES ('20260913_cotizaciones.sql');

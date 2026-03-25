-- Migracion: Fase comercial (empresa, kardex/alertas, caja, cuentas, backup)
-- Fecha: 2026-03-21
-- Script idempotente para ejecucion segura en reintentos

START TRANSACTION;

SET @schema_name = DATABASE();

CREATE TABLE IF NOT EXISTS `configuracion_empresa` (
  `idconfig` int(11) NOT NULL AUTO_INCREMENT,
  `nombre_comercial` varchar(120) NOT NULL,
  `razon_social` varchar(150) DEFAULT NULL,
  `ruc` varchar(20) DEFAULT NULL,
  `direccion` varchar(180) DEFAULT NULL,
  `telefono` varchar(30) DEFAULT NULL,
  `celular` varchar(30) DEFAULT NULL,
  `correo` varchar(120) DEFAULT NULL,
  `web` varchar(120) DEFAULT NULL,
  `logo` varchar(100) DEFAULT NULL,
  `color_primario` varchar(10) NOT NULL DEFAULT '#0f766e',
  `color_secundario` varchar(10) NOT NULL DEFAULT '#f59e0b',
  `serie_boleta` varchar(10) NOT NULL DEFAULT 'B001',
  `serie_factura` varchar(10) NOT NULL DEFAULT 'F001',
  `serie_ticket` varchar(10) NOT NULL DEFAULT 'T001',
  `impuesto_default` decimal(5,2) NOT NULL DEFAULT '18.00',
  `moneda` varchar(10) NOT NULL DEFAULT 'PEN',
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`idconfig`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO `configuracion_empresa`
(`idconfig`,`nombre_comercial`,`razon_social`,`ruc`,`direccion`,`telefono`,`celular`,`correo`,`web`,`logo`,`color_primario`,`color_secundario`,`serie_boleta`,`serie_factura`,`serie_ticket`,`impuesto_default`,`moneda`)
SELECT 1,'PERNO CENTRO','PERNO CENTRO "SEÑOR DE HUANCA"','20603558422','Bar. Santa Rosa S/N (al costado del Grifo Wari), Abancay - Apurimac','932381391','932381391','ventas@pernocentro.com','','logo1.jpeg','#0f766e','#f59e0b','B001','F001','T001',18.00,'PEN'
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM configuracion_empresa WHERE idconfig=1);

SET @col_stock_minimo_exists = (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA=@schema_name
    AND TABLE_NAME='articulo'
    AND COLUMN_NAME='stock_minimo'
);

SET @sql_add_stock_minimo = IF(
  @col_stock_minimo_exists = 0,
  'ALTER TABLE `articulo` ADD COLUMN `stock_minimo` decimal(14,3) NOT NULL DEFAULT 1.000 AFTER `stock`',
  'SELECT 1'
);
PREPARE stmt FROM @sql_add_stock_minimo;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS `caja_diaria` (
  `idcaja` int(11) NOT NULL AUTO_INCREMENT,
  `idusuario` int(11) NOT NULL,
  `fecha_apertura` datetime NOT NULL,
  `fecha_cierre` datetime DEFAULT NULL,
  `monto_apertura` decimal(14,2) NOT NULL DEFAULT '0.00',
  `monto_cierre_sistema` decimal(14,2) DEFAULT NULL,
  `monto_cierre_real` decimal(14,2) DEFAULT NULL,
  `diferencia` decimal(14,2) DEFAULT NULL,
  `estado` varchar(20) NOT NULL DEFAULT 'ABIERTA',
  `observacion` varchar(200) DEFAULT NULL,
  PRIMARY KEY (`idcaja`),
  KEY `fk_caja_usuario_idx` (`idusuario`),
  CONSTRAINT `fk_caja_usuario` FOREIGN KEY (`idusuario`) REFERENCES `usuario` (`idusuario`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `caja_movimiento` (
  `idmovimiento` int(11) NOT NULL AUTO_INCREMENT,
  `idcaja` int(11) NOT NULL,
  `idusuario` int(11) NOT NULL,
  `tipo` varchar(12) NOT NULL,
  `concepto` varchar(120) NOT NULL,
  `monto` decimal(14,2) NOT NULL,
  `fecha_hora` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`idmovimiento`),
  KEY `fk_cajamov_caja_idx` (`idcaja`),
  KEY `fk_cajamov_usuario_idx` (`idusuario`),
  CONSTRAINT `fk_cajamov_caja` FOREIGN KEY (`idcaja`) REFERENCES `caja_diaria` (`idcaja`) ON DELETE CASCADE ON UPDATE NO ACTION,
  CONSTRAINT `fk_cajamov_usuario` FOREIGN KEY (`idusuario`) REFERENCES `usuario` (`idusuario`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `cuenta_cobrar` (
  `idcuenta_cobrar` int(11) NOT NULL AUTO_INCREMENT,
  `idcliente` int(11) NOT NULL,
  `idventa` int(11) DEFAULT NULL,
  `fecha_emision` date NOT NULL,
  `fecha_vencimiento` date NOT NULL,
  `documento_ref` varchar(40) DEFAULT NULL,
  `monto_total` decimal(14,2) NOT NULL,
  `saldo` decimal(14,2) NOT NULL,
  `estado` varchar(20) NOT NULL DEFAULT 'PENDIENTE',
  `observacion` varchar(200) DEFAULT NULL,
  PRIMARY KEY (`idcuenta_cobrar`),
  KEY `fk_cobrar_cliente_idx` (`idcliente`),
  KEY `fk_cobrar_venta_idx` (`idventa`),
  CONSTRAINT `fk_cobrar_cliente` FOREIGN KEY (`idcliente`) REFERENCES `persona` (`idpersona`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_cobrar_venta` FOREIGN KEY (`idventa`) REFERENCES `venta` (`idventa`) ON DELETE SET NULL ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `pago_cuenta_cobrar` (
  `idpago_cobrar` int(11) NOT NULL AUTO_INCREMENT,
  `idcuenta_cobrar` int(11) NOT NULL,
  `idusuario` int(11) NOT NULL,
  `fecha_hora` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `monto` decimal(14,2) NOT NULL,
  `medio_pago` varchar(30) DEFAULT NULL,
  `observacion` varchar(150) DEFAULT NULL,
  PRIMARY KEY (`idpago_cobrar`),
  KEY `fk_pagocobrar_cuenta_idx` (`idcuenta_cobrar`),
  KEY `fk_pagocobrar_usuario_idx` (`idusuario`),
  CONSTRAINT `fk_pagocobrar_cuenta` FOREIGN KEY (`idcuenta_cobrar`) REFERENCES `cuenta_cobrar` (`idcuenta_cobrar`) ON DELETE CASCADE ON UPDATE NO ACTION,
  CONSTRAINT `fk_pagocobrar_usuario` FOREIGN KEY (`idusuario`) REFERENCES `usuario` (`idusuario`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `cuenta_pagar` (
  `idcuenta_pagar` int(11) NOT NULL AUTO_INCREMENT,
  `idproveedor` int(11) NOT NULL,
  `idingreso` int(11) DEFAULT NULL,
  `fecha_emision` date NOT NULL,
  `fecha_vencimiento` date NOT NULL,
  `documento_ref` varchar(40) DEFAULT NULL,
  `monto_total` decimal(14,2) NOT NULL,
  `saldo` decimal(14,2) NOT NULL,
  `estado` varchar(20) NOT NULL DEFAULT 'PENDIENTE',
  `observacion` varchar(200) DEFAULT NULL,
  PRIMARY KEY (`idcuenta_pagar`),
  KEY `fk_pagar_proveedor_idx` (`idproveedor`),
  KEY `fk_pagar_ingreso_idx` (`idingreso`),
  CONSTRAINT `fk_pagar_proveedor` FOREIGN KEY (`idproveedor`) REFERENCES `persona` (`idpersona`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_pagar_ingreso` FOREIGN KEY (`idingreso`) REFERENCES `ingreso` (`idingreso`) ON DELETE SET NULL ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `pago_cuenta_pagar` (
  `idpago_pagar` int(11) NOT NULL AUTO_INCREMENT,
  `idcuenta_pagar` int(11) NOT NULL,
  `idusuario` int(11) NOT NULL,
  `fecha_hora` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `monto` decimal(14,2) NOT NULL,
  `medio_pago` varchar(30) DEFAULT NULL,
  `observacion` varchar(150) DEFAULT NULL,
  PRIMARY KEY (`idpago_pagar`),
  KEY `fk_pagopagar_cuenta_idx` (`idcuenta_pagar`),
  KEY `fk_pagopagar_usuario_idx` (`idusuario`),
  CONSTRAINT `fk_pagopagar_cuenta` FOREIGN KEY (`idcuenta_pagar`) REFERENCES `cuenta_pagar` (`idcuenta_pagar`) ON DELETE CASCADE ON UPDATE NO ACTION,
  CONSTRAINT `fk_pagopagar_usuario` FOREIGN KEY (`idusuario`) REFERENCES `usuario` (`idusuario`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `backup_log` (
  `idbackup` int(11) NOT NULL AUTO_INCREMENT,
  `idusuario` int(11) NOT NULL,
  `archivo` varchar(180) NOT NULL,
  `tamano_bytes` bigint(20) NOT NULL DEFAULT '0',
  `fecha_hora` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `tipo` varchar(20) NOT NULL DEFAULT 'BACKUP',
  PRIMARY KEY (`idbackup`),
  KEY `fk_backup_usuario_idx` (`idusuario`),
  CONSTRAINT `fk_backup_usuario` FOREIGN KEY (`idusuario`) REFERENCES `usuario` (`idusuario`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

SET @col_tipo_pago_venta_exists = (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA=@schema_name
    AND TABLE_NAME='venta'
    AND COLUMN_NAME='tipo_pago'
);
SET @sql_add_tipo_pago_venta = IF(
  @col_tipo_pago_venta_exists = 0,
  'ALTER TABLE `venta` ADD COLUMN `tipo_pago` varchar(20) NOT NULL DEFAULT ''CONTADO'' AFTER `impuesto`',
  'SELECT 1'
);
PREPARE stmt FROM @sql_add_tipo_pago_venta;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col_fv_venta_exists = (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA=@schema_name
    AND TABLE_NAME='venta'
    AND COLUMN_NAME='fecha_vencimiento'
);
SET @sql_add_fv_venta = IF(
  @col_fv_venta_exists = 0,
  'ALTER TABLE `venta` ADD COLUMN `fecha_vencimiento` date NULL AFTER `fecha_hora`',
  'SELECT 1'
);
PREPARE stmt FROM @sql_add_fv_venta;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col_tipo_pago_ingreso_exists = (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA=@schema_name
    AND TABLE_NAME='ingreso'
    AND COLUMN_NAME='tipo_pago'
);
SET @sql_add_tipo_pago_ingreso = IF(
  @col_tipo_pago_ingreso_exists = 0,
  'ALTER TABLE `ingreso` ADD COLUMN `tipo_pago` varchar(20) NOT NULL DEFAULT ''CONTADO'' AFTER `impuesto`',
  'SELECT 1'
);
PREPARE stmt FROM @sql_add_tipo_pago_ingreso;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col_fv_ingreso_exists = (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA=@schema_name
    AND TABLE_NAME='ingreso'
    AND COLUMN_NAME='fecha_vencimiento'
);
SET @sql_add_fv_ingreso = IF(
  @col_fv_ingreso_exists = 0,
  'ALTER TABLE `ingreso` ADD COLUMN `fecha_vencimiento` date NULL AFTER `fecha_hora`',
  'SELECT 1'
);
PREPARE stmt FROM @sql_add_fv_ingreso;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

COMMIT;

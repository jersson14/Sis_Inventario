-- Backup generado por el sistema
-- Fecha: 2026-03-21 21:56:48
-- Base de datos: mi_tienda

SET FOREIGN_KEY_CHECKS=0;

-- ----------------------------
-- Tabla: `articulo`
-- ----------------------------
DROP TABLE IF EXISTS `articulo`;
CREATE TABLE `articulo` (
  `idarticulo` int(11) NOT NULL AUTO_INCREMENT,
  `idcategoria` int(11) NOT NULL,
  `idunidad` int(11) NOT NULL,
  `codigo` varchar(50) DEFAULT NULL,
  `nombre` varchar(100) NOT NULL,
  `stock` decimal(14,3) NOT NULL DEFAULT 0.000,
  `stock_minimo` decimal(14,3) NOT NULL DEFAULT 1.000,
  `descripcion` varchar(256) DEFAULT NULL,
  `imagen` varchar(50) DEFAULT NULL,
  `condicion` tinyint(4) DEFAULT 1,
  PRIMARY KEY (`idarticulo`),
  UNIQUE KEY `nombre_UNIQUE` (`nombre`),
  KEY `fk_articulo_categoria_idx` (`idcategoria`),
  KEY `fk_articulo_unidad_idx` (`idunidad`),
  CONSTRAINT `fk_articulo_categoria` FOREIGN KEY (`idcategoria`) REFERENCES `categoria` (`idcategoria`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_articulo_unidad` FOREIGN KEY (`idunidad`) REFERENCES `unidad_medida` (`idunidad`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

INSERT INTO `articulo` (`idarticulo`,`idcategoria`,`idunidad`,`codigo`,`nombre`,`stock`,`stock_minimo`,`descripcion`,`imagen`,`condicion`) VALUES ('6','7','1','00236','POLO','21.000','1.000','POLO TALLA XL','1638138848.jpg','1');
INSERT INTO `articulo` (`idarticulo`,`idcategoria`,`idunidad`,`codigo`,`nombre`,`stock`,`stock_minimo`,`descripcion`,`imagen`,`condicion`) VALUES ('7','9','1','0040kl','disco solido','58.000','1.000','disco marca KINGSTON','1535417431.jfif','1');
INSERT INTO `articulo` (`idarticulo`,`idcategoria`,`idunidad`,`codigo`,`nombre`,`stock`,`stock_minimo`,`descripcion`,`imagen`,`condicion`) VALUES ('8','9','1','HJL-OP','DATATRABEL','109.000','1.000','usb de 15gb','1535417452.jpg','1');
INSERT INTO `articulo` (`idarticulo`,`idcategoria`,`idunidad`,`codigo`,`nombre`,`stock`,`stock_minimo`,`descripcion`,`imagen`,`condicion`) VALUES ('9','13','1','1235','Pantalon JEAN PARADA 111','12.000','1.000','COMPRA D EPANTALOS JEAN','1638066940.jpg','1');
INSERT INTO `articulo` (`idarticulo`,`idcategoria`,`idunidad`,`codigo`,`nombre`,`stock`,`stock_minimo`,`descripcion`,`imagen`,`condicion`) VALUES ('10','7','6','2112','Tubo','20.000','1.000','tubos de pavco','','1');
INSERT INTO `articulo` (`idarticulo`,`idcategoria`,`idunidad`,`codigo`,`nombre`,`stock`,`stock_minimo`,`descripcion`,`imagen`,`condicion`) VALUES ('11','13','1','ART-648686','Clavos','213.000','1.000','clavios de pares','','1');
INSERT INTO `articulo` (`idarticulo`,`idcategoria`,`idunidad`,`codigo`,`nombre`,`stock`,`stock_minimo`,`descripcion`,`imagen`,`condicion`) VALUES ('12','7','1','CLA-620389','clavo','411.000','1.000','sdfsdf','','1');

-- ----------------------------
-- Tabla: `backup_log`
-- ----------------------------
DROP TABLE IF EXISTS `backup_log`;
CREATE TABLE `backup_log` (
  `idbackup` int(11) NOT NULL AUTO_INCREMENT,
  `idusuario` int(11) NOT NULL,
  `archivo` varchar(180) NOT NULL,
  `tamano_bytes` bigint(20) NOT NULL DEFAULT 0,
  `fecha_hora` datetime NOT NULL DEFAULT current_timestamp(),
  `tipo` varchar(20) NOT NULL DEFAULT 'BACKUP',
  PRIMARY KEY (`idbackup`),
  KEY `fk_backup_usuario_idx` (`idusuario`),
  CONSTRAINT `fk_backup_usuario` FOREIGN KEY (`idusuario`) REFERENCES `usuario` (`idusuario`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;


-- ----------------------------
-- Tabla: `caja_diaria`
-- ----------------------------
DROP TABLE IF EXISTS `caja_diaria`;
CREATE TABLE `caja_diaria` (
  `idcaja` int(11) NOT NULL AUTO_INCREMENT,
  `idusuario` int(11) NOT NULL,
  `fecha_apertura` datetime NOT NULL,
  `fecha_cierre` datetime DEFAULT NULL,
  `monto_apertura` decimal(14,2) NOT NULL DEFAULT 0.00,
  `monto_cierre_sistema` decimal(14,2) DEFAULT NULL,
  `monto_cierre_real` decimal(14,2) DEFAULT NULL,
  `diferencia` decimal(14,2) DEFAULT NULL,
  `estado` varchar(20) NOT NULL DEFAULT 'ABIERTA',
  `observacion` varchar(200) DEFAULT NULL,
  PRIMARY KEY (`idcaja`),
  KEY `fk_caja_usuario_idx` (`idusuario`),
  CONSTRAINT `fk_caja_usuario` FOREIGN KEY (`idusuario`) REFERENCES `usuario` (`idusuario`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;


-- ----------------------------
-- Tabla: `caja_movimiento`
-- ----------------------------
DROP TABLE IF EXISTS `caja_movimiento`;
CREATE TABLE `caja_movimiento` (
  `idmovimiento` int(11) NOT NULL AUTO_INCREMENT,
  `idcaja` int(11) NOT NULL,
  `idusuario` int(11) NOT NULL,
  `tipo` varchar(12) NOT NULL,
  `concepto` varchar(120) NOT NULL,
  `monto` decimal(14,2) NOT NULL,
  `fecha_hora` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`idmovimiento`),
  KEY `fk_cajamov_caja_idx` (`idcaja`),
  KEY `fk_cajamov_usuario_idx` (`idusuario`),
  CONSTRAINT `fk_cajamov_caja` FOREIGN KEY (`idcaja`) REFERENCES `caja_diaria` (`idcaja`) ON DELETE CASCADE ON UPDATE NO ACTION,
  CONSTRAINT `fk_cajamov_usuario` FOREIGN KEY (`idusuario`) REFERENCES `usuario` (`idusuario`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;


-- ----------------------------
-- Tabla: `categoria`
-- ----------------------------
DROP TABLE IF EXISTS `categoria`;
CREATE TABLE `categoria` (
  `idcategoria` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(50) NOT NULL,
  `descripcion` varchar(256) DEFAULT NULL,
  `condicion` tinyint(4) NOT NULL DEFAULT 1,
  PRIMARY KEY (`idcategoria`),
  UNIQUE KEY `nombre_UNIQUE` (`nombre`)
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

INSERT INTO `categoria` (`idcategoria`,`nombre`,`descripcion`,`condicion`) VALUES ('7','POLOS','POLOS MARGA CORTA','1');
INSERT INTO `categoria` (`idcategoria`,`nombre`,`descripcion`,`condicion`) VALUES ('8','CPU\'s','cpus gamers de alta categoria','1');
INSERT INTO `categoria` (`idcategoria`,`nombre`,`descripcion`,`condicion`) VALUES ('9','DISCOS DUROS','disco solidos','1');
INSERT INTO `categoria` (`idcategoria`,`nombre`,`descripcion`,`condicion`) VALUES ('12','MONITORES','monitores gamers','1');
INSERT INTO `categoria` (`idcategoria`,`nombre`,`descripcion`,`condicion`) VALUES ('13','PANTALONES','Pantalos JEAN','1');
INSERT INTO `categoria` (`idcategoria`,`nombre`,`descripcion`,`condicion`) VALUES ('14','herramienta','','1');
INSERT INTO `categoria` (`idcategoria`,`nombre`,`descripcion`,`condicion`) VALUES ('15','sdfdsf','','1');

-- ----------------------------
-- Tabla: `configuracion_empresa`
-- ----------------------------
DROP TABLE IF EXISTS `configuracion_empresa`;
CREATE TABLE `configuracion_empresa` (
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
  `impuesto_default` decimal(5,2) NOT NULL DEFAULT 18.00,
  `moneda` varchar(10) NOT NULL DEFAULT 'PEN',
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`idconfig`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

INSERT INTO `configuracion_empresa` (`idconfig`,`nombre_comercial`,`razon_social`,`ruc`,`direccion`,`telefono`,`celular`,`correo`,`web`,`logo`,`color_primario`,`color_secundario`,`serie_boleta`,`serie_factura`,`serie_ticket`,`impuesto_default`,`moneda`,`updated_at`) VALUES ('1','PERNO CENTRO','PERNO CENTRO \"SEÑOR DE HUANCA\"','20603558422','Bar. Santa Rosa S/N (al costado del Grifo Wari), Abancay - Apurimac','932381391','932381391','ventas@pernocentro.com','','logo1.jpeg','#0f766e','#f59e0b','B001','F001','T001','18.00','PEN','2026-03-21 15:35:02');

-- ----------------------------
-- Tabla: `cuenta_cobrar`
-- ----------------------------
DROP TABLE IF EXISTS `cuenta_cobrar`;
CREATE TABLE `cuenta_cobrar` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;


-- ----------------------------
-- Tabla: `cuenta_pagar`
-- ----------------------------
DROP TABLE IF EXISTS `cuenta_pagar`;
CREATE TABLE `cuenta_pagar` (
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
  CONSTRAINT `fk_pagar_ingreso` FOREIGN KEY (`idingreso`) REFERENCES `ingreso` (`idingreso`) ON DELETE SET NULL ON UPDATE NO ACTION,
  CONSTRAINT `fk_pagar_proveedor` FOREIGN KEY (`idproveedor`) REFERENCES `persona` (`idpersona`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;


-- ----------------------------
-- Tabla: `detalle_ingreso`
-- ----------------------------
DROP TABLE IF EXISTS `detalle_ingreso`;
CREATE TABLE `detalle_ingreso` (
  `iddetalle_ingreso` int(11) NOT NULL AUTO_INCREMENT,
  `idingreso` int(11) NOT NULL,
  `idarticulo` int(11) NOT NULL,
  `cantidad` decimal(14,3) NOT NULL,
  `precio_compra` decimal(11,2) NOT NULL,
  `precio_venta` decimal(11,2) NOT NULL,
  PRIMARY KEY (`iddetalle_ingreso`),
  KEY `fk_detalle_ingreso_idx` (`idingreso`),
  KEY `fk_detalle_articulo_idx` (`idarticulo`),
  CONSTRAINT `fk_detalle_articulo` FOREIGN KEY (`idarticulo`) REFERENCES `articulo` (`idarticulo`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_detalle_ingreso` FOREIGN KEY (`idingreso`) REFERENCES `ingreso` (`idingreso`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB AUTO_INCREMENT=26 DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

INSERT INTO `detalle_ingreso` (`iddetalle_ingreso`,`idingreso`,`idarticulo`,`cantidad`,`precio_compra`,`precio_venta`) VALUES ('16','6','6','10.000','20.00','30.00');
INSERT INTO `detalle_ingreso` (`iddetalle_ingreso`,`idingreso`,`idarticulo`,`cantidad`,`precio_compra`,`precio_venta`) VALUES ('17','6','7','5.000','200.00','250.00');
INSERT INTO `detalle_ingreso` (`iddetalle_ingreso`,`idingreso`,`idarticulo`,`cantidad`,`precio_compra`,`precio_venta`) VALUES ('18','7','8','10.000','16.00','25.00');
INSERT INTO `detalle_ingreso` (`iddetalle_ingreso`,`idingreso`,`idarticulo`,`cantidad`,`precio_compra`,`precio_venta`) VALUES ('19','8','7','10.000','250.00','300.00');
INSERT INTO `detalle_ingreso` (`iddetalle_ingreso`,`idingreso`,`idarticulo`,`cantidad`,`precio_compra`,`precio_venta`) VALUES ('20','9','8','50.000','20.00','30.00');
INSERT INTO `detalle_ingreso` (`iddetalle_ingreso`,`idingreso`,`idarticulo`,`cantidad`,`precio_compra`,`precio_venta`) VALUES ('21','10','6','10.000','25.00','30.00');
INSERT INTO `detalle_ingreso` (`iddetalle_ingreso`,`idingreso`,`idarticulo`,`cantidad`,`precio_compra`,`precio_venta`) VALUES ('22','11','7','15.000','250.00','300.00');
INSERT INTO `detalle_ingreso` (`iddetalle_ingreso`,`idingreso`,`idarticulo`,`cantidad`,`precio_compra`,`precio_venta`) VALUES ('23','12','9','7.000','1.00','1.00');
INSERT INTO `detalle_ingreso` (`iddetalle_ingreso`,`idingreso`,`idarticulo`,`cantidad`,`precio_compra`,`precio_venta`) VALUES ('24','13','12','222.000','1.00','1.00');
INSERT INTO `detalle_ingreso` (`iddetalle_ingreso`,`idingreso`,`idarticulo`,`cantidad`,`precio_compra`,`precio_venta`) VALUES ('25','13','8','3.000','20.00','1.00');

-- ----------------------------
-- Tabla: `detalle_venta`
-- ----------------------------
DROP TABLE IF EXISTS `detalle_venta`;
CREATE TABLE `detalle_venta` (
  `iddetalle_venta` int(11) NOT NULL AUTO_INCREMENT,
  `idventa` int(11) NOT NULL,
  `idarticulo` int(11) NOT NULL,
  `cantidad` decimal(14,3) NOT NULL,
  `precio_venta` decimal(11,2) NOT NULL,
  `descuento` decimal(11,2) NOT NULL,
  PRIMARY KEY (`iddetalle_venta`),
  KEY `fk_detalle_venta_venta_idx` (`idventa`),
  KEY `fk_detalle_venta_articulo_idx` (`idarticulo`),
  CONSTRAINT `fk_detalle_venta_articulo` FOREIGN KEY (`idarticulo`) REFERENCES `articulo` (`idarticulo`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_detalle_venta_venta` FOREIGN KEY (`idventa`) REFERENCES `venta` (`idventa`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB AUTO_INCREMENT=35 DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

INSERT INTO `detalle_venta` (`iddetalle_venta`,`idventa`,`idarticulo`,`cantidad`,`precio_venta`,`descuento`) VALUES ('12','10','6','10.000','30.00','5.00');
INSERT INTO `detalle_venta` (`iddetalle_venta`,`idventa`,`idarticulo`,`cantidad`,`precio_venta`,`descuento`) VALUES ('13','10','7','10.000','250.00','10.00');
INSERT INTO `detalle_venta` (`iddetalle_venta`,`idventa`,`idarticulo`,`cantidad`,`precio_venta`,`descuento`) VALUES ('14','11','6','1.000','30.00','0.00');
INSERT INTO `detalle_venta` (`iddetalle_venta`,`idventa`,`idarticulo`,`cantidad`,`precio_venta`,`descuento`) VALUES ('15','11','7','1.000','250.00','0.00');
INSERT INTO `detalle_venta` (`iddetalle_venta`,`idventa`,`idarticulo`,`cantidad`,`precio_venta`,`descuento`) VALUES ('16','12','7','4.000','250.00','0.00');
INSERT INTO `detalle_venta` (`iddetalle_venta`,`idventa`,`idarticulo`,`cantidad`,`precio_venta`,`descuento`) VALUES ('17','13','7','1.000','250.00','10.00');
INSERT INTO `detalle_venta` (`iddetalle_venta`,`idventa`,`idarticulo`,`cantidad`,`precio_venta`,`descuento`) VALUES ('18','14','7','2.000','250.00','10.00');
INSERT INTO `detalle_venta` (`iddetalle_venta`,`idventa`,`idarticulo`,`cantidad`,`precio_venta`,`descuento`) VALUES ('19','15','6','1.000','30.00','10.00');
INSERT INTO `detalle_venta` (`iddetalle_venta`,`idventa`,`idarticulo`,`cantidad`,`precio_venta`,`descuento`) VALUES ('20','16','7','1.000','250.00','5.00');
INSERT INTO `detalle_venta` (`iddetalle_venta`,`idventa`,`idarticulo`,`cantidad`,`precio_venta`,`descuento`) VALUES ('21','17','7','1.000','250.00','5.00');
INSERT INTO `detalle_venta` (`iddetalle_venta`,`idventa`,`idarticulo`,`cantidad`,`precio_venta`,`descuento`) VALUES ('22','18','6','1.000','30.00','0.00');
INSERT INTO `detalle_venta` (`iddetalle_venta`,`idventa`,`idarticulo`,`cantidad`,`precio_venta`,`descuento`) VALUES ('23','19','7','1.000','250.00','2.00');
INSERT INTO `detalle_venta` (`iddetalle_venta`,`idventa`,`idarticulo`,`cantidad`,`precio_venta`,`descuento`) VALUES ('24','20','8','2.000','25.00','0.00');
INSERT INTO `detalle_venta` (`iddetalle_venta`,`idventa`,`idarticulo`,`cantidad`,`precio_venta`,`descuento`) VALUES ('25','21','6','1.000','30.00','5.00');
INSERT INTO `detalle_venta` (`iddetalle_venta`,`idventa`,`idarticulo`,`cantidad`,`precio_venta`,`descuento`) VALUES ('26','22','6','1.000','30.00','0.00');
INSERT INTO `detalle_venta` (`iddetalle_venta`,`idventa`,`idarticulo`,`cantidad`,`precio_venta`,`descuento`) VALUES ('27','22','7','1.000','300.00','0.00');
INSERT INTO `detalle_venta` (`iddetalle_venta`,`idventa`,`idarticulo`,`cantidad`,`precio_venta`,`descuento`) VALUES ('28','22','8','1.000','30.00','0.00');
INSERT INTO `detalle_venta` (`iddetalle_venta`,`idventa`,`idarticulo`,`cantidad`,`precio_venta`,`descuento`) VALUES ('29','23','9','3.000','150.00','0.00');
INSERT INTO `detalle_venta` (`iddetalle_venta`,`idventa`,`idarticulo`,`cantidad`,`precio_venta`,`descuento`) VALUES ('30','24','9','4.000','120.00','0.00');
INSERT INTO `detalle_venta` (`iddetalle_venta`,`idventa`,`idarticulo`,`cantidad`,`precio_venta`,`descuento`) VALUES ('31','25','6','2.000','30.00','0.00');
INSERT INTO `detalle_venta` (`iddetalle_venta`,`idventa`,`idarticulo`,`cantidad`,`precio_venta`,`descuento`) VALUES ('32','26','6','2.000','30.00','0.00');
INSERT INTO `detalle_venta` (`iddetalle_venta`,`idventa`,`idarticulo`,`cantidad`,`precio_venta`,`descuento`) VALUES ('33','27','12','44.000','2.00','0.00');
INSERT INTO `detalle_venta` (`iddetalle_venta`,`idventa`,`idarticulo`,`cantidad`,`precio_venta`,`descuento`) VALUES ('34','27','8','1.000','30.00','0.00');

-- ----------------------------
-- Tabla: `ingreso`
-- ----------------------------
DROP TABLE IF EXISTS `ingreso`;
CREATE TABLE `ingreso` (
  `idingreso` int(11) NOT NULL AUTO_INCREMENT,
  `idproveedor` int(11) NOT NULL,
  `idusuario` int(11) DEFAULT NULL,
  `tipo_comprobante` varchar(20) NOT NULL,
  `serie_comprobante` varchar(7) DEFAULT NULL,
  `num_comprobante` varchar(10) NOT NULL,
  `fecha_hora` datetime NOT NULL,
  `fecha_vencimiento` date DEFAULT NULL,
  `impuesto` decimal(4,2) NOT NULL,
  `tipo_pago` varchar(20) NOT NULL DEFAULT 'CONTADO',
  `total_compra` decimal(11,2) NOT NULL,
  `estado` varchar(20) NOT NULL,
  PRIMARY KEY (`idingreso`),
  KEY `fk_ingreso_persona_idx` (`idproveedor`),
  KEY `fk_ingreso_usuario_idx` (`idusuario`),
  CONSTRAINT `fk_ingreso_persona` FOREIGN KEY (`idproveedor`) REFERENCES `persona` (`idpersona`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_ingreso_usuario` FOREIGN KEY (`idusuario`) REFERENCES `usuario` (`idusuario`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

INSERT INTO `ingreso` (`idingreso`,`idproveedor`,`idusuario`,`tipo_comprobante`,`serie_comprobante`,`num_comprobante`,`fecha_hora`,`fecha_vencimiento`,`impuesto`,`tipo_pago`,`total_compra`,`estado`) VALUES ('6','7','1','Factura','001','0001','2018-08-20 00:00:00',NULL,'18.00','CONTADO','1200.00','Aceptado');
INSERT INTO `ingreso` (`idingreso`,`idproveedor`,`idusuario`,`tipo_comprobante`,`serie_comprobante`,`num_comprobante`,`fecha_hora`,`fecha_vencimiento`,`impuesto`,`tipo_pago`,`total_compra`,`estado`) VALUES ('7','7','1','Factura','001','008','2018-08-21 00:00:00',NULL,'18.00','CONTADO','160.00','Aceptado');
INSERT INTO `ingreso` (`idingreso`,`idproveedor`,`idusuario`,`tipo_comprobante`,`serie_comprobante`,`num_comprobante`,`fecha_hora`,`fecha_vencimiento`,`impuesto`,`tipo_pago`,`total_compra`,`estado`) VALUES ('8','7','1','Boleta','0002','0004','2018-08-22 00:00:00',NULL,'0.00','CONTADO','2500.00','Aceptado');
INSERT INTO `ingreso` (`idingreso`,`idproveedor`,`idusuario`,`tipo_comprobante`,`serie_comprobante`,`num_comprobante`,`fecha_hora`,`fecha_vencimiento`,`impuesto`,`tipo_pago`,`total_compra`,`estado`) VALUES ('9','9','1','Factura','001','0005','2018-08-23 00:00:00',NULL,'18.00','CONTADO','1000.00','Aceptado');
INSERT INTO `ingreso` (`idingreso`,`idproveedor`,`idusuario`,`tipo_comprobante`,`serie_comprobante`,`num_comprobante`,`fecha_hora`,`fecha_vencimiento`,`impuesto`,`tipo_pago`,`total_compra`,`estado`) VALUES ('10','10','1','Factura','001','0006','2018-08-25 00:00:00',NULL,'18.00','CONTADO','250.00','Aceptado');
INSERT INTO `ingreso` (`idingreso`,`idproveedor`,`idusuario`,`tipo_comprobante`,`serie_comprobante`,`num_comprobante`,`fecha_hora`,`fecha_vencimiento`,`impuesto`,`tipo_pago`,`total_compra`,`estado`) VALUES ('11','10','1','Factura','001','0007','2018-08-27 00:00:00',NULL,'18.00','CONTADO','3750.00','Aceptado');
INSERT INTO `ingreso` (`idingreso`,`idproveedor`,`idusuario`,`tipo_comprobante`,`serie_comprobante`,`num_comprobante`,`fecha_hora`,`fecha_vencimiento`,`impuesto`,`tipo_pago`,`total_compra`,`estado`) VALUES ('12','9','1','Boleta','00','292','2021-11-27 00:00:00',NULL,'0.18','CONTADO','1.00','Aceptado');
INSERT INTO `ingreso` (`idingreso`,`idproveedor`,`idusuario`,`tipo_comprobante`,`serie_comprobante`,`num_comprobante`,`fecha_hora`,`fecha_vencimiento`,`impuesto`,`tipo_pago`,`total_compra`,`estado`) VALUES ('13','9','4','Boleta','S233','2222','2026-03-21 00:00:00',NULL,'0.00','CONTADO','282.00','Aceptado');

-- ----------------------------
-- Tabla: `pago_cuenta_cobrar`
-- ----------------------------
DROP TABLE IF EXISTS `pago_cuenta_cobrar`;
CREATE TABLE `pago_cuenta_cobrar` (
  `idpago_cobrar` int(11) NOT NULL AUTO_INCREMENT,
  `idcuenta_cobrar` int(11) NOT NULL,
  `idusuario` int(11) NOT NULL,
  `fecha_hora` datetime NOT NULL DEFAULT current_timestamp(),
  `monto` decimal(14,2) NOT NULL,
  `medio_pago` varchar(30) DEFAULT NULL,
  `observacion` varchar(150) DEFAULT NULL,
  PRIMARY KEY (`idpago_cobrar`),
  KEY `fk_pagocobrar_cuenta_idx` (`idcuenta_cobrar`),
  KEY `fk_pagocobrar_usuario_idx` (`idusuario`),
  CONSTRAINT `fk_pagocobrar_cuenta` FOREIGN KEY (`idcuenta_cobrar`) REFERENCES `cuenta_cobrar` (`idcuenta_cobrar`) ON DELETE CASCADE ON UPDATE NO ACTION,
  CONSTRAINT `fk_pagocobrar_usuario` FOREIGN KEY (`idusuario`) REFERENCES `usuario` (`idusuario`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;


-- ----------------------------
-- Tabla: `pago_cuenta_pagar`
-- ----------------------------
DROP TABLE IF EXISTS `pago_cuenta_pagar`;
CREATE TABLE `pago_cuenta_pagar` (
  `idpago_pagar` int(11) NOT NULL AUTO_INCREMENT,
  `idcuenta_pagar` int(11) NOT NULL,
  `idusuario` int(11) NOT NULL,
  `fecha_hora` datetime NOT NULL DEFAULT current_timestamp(),
  `monto` decimal(14,2) NOT NULL,
  `medio_pago` varchar(30) DEFAULT NULL,
  `observacion` varchar(150) DEFAULT NULL,
  PRIMARY KEY (`idpago_pagar`),
  KEY `fk_pagopagar_cuenta_idx` (`idcuenta_pagar`),
  KEY `fk_pagopagar_usuario_idx` (`idusuario`),
  CONSTRAINT `fk_pagopagar_cuenta` FOREIGN KEY (`idcuenta_pagar`) REFERENCES `cuenta_pagar` (`idcuenta_pagar`) ON DELETE CASCADE ON UPDATE NO ACTION,
  CONSTRAINT `fk_pagopagar_usuario` FOREIGN KEY (`idusuario`) REFERENCES `usuario` (`idusuario`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;


-- ----------------------------
-- Tabla: `permiso`
-- ----------------------------
DROP TABLE IF EXISTS `permiso`;
CREATE TABLE `permiso` (
  `idpermiso` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(30) NOT NULL,
  PRIMARY KEY (`idpermiso`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

INSERT INTO `permiso` (`idpermiso`,`nombre`) VALUES ('1','Escritorio');
INSERT INTO `permiso` (`idpermiso`,`nombre`) VALUES ('2','Almacen');
INSERT INTO `permiso` (`idpermiso`,`nombre`) VALUES ('3','Compras');
INSERT INTO `permiso` (`idpermiso`,`nombre`) VALUES ('4','Ventas');
INSERT INTO `permiso` (`idpermiso`,`nombre`) VALUES ('5','Acceso');
INSERT INTO `permiso` (`idpermiso`,`nombre`) VALUES ('6','Consulta Compras');
INSERT INTO `permiso` (`idpermiso`,`nombre`) VALUES ('7','Consulta Ventas');

-- ----------------------------
-- Tabla: `persona`
-- ----------------------------
DROP TABLE IF EXISTS `persona`;
CREATE TABLE `persona` (
  `idpersona` int(11) NOT NULL AUTO_INCREMENT,
  `tipo_persona` varchar(20) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `tipo_documento` varchar(20) DEFAULT NULL,
  `num_documento` varchar(20) DEFAULT NULL,
  `direccion` varchar(70) DEFAULT NULL,
  `telefono` varchar(20) DEFAULT NULL,
  `email` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`idpersona`)
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

INSERT INTO `persona` (`idpersona`,`tipo_persona`,`nombre`,`tipo_documento`,`num_documento`,`direccion`,`telefono`,`email`) VALUES ('7','Proveedor','INKA-PC S.R.L','RUC','12587845254','Av. los pinos 201','54328730','inkapc@hotmail.com');
INSERT INTO `persona` (`idpersona`,`tipo_persona`,`nombre`,`tipo_documento`,`num_documento`,`direccion`,`telefono`,`email`) VALUES ('8','Cliente','publico general','DNI','30224520','Av.jose olaya 215','54325230','public@hotmail.com');
INSERT INTO `persona` (`idpersona`,`tipo_persona`,`nombre`,`tipo_documento`,`num_documento`,`direccion`,`telefono`,`email`) VALUES ('9','Proveedor','TECNO-PC','RUC','20485248751','Calle los naranjales 245','054587852','tecno@gmail.com');
INSERT INTO `persona` (`idpersona`,`tipo_persona`,`nombre`,`tipo_documento`,`num_documento`,`direccion`,`telefono`,`email`) VALUES ('10','Proveedor','INFONET','RUC','40485245824','Av. quiñones 102','054789854','infonet@hotmail.com');
INSERT INTO `persona` (`idpersona`,`tipo_persona`,`nombre`,`tipo_documento`,`num_documento`,`direccion`,`telefono`,`email`) VALUES ('11','Cliente','pedro','DNI','458521748','Simon bolivar 120','78954263','pedro@gmailcom');
INSERT INTO `persona` (`idpersona`,`tipo_persona`,`nombre`,`tipo_documento`,`num_documento`,`direccion`,`telefono`,`email`) VALUES ('12','Cliente','Jose Luis','DNI','58256554','Av. Abancay N° 333','985236548','');
INSERT INTO `persona` (`idpersona`,`tipo_persona`,`nombre`,`tipo_documento`,`num_documento`,`direccion`,`telefono`,`email`) VALUES ('13','Proveedor','VISTONY','RUC','21050511515','JR. CUSCO - LIMA PERU','9115151515','vistony12@gmail.com');

-- ----------------------------
-- Tabla: `unidad_medida`
-- ----------------------------
DROP TABLE IF EXISTS `unidad_medida`;
CREATE TABLE `unidad_medida` (
  `idunidad` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(60) NOT NULL,
  `abreviatura` varchar(10) NOT NULL,
  `descripcion` varchar(120) DEFAULT NULL,
  `condicion` tinyint(4) NOT NULL DEFAULT 1,
  PRIMARY KEY (`idunidad`),
  UNIQUE KEY `nombre_UNIQUE` (`nombre`),
  UNIQUE KEY `abreviatura_UNIQUE` (`abreviatura`)
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

INSERT INTO `unidad_medida` (`idunidad`,`nombre`,`abreviatura`,`descripcion`,`condicion`) VALUES ('1','Unidad','und','Unidad individual','1');
INSERT INTO `unidad_medida` (`idunidad`,`nombre`,`abreviatura`,`descripcion`,`condicion`) VALUES ('2','Kilogramo','kg','Peso en kilogramo','1');
INSERT INTO `unidad_medida` (`idunidad`,`nombre`,`abreviatura`,`descripcion`,`condicion`) VALUES ('3','Gramo','g','Peso en gramo','1');
INSERT INTO `unidad_medida` (`idunidad`,`nombre`,`abreviatura`,`descripcion`,`condicion`) VALUES ('4','Litro','lt','Volumen en litro','1');
INSERT INTO `unidad_medida` (`idunidad`,`nombre`,`abreviatura`,`descripcion`,`condicion`) VALUES ('5','Mililitro','ml','Volumen en mililitro','1');
INSERT INTO `unidad_medida` (`idunidad`,`nombre`,`abreviatura`,`descripcion`,`condicion`) VALUES ('6','Metro','m','Longitud en metro','1');
INSERT INTO `unidad_medida` (`idunidad`,`nombre`,`abreviatura`,`descripcion`,`condicion`) VALUES ('7','Centimetro','cm','Longitud en centimetro','1');
INSERT INTO `unidad_medida` (`idunidad`,`nombre`,`abreviatura`,`descripcion`,`condicion`) VALUES ('8','Caja','caja','Presentacion en caja','1');
INSERT INTO `unidad_medida` (`idunidad`,`nombre`,`abreviatura`,`descripcion`,`condicion`) VALUES ('9','Paquete','paq','Presentacion en paquete','1');

-- ----------------------------
-- Tabla: `usuario`
-- ----------------------------
DROP TABLE IF EXISTS `usuario`;
CREATE TABLE `usuario` (
  `idusuario` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) NOT NULL,
  `tipo_documento` varchar(20) NOT NULL,
  `num_documento` varchar(20) NOT NULL,
  `direccion` varchar(70) DEFAULT NULL,
  `telefono` varchar(20) DEFAULT NULL,
  `email` varchar(50) DEFAULT NULL,
  `cargo` varchar(20) DEFAULT NULL,
  `login` varchar(20) NOT NULL,
  `clave` varchar(64) NOT NULL,
  `imagen` varchar(50) NOT NULL,
  `condicion` tinyint(4) NOT NULL DEFAULT 1,
  PRIMARY KEY (`idusuario`),
  UNIQUE KEY `login_UNIQUE` (`login`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

INSERT INTO `usuario` (`idusuario`,`nombre`,`tipo_documento`,`num_documento`,`direccion`,`telefono`,`email`,`cargo`,`login`,`clave`,`imagen`,`condicion`) VALUES ('1','JOSE ANTONIO CHAHUA TAIPE','DNI','72154871','JR. JULIO C TELLO 230','944952429','antonio2021@gmail.com','Vendedor','antonio2021','5994471abb01112afcc18159f6cc74b4f511b99806da59b3caf5a9c173cacfc5','1535417472.jpg','1');
INSERT INTO `usuario` (`idusuario`,`nombre`,`tipo_documento`,`num_documento`,`direccion`,`telefono`,`email`,`cargo`,`login`,`clave`,`imagen`,`condicion`) VALUES ('2','PATRICIA LIMA BENDEZU','DNI','30115425','AV. CIRCUNVALACION S/N','956135290','patricia2021@hotmail.com','Empleado de Compras','patricia2021','5994471abb01112afcc18159f6cc74b4f511b99806da59b3caf5a9c173cacfc5','1535417486.jpg','1');
INSERT INTO `usuario` (`idusuario`,`nombre`,`tipo_documento`,`num_documento`,`direccion`,`telefono`,`email`,`cargo`,`login`,`clave`,`imagen`,`condicion`) VALUES ('3','Jersson Corilla Miranda','DNI','72646121','Jr. Nicolas de Pierola','952956235','jerry12@gmail.com','ADMINISTRADOR','jersson123','5994471abb01112afcc18159f6cc74b4f511b99806da59b3caf5a9c173cacfc5','1774123566.png','1');
INSERT INTO `usuario` (`idusuario`,`nombre`,`tipo_documento`,`num_documento`,`direccion`,`telefono`,`email`,`cargo`,`login`,`clave`,`imagen`,`condicion`) VALUES ('4','WILFREDO CARRIÓN UMERES','DNI','31044054','Jr 28 de Abril','932381391','wcuu@hotmail.com','ADMINISTRADOR','clavito2021','5994471abb01112afcc18159f6cc74b4f511b99806da59b3caf5a9c173cacfc5','1638142070.jpeg','1');
INSERT INTO `usuario` (`idusuario`,`nombre`,`tipo_documento`,`num_documento`,`direccion`,`telefono`,`email`,`cargo`,`login`,`clave`,`imagen`,`condicion`) VALUES ('5','YAMIL SOLIS DIAZ','DNI','65425825','JR. CHALHUANCA 515','944413908','yamil2021@gmail.com','Almacenero','yamil2021','5994471abb01112afcc18159f6cc74b4f511b99806da59b3caf5a9c173cacfc5','','1');

-- ----------------------------
-- Tabla: `usuario_permiso`
-- ----------------------------
DROP TABLE IF EXISTS `usuario_permiso`;
CREATE TABLE `usuario_permiso` (
  `idusuario_permiso` int(11) NOT NULL AUTO_INCREMENT,
  `idusuario` int(11) NOT NULL,
  `idpermiso` int(11) NOT NULL,
  PRIMARY KEY (`idusuario_permiso`),
  KEY `fk_u_permiso_usuario_idx` (`idusuario`),
  KEY `fk_usuario_permiso_idx` (`idpermiso`),
  CONSTRAINT `fk_u_permiso_usuario` FOREIGN KEY (`idusuario`) REFERENCES `usuario` (`idusuario`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_usuario_permiso` FOREIGN KEY (`idpermiso`) REFERENCES `permiso` (`idpermiso`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB AUTO_INCREMENT=180 DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

INSERT INTO `usuario_permiso` (`idusuario_permiso`,`idusuario`,`idpermiso`) VALUES ('116','2','1');
INSERT INTO `usuario_permiso` (`idusuario_permiso`,`idusuario`,`idpermiso`) VALUES ('117','2','3');
INSERT INTO `usuario_permiso` (`idusuario_permiso`,`idusuario`,`idpermiso`) VALUES ('118','2','6');
INSERT INTO `usuario_permiso` (`idusuario_permiso`,`idusuario`,`idpermiso`) VALUES ('119','1','1');
INSERT INTO `usuario_permiso` (`idusuario_permiso`,`idusuario`,`idpermiso`) VALUES ('120','1','4');
INSERT INTO `usuario_permiso` (`idusuario_permiso`,`idusuario`,`idpermiso`) VALUES ('121','1','7');
INSERT INTO `usuario_permiso` (`idusuario_permiso`,`idusuario`,`idpermiso`) VALUES ('122','5','1');
INSERT INTO `usuario_permiso` (`idusuario_permiso`,`idusuario`,`idpermiso`) VALUES ('123','5','2');
INSERT INTO `usuario_permiso` (`idusuario_permiso`,`idusuario`,`idpermiso`) VALUES ('152','4','1');
INSERT INTO `usuario_permiso` (`idusuario_permiso`,`idusuario`,`idpermiso`) VALUES ('153','4','2');
INSERT INTO `usuario_permiso` (`idusuario_permiso`,`idusuario`,`idpermiso`) VALUES ('154','4','3');
INSERT INTO `usuario_permiso` (`idusuario_permiso`,`idusuario`,`idpermiso`) VALUES ('155','4','4');
INSERT INTO `usuario_permiso` (`idusuario_permiso`,`idusuario`,`idpermiso`) VALUES ('156','4','5');
INSERT INTO `usuario_permiso` (`idusuario_permiso`,`idusuario`,`idpermiso`) VALUES ('157','4','6');
INSERT INTO `usuario_permiso` (`idusuario_permiso`,`idusuario`,`idpermiso`) VALUES ('158','4','7');
INSERT INTO `usuario_permiso` (`idusuario_permiso`,`idusuario`,`idpermiso`) VALUES ('173','3','1');
INSERT INTO `usuario_permiso` (`idusuario_permiso`,`idusuario`,`idpermiso`) VALUES ('174','3','2');
INSERT INTO `usuario_permiso` (`idusuario_permiso`,`idusuario`,`idpermiso`) VALUES ('175','3','3');
INSERT INTO `usuario_permiso` (`idusuario_permiso`,`idusuario`,`idpermiso`) VALUES ('176','3','4');
INSERT INTO `usuario_permiso` (`idusuario_permiso`,`idusuario`,`idpermiso`) VALUES ('177','3','5');
INSERT INTO `usuario_permiso` (`idusuario_permiso`,`idusuario`,`idpermiso`) VALUES ('178','3','6');
INSERT INTO `usuario_permiso` (`idusuario_permiso`,`idusuario`,`idpermiso`) VALUES ('179','3','7');

-- ----------------------------
-- Tabla: `venta`
-- ----------------------------
DROP TABLE IF EXISTS `venta`;
CREATE TABLE `venta` (
  `idventa` int(11) NOT NULL AUTO_INCREMENT,
  `idcliente` int(11) NOT NULL,
  `idusuario` int(11) NOT NULL,
  `tipo_comprobante` varchar(20) NOT NULL,
  `serie_comprobante` varchar(7) DEFAULT NULL,
  `num_comprobante` varchar(10) NOT NULL,
  `fecha_hora` datetime NOT NULL,
  `fecha_vencimiento` date DEFAULT NULL,
  `impuesto` decimal(4,2) DEFAULT NULL,
  `tipo_pago` varchar(20) NOT NULL DEFAULT 'CONTADO',
  `total_venta` decimal(11,2) DEFAULT NULL,
  `estado` varchar(20) DEFAULT NULL,
  PRIMARY KEY (`idventa`),
  KEY `fk_venta_persona_idx` (`idcliente`),
  KEY `fk_venta_usuario_idx` (`idusuario`),
  CONSTRAINT `fk_venta_persona` FOREIGN KEY (`idcliente`) REFERENCES `persona` (`idpersona`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_venta_usuario` FOREIGN KEY (`idusuario`) REFERENCES `usuario` (`idusuario`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB AUTO_INCREMENT=28 DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

INSERT INTO `venta` (`idventa`,`idcliente`,`idusuario`,`tipo_comprobante`,`serie_comprobante`,`num_comprobante`,`fecha_hora`,`fecha_vencimiento`,`impuesto`,`tipo_pago`,`total_venta`,`estado`) VALUES ('10','8','1','Boleta','001','0001','2018-01-08 00:00:00',NULL,'0.00','CONTADO','11800.15','Aceptado');
INSERT INTO `venta` (`idventa`,`idcliente`,`idusuario`,`tipo_comprobante`,`serie_comprobante`,`num_comprobante`,`fecha_hora`,`fecha_vencimiento`,`impuesto`,`tipo_pago`,`total_venta`,`estado`) VALUES ('11','8','1','Factura','001','0002','2018-03-05 00:00:00',NULL,'18.00','CONTADO','3800.00','Aceptado');
INSERT INTO `venta` (`idventa`,`idcliente`,`idusuario`,`tipo_comprobante`,`serie_comprobante`,`num_comprobante`,`fecha_hora`,`fecha_vencimiento`,`impuesto`,`tipo_pago`,`total_venta`,`estado`) VALUES ('12','8','1','Ticket','001','0001','2018-04-17 00:00:00',NULL,'0.00','CONTADO','1000.00','Aceptado');
INSERT INTO `venta` (`idventa`,`idcliente`,`idusuario`,`tipo_comprobante`,`serie_comprobante`,`num_comprobante`,`fecha_hora`,`fecha_vencimiento`,`impuesto`,`tipo_pago`,`total_venta`,`estado`) VALUES ('13','8','1','Factura','001','0002','2018-06-09 00:00:00',NULL,'18.00','CONTADO','240.00','Aceptado');
INSERT INTO `venta` (`idventa`,`idcliente`,`idusuario`,`tipo_comprobante`,`serie_comprobante`,`num_comprobante`,`fecha_hora`,`fecha_vencimiento`,`impuesto`,`tipo_pago`,`total_venta`,`estado`) VALUES ('14','8','1','Factura','20','30','2018-07-24 00:00:00',NULL,'18.00','CONTADO','490.00','Aceptado');
INSERT INTO `venta` (`idventa`,`idcliente`,`idusuario`,`tipo_comprobante`,`serie_comprobante`,`num_comprobante`,`fecha_hora`,`fecha_vencimiento`,`impuesto`,`tipo_pago`,`total_venta`,`estado`) VALUES ('15','8','1','Factura','001','0008','2018-08-26 00:00:00',NULL,'18.00','CONTADO','20.00','Aceptado');
INSERT INTO `venta` (`idventa`,`idcliente`,`idusuario`,`tipo_comprobante`,`serie_comprobante`,`num_comprobante`,`fecha_hora`,`fecha_vencimiento`,`impuesto`,`tipo_pago`,`total_venta`,`estado`) VALUES ('16','8','1','Boleta','001','0070','2018-08-26 00:00:00',NULL,'0.00','CONTADO','245.00','Aceptado');
INSERT INTO `venta` (`idventa`,`idcliente`,`idusuario`,`tipo_comprobante`,`serie_comprobante`,`num_comprobante`,`fecha_hora`,`fecha_vencimiento`,`impuesto`,`tipo_pago`,`total_venta`,`estado`) VALUES ('17','8','1','Factura','002','0004','2018-08-26 00:00:00',NULL,'18.00','CONTADO','245.00','Aceptado');
INSERT INTO `venta` (`idventa`,`idcliente`,`idusuario`,`tipo_comprobante`,`serie_comprobante`,`num_comprobante`,`fecha_hora`,`fecha_vencimiento`,`impuesto`,`tipo_pago`,`total_venta`,`estado`) VALUES ('18','8','1','Boleta','001','0006','2018-08-26 00:00:00',NULL,'0.00','CONTADO','30.00','Aceptado');
INSERT INTO `venta` (`idventa`,`idcliente`,`idusuario`,`tipo_comprobante`,`serie_comprobante`,`num_comprobante`,`fecha_hora`,`fecha_vencimiento`,`impuesto`,`tipo_pago`,`total_venta`,`estado`) VALUES ('19','8','1','Factura','001','0009','2018-08-26 00:00:00',NULL,'18.00','CONTADO','248.00','Aceptado');
INSERT INTO `venta` (`idventa`,`idcliente`,`idusuario`,`tipo_comprobante`,`serie_comprobante`,`num_comprobante`,`fecha_hora`,`fecha_vencimiento`,`impuesto`,`tipo_pago`,`total_venta`,`estado`) VALUES ('20','8','1','Factura','001','002','2018-08-26 00:00:00',NULL,'18.00','CONTADO','50.00','Aceptado');
INSERT INTO `venta` (`idventa`,`idcliente`,`idusuario`,`tipo_comprobante`,`serie_comprobante`,`num_comprobante`,`fecha_hora`,`fecha_vencimiento`,`impuesto`,`tipo_pago`,`total_venta`,`estado`) VALUES ('21','8','1','Factura','001','0004','2018-08-27 00:00:00',NULL,'18.00','CONTADO','25.00','Aceptado');
INSERT INTO `venta` (`idventa`,`idcliente`,`idusuario`,`tipo_comprobante`,`serie_comprobante`,`num_comprobante`,`fecha_hora`,`fecha_vencimiento`,`impuesto`,`tipo_pago`,`total_venta`,`estado`) VALUES ('22','11','1','Ticket','001','0004','2018-08-27 00:00:00',NULL,'0.00','CONTADO','360.00','Aceptado');
INSERT INTO `venta` (`idventa`,`idcliente`,`idusuario`,`tipo_comprobante`,`serie_comprobante`,`num_comprobante`,`fecha_hora`,`fecha_vencimiento`,`impuesto`,`tipo_pago`,`total_venta`,`estado`) VALUES ('23','12','1','Boleta','0011','266','2021-11-27 00:00:00',NULL,'0.18','CONTADO','450.00','Aceptado');
INSERT INTO `venta` (`idventa`,`idcliente`,`idusuario`,`tipo_comprobante`,`serie_comprobante`,`num_comprobante`,`fecha_hora`,`fecha_vencimiento`,`impuesto`,`tipo_pago`,`total_venta`,`estado`) VALUES ('24','12','3','Factura','0219','226','2021-11-28 00:00:00',NULL,'18.00','CONTADO','1.00','Aceptado');
INSERT INTO `venta` (`idventa`,`idcliente`,`idusuario`,`tipo_comprobante`,`serie_comprobante`,`num_comprobante`,`fecha_hora`,`fecha_vencimiento`,`impuesto`,`tipo_pago`,`total_venta`,`estado`) VALUES ('25','11','3','Boleta','0001','28','2021-11-28 00:00:00',NULL,'0.18','CONTADO','60.00','Aceptado');
INSERT INTO `venta` (`idventa`,`idcliente`,`idusuario`,`tipo_comprobante`,`serie_comprobante`,`num_comprobante`,`fecha_hora`,`fecha_vencimiento`,`impuesto`,`tipo_pago`,`total_venta`,`estado`) VALUES ('26','11','3','Ticket','0002','112','2021-11-28 00:00:00',NULL,'0.18','CONTADO','60.00','Aceptado');
INSERT INTO `venta` (`idventa`,`idcliente`,`idusuario`,`tipo_comprobante`,`serie_comprobante`,`num_comprobante`,`fecha_hora`,`fecha_vencimiento`,`impuesto`,`tipo_pago`,`total_venta`,`estado`) VALUES ('27','11','4','Boleta','fpp2','155115','2026-03-21 00:00:00',NULL,'0.00','CONTADO','118.00','Aceptado');

SET FOREIGN_KEY_CHECKS=1;

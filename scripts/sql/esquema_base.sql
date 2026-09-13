-- Esquema base de Mi Tienda v2.0.0 (sin datos)
-- Generado desde la BD de referencia. Ejecutar sobre una base vacia.
-- Incluye tablas, claves foraneas, triggers de stock y datos semilla minimos.

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS=0;

DROP TABLE IF EXISTS `ajuste_inventario`;

CREATE TABLE `ajuste_inventario` (
  `idajuste` int(11) NOT NULL AUTO_INCREMENT,
  `idarticulo` int(11) NOT NULL,
  `idusuario` int(11) NOT NULL,
  `tipo` varchar(10) NOT NULL COMMENT 'ENTRADA | SALIDA',
  `motivo` varchar(40) NOT NULL COMMENT 'CONTEO, MERMA, VENCIMIENTO, DEVOLUCION_CLIENTE, DEVOLUCION_PROVEEDOR, ROBO, DONACION, USO_INTERNO, INICIAL, OTRO',
  `cantidad` decimal(14,3) NOT NULL,
  `stock_anterior` decimal(14,3) NOT NULL,
  `stock_nuevo` decimal(14,3) NOT NULL,
  `costo_unitario` decimal(11,2) NOT NULL DEFAULT 0.00,
  `observacion` varchar(200) DEFAULT NULL,
  `fecha_hora` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`idajuste`),
  KEY `fk_ajuste_articulo_idx` (`idarticulo`),
  KEY `fk_ajuste_usuario_idx` (`idusuario`),
  KEY `idx_ajuste_fecha` (`fecha_hora`),
  CONSTRAINT `fk_ajuste_articulo` FOREIGN KEY (`idarticulo`) REFERENCES `articulo` (`idarticulo`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_ajuste_usuario` FOREIGN KEY (`idusuario`) REFERENCES `usuario` (`idusuario`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `articulo`;

CREATE TABLE `articulo` (
  `idarticulo` int(11) NOT NULL AUTO_INCREMENT,
  `idcategoria` int(11) NOT NULL,
  `idunidad` int(11) NOT NULL,
  `codigo` varchar(50) DEFAULT NULL,
  `nombre` varchar(100) NOT NULL,
  `stock` decimal(14,3) NOT NULL DEFAULT 0.000,
  `stock_minimo` decimal(14,3) NOT NULL DEFAULT 1.000,
  `precio_compra` decimal(11,2) NOT NULL DEFAULT 0.00,
  `precio_venta` decimal(11,2) NOT NULL DEFAULT 0.00,
  `descripcion` varchar(256) DEFAULT NULL,
  `imagen` varchar(50) DEFAULT NULL,
  `condicion` tinyint(4) DEFAULT 1,
  `fecha_creacion` datetime NOT NULL DEFAULT current_timestamp(),
  `fecha_actualizacion` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`idarticulo`),
  UNIQUE KEY `nombre_UNIQUE` (`nombre`),
  KEY `fk_articulo_categoria_idx` (`idcategoria`),
  KEY `fk_articulo_unidad_idx` (`idunidad`),
  KEY `idx_articulo_codigo` (`codigo`),
  CONSTRAINT `fk_articulo_categoria` FOREIGN KEY (`idcategoria`) REFERENCES `categoria` (`idcategoria`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_articulo_unidad` FOREIGN KEY (`idunidad`) REFERENCES `unidad_medida` (`idunidad`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

DROP TABLE IF EXISTS `auditoria`;

CREATE TABLE `auditoria` (
  `idauditoria` int(11) NOT NULL AUTO_INCREMENT,
  `idusuario` int(11) DEFAULT NULL,
  `usuario` varchar(60) DEFAULT NULL,
  `modulo` varchar(40) NOT NULL,
  `accion` varchar(40) NOT NULL,
  `detalle` varchar(255) DEFAULT NULL,
  `ip` varchar(45) DEFAULT NULL,
  `fecha_hora` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`idauditoria`),
  KEY `idx_auditoria_fecha` (`fecha_hora`),
  KEY `idx_auditoria_usuario` (`idusuario`),
  KEY `idx_auditoria_modulo` (`modulo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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

DROP TABLE IF EXISTS `caja_movimiento`;

CREATE TABLE `caja_movimiento` (
  `idmovimiento` int(11) NOT NULL AUTO_INCREMENT,
  `idcaja` int(11) NOT NULL,
  `idusuario` int(11) NOT NULL,
  `tipo` varchar(12) NOT NULL,
  `concepto` varchar(120) NOT NULL,
  `referencia` varchar(40) DEFAULT NULL,
  `medio_pago` varchar(20) NOT NULL DEFAULT 'EFECTIVO',
  `monto` decimal(14,2) NOT NULL,
  `fecha_hora` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`idmovimiento`),
  KEY `fk_cajamov_caja_idx` (`idcaja`),
  KEY `fk_cajamov_usuario_idx` (`idusuario`),
  CONSTRAINT `fk_cajamov_caja` FOREIGN KEY (`idcaja`) REFERENCES `caja_diaria` (`idcaja`) ON DELETE CASCADE ON UPDATE NO ACTION,
  CONSTRAINT `fk_cajamov_usuario` FOREIGN KEY (`idusuario`) REFERENCES `usuario` (`idusuario`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

DROP TABLE IF EXISTS `categoria`;

CREATE TABLE `categoria` (
  `idcategoria` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(50) NOT NULL,
  `descripcion` varchar(256) DEFAULT NULL,
  `condicion` tinyint(4) NOT NULL DEFAULT 1,
  PRIMARY KEY (`idcategoria`),
  UNIQUE KEY `nombre_UNIQUE` (`nombre`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

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
  `serie_cotizacion` varchar(10) NOT NULL DEFAULT 'COT',
  `impuesto_default` decimal(5,2) NOT NULL DEFAULT 18.00,
  `moneda` varchar(10) NOT NULL DEFAULT 'PEN',
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `mensaje_ticket` varchar(160) NOT NULL DEFAULT 'Gracias por su compra',
  PRIMARY KEY (`idconfig`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

CREATE TRIGGER `tr_updStockIngreso` AFTER INSERT ON `detalle_ingreso` FOR EACH ROW BEGIN
UPDATE articulo SET stock=stock + NEW.cantidad
WHERE articulo.idarticulo = NEW.idarticulo;
END;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

CREATE TRIGGER `tr_udpStockVenta` AFTER INSERT ON `detalle_venta` FOR EACH ROW BEGIN
UPDATE articulo SET stock = stock - NEW.cantidad
WHERE articulo.idarticulo = NEW.idarticulo;
END;

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
  `medio_pago` varchar(20) NOT NULL DEFAULT 'EFECTIVO',
  `total_compra` decimal(11,2) NOT NULL,
  `estado` varchar(20) NOT NULL,
  `observacion` varchar(200) DEFAULT NULL,
  PRIMARY KEY (`idingreso`),
  KEY `fk_ingreso_persona_idx` (`idproveedor`),
  KEY `fk_ingreso_usuario_idx` (`idusuario`),
  KEY `idx_ingreso_fecha` (`fecha_hora`),
  CONSTRAINT `fk_ingreso_persona` FOREIGN KEY (`idproveedor`) REFERENCES `persona` (`idpersona`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_ingreso_usuario` FOREIGN KEY (`idusuario`) REFERENCES `usuario` (`idusuario`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

DROP TABLE IF EXISTS `intento_login`;

CREATE TABLE `intento_login` (
  `idintento` int(11) NOT NULL AUTO_INCREMENT,
  `login` varchar(60) DEFAULT NULL,
  `ip` varchar(45) DEFAULT NULL,
  `exito` tinyint(1) NOT NULL DEFAULT 0,
  `fecha_hora` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`idintento`),
  KEY `idx_intento_login` (`login`,`fecha_hora`),
  KEY `idx_intento_ip` (`ip`,`fecha_hora`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `migracion`;

CREATE TABLE `migracion` (
  `idmigracion` int(11) NOT NULL AUTO_INCREMENT,
  `archivo` varchar(120) NOT NULL,
  `aplicada_en` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`idmigracion`),
  UNIQUE KEY `uq_migracion_archivo` (`archivo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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

DROP TABLE IF EXISTS `permiso`;

CREATE TABLE `permiso` (
  `idpermiso` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(30) NOT NULL,
  PRIMARY KEY (`idpermiso`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

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
  `condicion` tinyint(4) NOT NULL DEFAULT 1,
  PRIMARY KEY (`idpersona`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

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
  `clave` varchar(255) NOT NULL,
  `imagen` varchar(50) NOT NULL,
  `condicion` tinyint(4) NOT NULL DEFAULT 1,
  `ultimo_acceso` datetime DEFAULT NULL,
  PRIMARY KEY (`idusuario`),
  UNIQUE KEY `login_UNIQUE` (`login`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

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
  `medio_pago` varchar(20) NOT NULL DEFAULT 'EFECTIVO',
  `idcaja` int(11) DEFAULT NULL,
  `total_venta` decimal(11,2) DEFAULT NULL,
  `estado` varchar(20) DEFAULT NULL,
  `observacion` varchar(200) DEFAULT NULL,
  PRIMARY KEY (`idventa`),
  KEY `fk_venta_persona_idx` (`idcliente`),
  KEY `fk_venta_usuario_idx` (`idusuario`),
  KEY `idx_venta_fecha` (`fecha_hora`),
  CONSTRAINT `fk_venta_persona` FOREIGN KEY (`idcliente`) REFERENCES `persona` (`idpersona`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_venta_usuario` FOREIGN KEY (`idusuario`) REFERENCES `usuario` (`idusuario`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;


-- Cotizaciones (migracion 20260913)
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

-- ------------------------------------------------------------------
-- Datos semilla
-- ------------------------------------------------------------------
INSERT IGNORE INTO `permiso` (`idpermiso`,`nombre`) VALUES (1,'Escritorio'),(2,'Almacen'),(3,'Compras'),(4,'Ventas'),(5,'Acceso'),(6,'Consulta Compras'),(7,'Consulta Ventas'),(8,'Gestion Pro'),(9,'Empresa'),(10,'Centro Inteligente'),(11,'Cuentas CxC CxP'),(12,'Backup'),(13,'Centro Reportes'),(14,'Caja'),(15,'Ajustes Inventario');

INSERT IGNORE INTO `unidad_medida` (`nombre`,`abreviatura`,`descripcion`,`condicion`) VALUES ('Unidad','und','Unidad individual',1),('Kilogramo','kg','Peso en kilogramo',1),('Gramo','g','Peso en gramo',1),('Litro','lt','Volumen en litro',1),('Mililitro','ml','Volumen en mililitro',1),('Metro','m','Longitud en metro',1),('Centimetro','cm','Longitud en centimetro',1),('Caja','caja','Presentacion en caja',1),('Paquete','paq','Presentacion en paquete',1),('Galon','gal','Volumen en galon',1);

INSERT IGNORE INTO `categoria` (`idcategoria`,`nombre`,`descripcion`,`condicion`) VALUES (1,'General','Categoria por defecto',1);

INSERT IGNORE INTO `configuracion_empresa` (`idconfig`,`nombre_comercial`,`razon_social`,`ruc`,`direccion`,`telefono`,`celular`,`correo`,`web`,`logo`,`color_primario`,`color_secundario`,`serie_boleta`,`serie_factura`,`serie_ticket`,`impuesto_default`,`moneda`,`mensaje_ticket`) VALUES (1,'Mi Tienda','','','','','','','','','#0f766e','#f59e0b','B001','F001','T001',18.00,'PEN','Gracias por su compra');

INSERT IGNORE INTO `migracion` (`archivo`) VALUES ('20260321_unidades_medida.sql'),('20260321_fase_comercial.sql'),('20260911_seguridad_inventario.sql'),('20260913_cotizaciones.sql');

SET FOREIGN_KEY_CHECKS=1;

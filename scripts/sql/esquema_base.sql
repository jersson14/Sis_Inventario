-- Esquema base de Mi Tienda v2.6.0 (sin datos)
-- Generado desde la BD de referencia. Ejecutar sobre una base vacia.
-- Incluye tablas, claves foraneas, triggers de stock y datos semilla minimos.

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS=0;

DROP TABLE IF EXISTS `ajuste_inventario`;

CREATE TABLE `ajuste_inventario` (
  `idajuste` int(11) NOT NULL AUTO_INCREMENT,
  `idarticulo` int(11) NOT NULL,
  `idvariante` int(11) DEFAULT NULL,
  `idusuario` int(11) NOT NULL,
  `tipo` varchar(10) NOT NULL COMMENT 'ENTRADA | SALIDA',
  `motivo` varchar(40) NOT NULL COMMENT 'CONTEO, MERMA, VENCIMIENTO, DEVOLUCION_CLIENTE, DEVOLUCION_PROVEEDOR, ROBO, DONACION, USO_INTERNO, INICIAL, OTRO',
  `cantidad` decimal(14,3) NOT NULL,
  `stock_anterior` decimal(14,3) NOT NULL,
  `stock_nuevo` decimal(14,3) NOT NULL,
  `costo_unitario` decimal(11,2) NOT NULL DEFAULT 0.00,
  `observacion` varchar(200) DEFAULT NULL,
  `idconteo` int(11) DEFAULT NULL,
  `fecha_hora` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`idajuste`),
  KEY `fk_ajuste_articulo_idx` (`idarticulo`),
  KEY `fk_ajuste_usuario_idx` (`idusuario`),
  KEY `idx_ajuste_fecha` (`fecha_hora`),
  KEY `idx_ajuste_conteo` (`idconteo`),
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
  `temporada` varchar(40) DEFAULT NULL,
  `coleccion` varchar(40) DEFAULT NULL,
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
  `tipo_negocio` varchar(20) NOT NULL DEFAULT 'GENERAL' COMMENT 'GENERAL | ABARROTES | FERRETERIA | ROPA',
  `dias_alerta_vencimiento` int(11) NOT NULL DEFAULT 30,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `mensaje_ticket` varchar(160) NOT NULL DEFAULT 'Gracias por su compra',
  `ticket_ancho` int(11) NOT NULL DEFAULT 80,
  `ticket_auto_imprimir` tinyint(1) NOT NULL DEFAULT 1,
  `ticket_logo` tinyint(1) NOT NULL DEFAULT 1,
  `ticket_cabecera` varchar(200) DEFAULT NULL,
  `ticket_copias` int(11) NOT NULL DEFAULT 1,
  `arqueo_ciego` tinyint(1) NOT NULL DEFAULT 1,
  `url_publica` varchar(200) DEFAULT NULL,
  `ticket_qr` tinyint(1) NOT NULL DEFAULT 1,
  `ticket_leyenda` varchar(250) DEFAULT 'Este comprobante interno puede canjearse por una boleta o factura electrónica válida ante SUNAT. Solicítela en tienda presentando este ticket.',
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
  `idpresentacion` int(11) DEFAULT NULL,
  `idvariante` int(11) DEFAULT NULL,
  `cantidad` decimal(14,3) NOT NULL,
  `factor` decimal(14,3) NOT NULL DEFAULT 1.000,
  `precio_compra` decimal(11,2) NOT NULL,
  `precio_venta` decimal(11,2) NOT NULL,
  PRIMARY KEY (`iddetalle_ingreso`),
  KEY `fk_detalle_ingreso_idx` (`idingreso`),
  KEY `fk_detalle_articulo_idx` (`idarticulo`),
  CONSTRAINT `fk_detalle_articulo` FOREIGN KEY (`idarticulo`) REFERENCES `articulo` (`idarticulo`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_detalle_ingreso` FOREIGN KEY (`idingreso`) REFERENCES `ingreso` (`idingreso`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

CREATE TRIGGER `tr_updStockIngreso` AFTER INSERT ON `detalle_ingreso` FOR EACH ROW BEGIN
UPDATE articulo a
LEFT JOIN articulo_variante v ON v.idvariante = NEW.idvariante AND v.idarticulo = NEW.idarticulo
SET a.stock = a.stock + (NEW.cantidad * NEW.factor), v.stock = v.stock + (NEW.cantidad * NEW.factor)
WHERE a.idarticulo = NEW.idarticulo;
END;

DROP TABLE IF EXISTS `detalle_venta`;

CREATE TABLE `detalle_venta` (
  `iddetalle_venta` int(11) NOT NULL AUTO_INCREMENT,
  `idventa` int(11) NOT NULL,
  `idarticulo` int(11) NOT NULL,
  `idpresentacion` int(11) DEFAULT NULL,
  `idvariante` int(11) DEFAULT NULL,
  `cantidad` decimal(14,3) NOT NULL,
  `factor` decimal(14,3) NOT NULL DEFAULT 1.000,
  `precio_venta` decimal(11,2) NOT NULL,
  `descuento` decimal(11,2) NOT NULL,
  PRIMARY KEY (`iddetalle_venta`),
  KEY `fk_detalle_venta_venta_idx` (`idventa`),
  KEY `fk_detalle_venta_articulo_idx` (`idarticulo`),
  CONSTRAINT `fk_detalle_venta_articulo` FOREIGN KEY (`idarticulo`) REFERENCES `articulo` (`idarticulo`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_detalle_venta_venta` FOREIGN KEY (`idventa`) REFERENCES `venta` (`idventa`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

CREATE TRIGGER `tr_udpStockVenta` AFTER INSERT ON `detalle_venta` FOR EACH ROW BEGIN
UPDATE articulo a
LEFT JOIN articulo_variante v ON v.idvariante = NEW.idvariante AND v.idarticulo = NEW.idarticulo
SET a.stock = a.stock - (NEW.cantidad * NEW.factor), v.stock = v.stock - (NEW.cantidad * NEW.factor)
WHERE a.idarticulo = NEW.idarticulo;
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
  `cuenta_pago` varchar(80) DEFAULT NULL,
  `num_operacion` varchar(40) DEFAULT NULL,
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
  `permite_fraccion` tinyint(1) NOT NULL DEFAULT 0,
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
  `num_operacion` varchar(40) DEFAULT NULL,
  `idcaja` int(11) DEFAULT NULL,
  `total_venta` decimal(11,2) DEFAULT NULL,
  `monto_recibido` decimal(11,2) DEFAULT NULL,
  `estado` varchar(20) DEFAULT NULL,
  `observacion` varchar(200) DEFAULT NULL,
  `codigo_publico` varchar(20) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL,
  PRIMARY KEY (`idventa`),
  UNIQUE KEY `uq_venta_codigo_publico` (`codigo_publico`),
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
  `idpresentacion` INT(11) DEFAULT NULL,
  `idvariante` INT(11) DEFAULT NULL,
  `cantidad` DECIMAL(14,3) NOT NULL,
  `factor` DECIMAL(14,3) NOT NULL DEFAULT 1.000,
  `precio` DECIMAL(11,2) NOT NULL,
  `descuento` DECIMAL(11,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`iddetalle_cotizacion`),
  KEY `fk_detcot_cotizacion_idx` (`idcotizacion`),
  KEY `fk_detcot_articulo_idx` (`idarticulo`),
  CONSTRAINT `fk_detcot_cotizacion` FOREIGN KEY (`idcotizacion`) REFERENCES `cotizacion` (`idcotizacion`) ON DELETE CASCADE ON UPDATE NO ACTION,
  CONSTRAINT `fk_detcot_articulo` FOREIGN KEY (`idarticulo`) REFERENCES `articulo` (`idarticulo`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

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

CREATE TABLE IF NOT EXISTS `articulo_precio_escala` (
  `idescala` INT(11) NOT NULL AUTO_INCREMENT,
  `idarticulo` INT(11) NOT NULL,
  `cantidad_minima` DECIMAL(14,3) NOT NULL,
  `precio` DECIMAL(11,2) NOT NULL,
  PRIMARY KEY (`idescala`),
  UNIQUE KEY `uq_escala_articulo_cantidad` (`idarticulo`, `cantidad_minima`),
  CONSTRAINT `fk_escala_articulo` FOREIGN KEY (`idarticulo`) REFERENCES `articulo` (`idarticulo`) ON DELETE CASCADE ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

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
  `tipo` VARCHAR(20) NOT NULL COMMENT 'VENTA | AJUSTE | ENTRADA',
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

CREATE TABLE IF NOT EXISTS `conteo_inventario` (
  `idconteo` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(80) NOT NULL,
  `idcategoria` int(11) DEFAULT NULL COMMENT 'NULL = todo el almacen',
  `por_lote` tinyint(1) NOT NULL DEFAULT 0,
  `estado` varchar(12) NOT NULL DEFAULT 'ABIERTO',
  `observacion` varchar(200) DEFAULT NULL,
  `idusuario` int(11) NOT NULL,
  `fecha_inicio` datetime NOT NULL DEFAULT current_timestamp(),
  `idusuario_cierre` int(11) DEFAULT NULL,
  `fecha_cierre` datetime DEFAULT NULL,
  `ajustes` int(11) NOT NULL DEFAULT 0,
  `valor_sobrante` decimal(14,2) NOT NULL DEFAULT 0.00,
  `valor_faltante` decimal(14,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`idconteo`),
  KEY `idx_conteo_estado` (`estado`),
  CONSTRAINT `fk_conteo_usuario` FOREIGN KEY (`idusuario`) REFERENCES `usuario` (`idusuario`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `conteo_detalle` (
  `iddetalle` int(11) NOT NULL AUTO_INCREMENT,
  `idconteo` int(11) NOT NULL,
  `idarticulo` int(11) NOT NULL,
  `idvariante` int(11) NOT NULL DEFAULT 0,
  `idlote` int(11) NOT NULL DEFAULT 0,
  `lote_clave` varchar(60) NOT NULL DEFAULT '',
  `lote_codigo` varchar(40) DEFAULT NULL,
  `lote_vencimiento` date DEFAULT NULL,
  `cantidad` decimal(14,3) NOT NULL DEFAULT 0.000,
  `stock_sistema` decimal(14,3) NOT NULL DEFAULT 0.000,
  `costo_unitario` decimal(11,2) NOT NULL DEFAULT 0.00,
  `lecturas` int(11) NOT NULL DEFAULT 0,
  `idusuario` int(11) NOT NULL,
  `actualizado` datetime NOT NULL DEFAULT current_timestamp(),
  `diferencia_aplicada` decimal(14,3) DEFAULT NULL,
  `idajuste` int(11) DEFAULT NULL,
  PRIMARY KEY (`iddetalle`),
  UNIQUE KEY `uq_conteo_linea` (`idconteo`,`idarticulo`,`idvariante`,`idlote`,`lote_clave`),
  KEY `idx_conteo_detalle_articulo` (`idarticulo`),
  CONSTRAINT `fk_conteo_detalle_conteo` FOREIGN KEY (`idconteo`) REFERENCES `conteo_inventario` (`idconteo`) ON DELETE CASCADE ON UPDATE NO ACTION,
  CONSTRAINT `fk_conteo_detalle_articulo` FOREIGN KEY (`idarticulo`) REFERENCES `articulo` (`idarticulo`) ON DELETE CASCADE ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ------------------------------------------------------------------
-- Pago mixto, notas de credito y varios almacenes (mismas sentencias
-- idempotentes que migrations/20260925, 20260926 y 20260927)
-- ------------------------------------------------------------------

-- ===== 20260925_pago_mixto.sql =====
-- Migracion: pago mixto (v2.4)
-- Fecha: 2026-09-25
-- Idempotente.
--
-- venta_pago   una fila por cada medio con que se pago una venta:
--              50 en efectivo + 30 con Yape = dos filas.
--              monto     lo que se aplica a la venta (sin vuelto)
--              recibido  solo efectivo: lo que entrego el cliente (vuelto = recibido - monto)
--              En una venta al CREDITO las filas son el adelanto; el resto va a la
--              cuenta por cobrar.
-- venta.medio_pago queda como resumen: el medio si fue uno solo, MIXTO si fueron
-- varios, CREDITO si fue al credito sin adelanto.
--
-- Datos existentes: cada venta al contado recibe una fila con su medio y su total.

CREATE TABLE IF NOT EXISTS `venta_pago` (
  `idpago` int(11) NOT NULL AUTO_INCREMENT,
  `idventa` int(11) NOT NULL,
  `medio_pago` varchar(20) NOT NULL,
  `monto` decimal(11,2) NOT NULL,
  `recibido` decimal(11,2) DEFAULT NULL,
  `num_operacion` varchar(40) DEFAULT NULL,
  `idcaja` int(11) DEFAULT NULL,
  `fecha_hora` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`idpago`),
  KEY `idx_venta_pago_venta` (`idventa`),
  KEY `idx_venta_pago_medio` (`medio_pago`),
  CONSTRAINT `fk_venta_pago_venta` FOREIGN KEY (`idventa`) REFERENCES `venta` (`idventa`) ON DELETE CASCADE ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

INSERT INTO `venta_pago` (`idventa`, `medio_pago`, `monto`, `recibido`, `num_operacion`, `idcaja`, `fecha_hora`)
SELECT v.idventa, IFNULL(NULLIF(v.medio_pago,''),'EFECTIVO'), IFNULL(v.total_venta,0),
       IF(IFNULL(NULLIF(v.medio_pago,''),'EFECTIVO')='EFECTIVO', v.monto_recibido, NULL),
       v.num_operacion, v.idcaja, v.fecha_hora
FROM `venta` v
WHERE v.tipo_pago='CONTADO'
  AND NOT EXISTS (SELECT 1 FROM `venta_pago` p WHERE p.idventa=v.idventa);

-- Ventas al credito existentes: el resumen deja de decir "EFECTIVO"
UPDATE `venta` v SET v.medio_pago='CREDITO'
WHERE v.tipo_pago='CREDITO' AND NOT EXISTS (SELECT 1 FROM `venta_pago` p WHERE p.idventa=v.idventa) AND v.medio_pago<>'CREDITO';

-- ===== 20260926_notas_credito.sql =====
-- Migracion: devoluciones y notas de credito (v2.5)
-- Fecha: 2026-09-26
-- Idempotente.
--
-- nota_credito           documento que reduce una venta ya emitida (serie NC01).
--   tipo_nota            01 = anulacion de la operacion (todo), 07 = devolucion por item
--   reintegro            ORIGINAL (revierte cada pago de la venta), EFECTIVO/YAPE/...,
--                        SALDO_A_FAVOR (el cliente lo usa en otra compra) o NINGUNO
--   monto_credito        lo que bajo la deuda de una venta al credito
--   monto_reintegro      lo devuelto en dinero o como saldo a favor
--   saldo_favor          saldo a favor aun disponible (se usa como medio de pago NOTA_CREDITO)
-- detalle_nota_credito   lineas devueltas, referidas a la linea vendida (iddetalle_venta).
--   reingresa_stock      0 = producto danado: no vuelve a la venta
-- lote_movimiento        tipo DEVOLUCION (con idnota): lo que volvio a cada lote
-- venta_pago.idnota      pago hecho con el saldo a favor de una nota de credito
--
-- Vistas para reportes (todas restan las notas de credito sin duplicar logica):
--   venta_linea          lineas vendidas (+) y devueltas (-) con su fecha; cantidad_costo
--                        excluye lo danado (el costo de lo que no vuelve es perdida)
--   venta_total          cabeceras: ventas (+) y notas de credito (-) por fecha
--   kardex_movimiento    todo movimiento de stock: compras, ventas, devoluciones y ajustes

SET @schema_name = DATABASE();

CREATE TABLE IF NOT EXISTS `nota_credito` (
  `idnota` int(11) NOT NULL AUTO_INCREMENT,
  `idventa` int(11) NOT NULL,
  `idcliente` int(11) NOT NULL,
  `idusuario` int(11) NOT NULL,
  `idautoriza` int(11) DEFAULT NULL,
  `serie` varchar(4) NOT NULL DEFAULT 'NC01',
  `numero` varchar(8) NOT NULL,
  `fecha_hora` datetime NOT NULL,
  `tipo_nota` char(2) NOT NULL,
  `motivo` varchar(200) NOT NULL,
  `total` decimal(11,2) NOT NULL,
  `reintegro` varchar(20) NOT NULL,
  `monto_credito` decimal(11,2) NOT NULL DEFAULT 0.00,
  `monto_reintegro` decimal(11,2) NOT NULL DEFAULT 0.00,
  `saldo_favor` decimal(11,2) NOT NULL DEFAULT 0.00,
  `idcaja` int(11) DEFAULT NULL,
  `estado` varchar(12) NOT NULL DEFAULT 'EMITIDA',
  PRIMARY KEY (`idnota`),
  UNIQUE KEY `uq_nota_serie_numero` (`serie`,`numero`),
  KEY `idx_nota_venta` (`idventa`),
  KEY `idx_nota_cliente` (`idcliente`),
  KEY `idx_nota_fecha` (`fecha_hora`),
  CONSTRAINT `fk_nota_venta` FOREIGN KEY (`idventa`) REFERENCES `venta` (`idventa`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_nota_usuario` FOREIGN KEY (`idusuario`) REFERENCES `usuario` (`idusuario`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

CREATE TABLE IF NOT EXISTS `detalle_nota_credito` (
  `iddetalle` int(11) NOT NULL AUTO_INCREMENT,
  `idnota` int(11) NOT NULL,
  `iddetalle_venta` int(11) NOT NULL,
  `idarticulo` int(11) NOT NULL,
  `idpresentacion` int(11) DEFAULT NULL,
  `idvariante` int(11) DEFAULT NULL,
  `cantidad` decimal(14,3) NOT NULL,
  `factor` decimal(14,3) NOT NULL DEFAULT 1.000,
  `precio` decimal(11,2) NOT NULL,
  `descuento` decimal(11,2) NOT NULL DEFAULT 0.00,
  `subtotal` decimal(11,2) NOT NULL,
  `reingresa_stock` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`iddetalle`),
  KEY `idx_dnc_nota` (`idnota`),
  KEY `idx_dnc_detalle_venta` (`iddetalle_venta`),
  KEY `idx_dnc_articulo` (`idarticulo`),
  CONSTRAINT `fk_dnc_nota` FOREIGN KEY (`idnota`) REFERENCES `nota_credito` (`idnota`) ON DELETE CASCADE ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

SET @c = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME='lote_movimiento' AND COLUMN_NAME='idnota');
SET @s = IF(@c=0, 'ALTER TABLE `lote_movimiento` ADD COLUMN `idnota` INT(11) DEFAULT NULL AFTER `idajuste`, MODIFY `tipo` varchar(20) NOT NULL COMMENT ''VENTA | AJUSTE | ENTRADA | DEVOLUCION''', 'SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @c = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME='venta_pago' AND COLUMN_NAME='idnota');
SET @s = IF(@c=0, 'ALTER TABLE `venta_pago` ADD COLUMN `idnota` INT(11) DEFAULT NULL AFTER `idcaja`', 'SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

CREATE OR REPLACE VIEW `venta_linea` AS
SELECT dv.idventa, 0 AS idnota, dv.iddetalle_venta, dv.idarticulo, dv.idvariante,
       dv.cantidad, dv.factor, dv.precio_venta, dv.descuento, dv.cantidad AS cantidad_costo,
       v.fecha_hora, v.estado, v.idusuario, v.idcliente
FROM detalle_venta dv
INNER JOIN venta v ON v.idventa=dv.idventa
UNION ALL
SELECT n.idventa, n.idnota, d.iddetalle_venta, d.idarticulo, d.idvariante,
       -d.cantidad, d.factor, d.precio, -d.descuento, IF(d.reingresa_stock=1, -d.cantidad, 0),
       n.fecha_hora, v.estado, n.idusuario, n.idcliente
FROM detalle_nota_credito d
INNER JOIN nota_credito n ON n.idnota=d.idnota
INNER JOIN venta v ON v.idventa=n.idventa
WHERE n.estado='EMITIDA' AND n.tipo_nota<>'01';

CREATE OR REPLACE VIEW `venta_total` AS
SELECT v.idventa, 0 AS idnota, v.fecha_hora, v.estado, v.total_venta AS total, v.idusuario, v.idcliente, v.tipo_pago
FROM venta v
UNION ALL
SELECT n.idventa, n.idnota, n.fecha_hora, v.estado, -n.total, n.idusuario, n.idcliente, v.tipo_pago
FROM nota_credito n
INNER JOIN venta v ON v.idventa=n.idventa
WHERE n.estado='EMITIDA' AND n.tipo_nota<>'01';

-- Kardex: compras, ventas, devoluciones y ajustes (una fila por movimiento de stock).
-- Las ventas anuladas (y su nota 01) no aparecen: su efecto neto es cero.
CREATE OR REPLACE VIEW `kardex_movimiento` AS
SELECT di.idarticulo, di.idvariante, i.fecha_hora, 'INGRESO' AS tipo,
  CONCAT(i.tipo_comprobante,' ',i.serie_comprobante,'-',i.num_comprobante) AS documento,
  IFNULL(p.nombre,'-') AS tercero,
  di.cantidad*di.factor AS entrada, 0.000 AS salida,
  di.precio_compra/di.factor AS costo, di.precio_venta AS precio_ref
FROM detalle_ingreso di
INNER JOIN ingreso i ON i.idingreso=di.idingreso
LEFT JOIN persona p ON p.idpersona=i.idproveedor
WHERE i.estado='Aceptado'
UNION ALL
SELECT dv.idarticulo, dv.idvariante, v.fecha_hora, 'VENTA',
  CONCAT(v.tipo_comprobante,' ',v.serie_comprobante,'-',v.num_comprobante),
  IFNULL(p.nombre,'-'),
  0.000, dv.cantidad*dv.factor,
  IFNULL((SELECT di2.precio_compra/di2.factor FROM detalle_ingreso di2 INNER JOIN ingreso i2 ON i2.idingreso=di2.idingreso
          WHERE di2.idarticulo=dv.idarticulo AND i2.estado='Aceptado' AND i2.fecha_hora<=v.fecha_hora
          ORDER BY i2.fecha_hora DESC, di2.iddetalle_ingreso DESC LIMIT 1), a.precio_compra),
  dv.precio_venta
FROM detalle_venta dv
INNER JOIN venta v ON v.idventa=dv.idventa
INNER JOIN articulo a ON a.idarticulo=dv.idarticulo
LEFT JOIN persona p ON p.idpersona=v.idcliente
WHERE v.estado='Aceptado'
UNION ALL
SELECT d.idarticulo, d.idvariante, n.fecha_hora, IF(d.reingresa_stock=1,'DEVOLUCION','DEVOLUCION (DAÑADO)'),
  CONCAT('NC ',n.serie,'-',n.numero,' de ',v.serie_comprobante,'-',v.num_comprobante),
  IFNULL(p.nombre,'-'),
  IF(d.reingresa_stock=1, d.cantidad*d.factor, 0), 0.000, a.precio_compra, d.precio
FROM detalle_nota_credito d
INNER JOIN nota_credito n ON n.idnota=d.idnota
INNER JOIN venta v ON v.idventa=n.idventa
INNER JOIN articulo a ON a.idarticulo=d.idarticulo
LEFT JOIN persona p ON p.idpersona=n.idcliente
WHERE n.estado='EMITIDA' AND n.tipo_nota<>'01' AND v.estado='Aceptado'
UNION ALL
SELECT aj.idarticulo, aj.idvariante, aj.fecha_hora, IF(aj.tipo='ENTRADA','AJUSTE +','AJUSTE -'),
  CONCAT('AJUSTE #',aj.idajuste,' ',aj.motivo),
  IFNULL(u.nombre,'-'),
  IF(aj.tipo='ENTRADA',aj.cantidad,0), IF(aj.tipo='SALIDA',aj.cantidad,0), aj.costo_unitario, 0
FROM ajuste_inventario aj
LEFT JOIN usuario u ON u.idusuario=aj.idusuario;

-- ===== 20260927_almacenes.sql =====
-- Migracion: varios almacenes, stock por almacen y transferencias
-- Fecha: 2026-09-27
-- Idempotente.
--
-- almacen                 tipo NORMAL (tienda, deposito, local 2...) o TRANSITO (oculto:
--                         la mercaderia enviada que aun no se recibe). Uno es el principal.
-- stock_almacen           stock por almacen, articulo y talla/color (idvariante 0 = sin talla).
--                         Regla: la suma de todos los almacenes (incluido el transito) es
--                         articulo.stock (y articulo_variante.stock por talla). Lo que cambia
--                         el total a mano (ficha del articulo, importacion) se cuadra en el
--                         almacen principal.
-- transferencia           ENVIADA (sale del origen y queda en transito) -> RECIBIDA (entra al
--                         destino; lo que no llego queda como ajuste de salida) o ANULADA.
-- detalle_transferencia   una linea por articulo / talla / lote enviado.
-- idalmacen en venta, ingreso, ajuste_inventario, conteo_inventario, nota_credito, lote
-- (de que almacen sale o a cual entra) y en usuario (almacen donde trabaja).
-- Permiso 18 "Almacenes": crear almacenes, transferir y cambiar de almacen.
-- Datos existentes: todo queda en el almacen principal.

SET @schema_name = DATABASE();

CREATE TABLE IF NOT EXISTS `almacen` (
  `idalmacen` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(60) NOT NULL,
  `direccion` varchar(150) DEFAULT NULL,
  `responsable` varchar(80) DEFAULT NULL,
  `principal` tinyint(1) NOT NULL DEFAULT 0,
  `tipo` varchar(10) NOT NULL DEFAULT 'NORMAL',
  `condicion` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`idalmacen`),
  UNIQUE KEY `uq_almacen_nombre` (`nombre`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

INSERT INTO `almacen` (`nombre`,`principal`,`tipo`,`condicion`)
SELECT 'Almacén principal', 1, 'NORMAL', 1 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `almacen` WHERE `principal`=1);

INSERT INTO `almacen` (`nombre`,`principal`,`tipo`,`condicion`)
SELECT 'En tránsito', 0, 'TRANSITO', 1 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `almacen` WHERE `tipo`='TRANSITO');

SET @principal = (SELECT idalmacen FROM `almacen` WHERE principal=1 ORDER BY idalmacen LIMIT 1);

CREATE TABLE IF NOT EXISTS `stock_almacen` (
  `idalmacen` int(11) NOT NULL,
  `idarticulo` int(11) NOT NULL,
  `idvariante` int(11) NOT NULL DEFAULT 0,
  `stock` decimal(14,3) NOT NULL DEFAULT 0.000,
  PRIMARY KEY (`idalmacen`,`idarticulo`,`idvariante`),
  KEY `idx_stock_almacen_articulo` (`idarticulo`,`idvariante`),
  CONSTRAINT `fk_stock_almacen_almacen` FOREIGN KEY (`idalmacen`) REFERENCES `almacen` (`idalmacen`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_stock_almacen_articulo` FOREIGN KEY (`idarticulo`) REFERENCES `articulo` (`idarticulo`) ON DELETE CASCADE ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- Stock actual al almacen principal: por talla si el articulo las tiene, si no por articulo
INSERT IGNORE INTO `stock_almacen` (`idalmacen`,`idarticulo`,`idvariante`,`stock`)
SELECT @principal, a.idarticulo, 0, a.stock FROM `articulo` a
WHERE NOT EXISTS (SELECT 1 FROM `articulo_variante` v WHERE v.idarticulo=a.idarticulo AND v.condicion=1);
INSERT IGNORE INTO `stock_almacen` (`idalmacen`,`idarticulo`,`idvariante`,`stock`)
SELECT @principal, v.idarticulo, v.idvariante, v.stock FROM `articulo_variante` v WHERE v.condicion=1;

-- idalmacen en documentos, lotes y usuarios
SET @c = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME='venta' AND COLUMN_NAME='idalmacen');
SET @s = IF(@c=0, 'ALTER TABLE `venta` ADD COLUMN `idalmacen` INT(11) DEFAULT NULL AFTER `idusuario`, ADD KEY `idx_venta_almacen` (`idalmacen`)', 'SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @c = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME='ingreso' AND COLUMN_NAME='idalmacen');
SET @s = IF(@c=0, 'ALTER TABLE `ingreso` ADD COLUMN `idalmacen` INT(11) DEFAULT NULL AFTER `idusuario`, ADD KEY `idx_ingreso_almacen` (`idalmacen`)', 'SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @c = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME='ajuste_inventario' AND COLUMN_NAME='idalmacen');
SET @s = IF(@c=0, 'ALTER TABLE `ajuste_inventario` ADD COLUMN `idalmacen` INT(11) DEFAULT NULL AFTER `idvariante`, ADD KEY `idx_ajuste_almacen` (`idalmacen`)', 'SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @c = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME='conteo_inventario' AND COLUMN_NAME='idalmacen');
SET @s = IF(@c=0, 'ALTER TABLE `conteo_inventario` ADD COLUMN `idalmacen` INT(11) DEFAULT NULL AFTER `idcategoria`', 'SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @c = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME='nota_credito' AND COLUMN_NAME='idalmacen');
SET @s = IF(@c=0, 'ALTER TABLE `nota_credito` ADD COLUMN `idalmacen` INT(11) DEFAULT NULL AFTER `idcliente`', 'SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @c = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME='lote' AND COLUMN_NAME='idalmacen');
SET @s = IF(@c=0, 'ALTER TABLE `lote` ADD COLUMN `idalmacen` INT(11) DEFAULT NULL AFTER `idarticulo`, ADD KEY `idx_lote_almacen` (`idalmacen`,`idarticulo`)', 'SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @c = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME='usuario' AND COLUMN_NAME='idalmacen');
SET @s = IF(@c=0, 'ALTER TABLE `usuario` ADD COLUMN `idalmacen` INT(11) DEFAULT NULL', 'SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

UPDATE `venta` SET idalmacen=@principal WHERE idalmacen IS NULL;
UPDATE `ingreso` SET idalmacen=@principal WHERE idalmacen IS NULL;
UPDATE `ajuste_inventario` SET idalmacen=@principal WHERE idalmacen IS NULL;
UPDATE `conteo_inventario` SET idalmacen=@principal WHERE idalmacen IS NULL;
UPDATE `nota_credito` SET idalmacen=@principal WHERE idalmacen IS NULL;
UPDATE `lote` SET idalmacen=@principal WHERE idalmacen IS NULL;
UPDATE `usuario` SET idalmacen=@principal WHERE idalmacen IS NULL;

CREATE TABLE IF NOT EXISTS `transferencia` (
  `idtransferencia` int(11) NOT NULL AUTO_INCREMENT,
  `idorigen` int(11) NOT NULL,
  `iddestino` int(11) NOT NULL,
  `idusuario` int(11) NOT NULL,
  `fecha_hora` datetime NOT NULL,
  `estado` varchar(12) NOT NULL DEFAULT 'ENVIADA',
  `observacion` varchar(200) DEFAULT NULL,
  `idusuario_recibe` int(11) DEFAULT NULL,
  `fecha_recepcion` datetime DEFAULT NULL,
  `observacion_recepcion` varchar(200) DEFAULT NULL,
  PRIMARY KEY (`idtransferencia`),
  KEY `idx_transf_estado` (`estado`),
  KEY `idx_transf_fecha` (`fecha_hora`),
  CONSTRAINT `fk_transf_origen` FOREIGN KEY (`idorigen`) REFERENCES `almacen` (`idalmacen`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_transf_destino` FOREIGN KEY (`iddestino`) REFERENCES `almacen` (`idalmacen`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_transf_usuario` FOREIGN KEY (`idusuario`) REFERENCES `usuario` (`idusuario`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

CREATE TABLE IF NOT EXISTS `detalle_transferencia` (
  `iddetalle` int(11) NOT NULL AUTO_INCREMENT,
  `idtransferencia` int(11) NOT NULL,
  `idarticulo` int(11) NOT NULL,
  `idvariante` int(11) DEFAULT NULL,
  `idlote_transito` int(11) DEFAULT NULL,
  `lote_codigo` varchar(40) DEFAULT NULL,
  `lote_vencimiento` date DEFAULT NULL,
  `costo_unitario` decimal(11,2) NOT NULL DEFAULT 0.00,
  `cantidad` decimal(14,3) NOT NULL,
  `cantidad_recibida` decimal(14,3) DEFAULT NULL,
  PRIMARY KEY (`iddetalle`),
  KEY `idx_dtransf_transf` (`idtransferencia`),
  KEY `idx_dtransf_articulo` (`idarticulo`),
  CONSTRAINT `fk_dtransf_transf` FOREIGN KEY (`idtransferencia`) REFERENCES `transferencia` (`idtransferencia`) ON DELETE CASCADE ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

INSERT IGNORE INTO `permiso` (`idpermiso`,`nombre`) VALUES (18,'Almacenes');

-- Triggers: ademas del total, mueven el stock del almacen del documento
DROP TRIGGER IF EXISTS `tr_updStockIngreso`;
CREATE TRIGGER `tr_updStockIngreso` AFTER INSERT ON `detalle_ingreso` FOR EACH ROW BEGIN
UPDATE articulo a
LEFT JOIN articulo_variante v ON v.idvariante = NEW.idvariante AND v.idarticulo = NEW.idarticulo
SET a.stock = a.stock + (NEW.cantidad * NEW.factor), v.stock = v.stock + (NEW.cantidad * NEW.factor)
WHERE a.idarticulo = NEW.idarticulo;
INSERT INTO stock_almacen (idalmacen, idarticulo, idvariante, stock)
VALUES (IFNULL((SELECT i.idalmacen FROM ingreso i WHERE i.idingreso = NEW.idingreso), (SELECT al.idalmacen FROM almacen al WHERE al.principal = 1 ORDER BY al.idalmacen LIMIT 1)),
        NEW.idarticulo, IFNULL(NEW.idvariante, 0), NEW.cantidad * NEW.factor)
ON DUPLICATE KEY UPDATE stock = stock + VALUES(stock);
END;

DROP TRIGGER IF EXISTS `tr_udpStockVenta`;
CREATE TRIGGER `tr_udpStockVenta` AFTER INSERT ON `detalle_venta` FOR EACH ROW BEGIN
UPDATE articulo a
LEFT JOIN articulo_variante v ON v.idvariante = NEW.idvariante AND v.idarticulo = NEW.idarticulo
SET a.stock = a.stock - (NEW.cantidad * NEW.factor), v.stock = v.stock - (NEW.cantidad * NEW.factor)
WHERE a.idarticulo = NEW.idarticulo;
INSERT INTO stock_almacen (idalmacen, idarticulo, idvariante, stock)
VALUES (IFNULL((SELECT ve.idalmacen FROM venta ve WHERE ve.idventa = NEW.idventa), (SELECT al.idalmacen FROM almacen al WHERE al.principal = 1 ORDER BY al.idalmacen LIMIT 1)),
        NEW.idarticulo, IFNULL(NEW.idvariante, 0), -(NEW.cantidad * NEW.factor))
ON DUPLICATE KEY UPDATE stock = stock + VALUES(stock);
END;

-- Kardex con almacen y traslados (TRASLADO - sale del origen, TRASLADO + entra al destino)
CREATE OR REPLACE VIEW `kardex_movimiento` AS
SELECT di.idarticulo, di.idvariante, i.idalmacen, i.fecha_hora, 'INGRESO' AS tipo,
  CONCAT(i.tipo_comprobante,' ',i.serie_comprobante,'-',i.num_comprobante) AS documento,
  IFNULL(p.nombre,'-') AS tercero,
  di.cantidad*di.factor AS entrada, 0.000 AS salida,
  di.precio_compra/di.factor AS costo, di.precio_venta AS precio_ref
FROM detalle_ingreso di
INNER JOIN ingreso i ON i.idingreso=di.idingreso
LEFT JOIN persona p ON p.idpersona=i.idproveedor
WHERE i.estado='Aceptado'
UNION ALL
SELECT dv.idarticulo, dv.idvariante, v.idalmacen, v.fecha_hora, 'VENTA',
  CONCAT(v.tipo_comprobante,' ',v.serie_comprobante,'-',v.num_comprobante),
  IFNULL(p.nombre,'-'),
  0.000, dv.cantidad*dv.factor,
  IFNULL((SELECT di2.precio_compra/di2.factor FROM detalle_ingreso di2 INNER JOIN ingreso i2 ON i2.idingreso=di2.idingreso
          WHERE di2.idarticulo=dv.idarticulo AND i2.estado='Aceptado' AND i2.fecha_hora<=v.fecha_hora
          ORDER BY i2.fecha_hora DESC, di2.iddetalle_ingreso DESC LIMIT 1), a.precio_compra),
  dv.precio_venta
FROM detalle_venta dv
INNER JOIN venta v ON v.idventa=dv.idventa
INNER JOIN articulo a ON a.idarticulo=dv.idarticulo
LEFT JOIN persona p ON p.idpersona=v.idcliente
WHERE v.estado='Aceptado'
UNION ALL
SELECT d.idarticulo, d.idvariante, n.idalmacen, n.fecha_hora, IF(d.reingresa_stock=1,'DEVOLUCION','DEVOLUCION (DAÑADO)'),
  CONCAT('NC ',n.serie,'-',n.numero,' de ',v.serie_comprobante,'-',v.num_comprobante),
  IFNULL(p.nombre,'-'),
  IF(d.reingresa_stock=1, d.cantidad*d.factor, 0), 0.000, a.precio_compra, d.precio
FROM detalle_nota_credito d
INNER JOIN nota_credito n ON n.idnota=d.idnota
INNER JOIN venta v ON v.idventa=n.idventa
INNER JOIN articulo a ON a.idarticulo=d.idarticulo
LEFT JOIN persona p ON p.idpersona=n.idcliente
WHERE n.estado='EMITIDA' AND n.tipo_nota<>'01' AND v.estado='Aceptado'
UNION ALL
SELECT aj.idarticulo, aj.idvariante, aj.idalmacen, aj.fecha_hora, IF(aj.tipo='ENTRADA','AJUSTE +','AJUSTE -'),
  CONCAT('AJUSTE #',aj.idajuste,' ',aj.motivo),
  IFNULL(u.nombre,'-'),
  IF(aj.tipo='ENTRADA',aj.cantidad,0), IF(aj.tipo='SALIDA',aj.cantidad,0), aj.costo_unitario, 0
FROM ajuste_inventario aj
LEFT JOIN usuario u ON u.idusuario=aj.idusuario
UNION ALL
SELECT d.idarticulo, d.idvariante, t.idorigen, t.fecha_hora, 'TRASLADO -',
  CONCAT('TRANSF. #',t.idtransferencia,' a ',ad.nombre),
  IFNULL(u.nombre,'-'),
  0.000, d.cantidad, d.costo_unitario, 0
FROM detalle_transferencia d
INNER JOIN transferencia t ON t.idtransferencia=d.idtransferencia
INNER JOIN almacen ad ON ad.idalmacen=t.iddestino
LEFT JOIN usuario u ON u.idusuario=t.idusuario
WHERE t.estado IN ('ENVIADA','RECIBIDA')
UNION ALL
SELECT d.idarticulo, d.idvariante, t.iddestino, t.fecha_recepcion, 'TRASLADO +',
  CONCAT('TRANSF. #',t.idtransferencia,' de ',ao.nombre),
  IFNULL(u.nombre,'-'),
  IFNULL(d.cantidad_recibida,0), 0.000, d.costo_unitario, 0
FROM detalle_transferencia d
INNER JOIN transferencia t ON t.idtransferencia=d.idtransferencia
INNER JOIN almacen ao ON ao.idalmacen=t.idorigen
LEFT JOIN usuario u ON u.idusuario=t.idusuario_recibe
WHERE t.estado='RECIBIDA';

SET FOREIGN_KEY_CHECKS=0;

-- ------------------------------------------------------------------
-- Datos semilla
-- ------------------------------------------------------------------
INSERT IGNORE INTO `permiso` (`idpermiso`,`nombre`) VALUES (1,'Escritorio'),(2,'Almacen'),(3,'Compras'),(4,'Ventas'),(5,'Acceso'),(6,'Consulta Compras'),(7,'Consulta Ventas'),(8,'Gestion Pro'),(9,'Empresa'),(10,'Centro Inteligente'),(11,'Cuentas CxC CxP'),(12,'Backup'),(13,'Centro Reportes'),(14,'Caja'),(15,'Ajustes Inventario'),(16,'Anular documentos'),(17,'Cambiar precios y descuentos');

INSERT IGNORE INTO `unidad_medida` (`nombre`,`abreviatura`,`descripcion`,`permite_fraccion`,`condicion`) VALUES ('Unidad','und','Unidad individual',0,1),('Kilogramo','kg','Peso en kilogramo',1,1),('Gramo','g','Peso en gramo',1,1),('Litro','lt','Volumen en litro',1,1),('Mililitro','ml','Volumen en mililitro',1,1),('Metro','m','Longitud en metro',1,1),('Centimetro','cm','Longitud en centimetro',1,1),('Caja','caja','Presentacion en caja',0,1),('Paquete','paq','Presentacion en paquete',0,1),('Galon','gal','Volumen en galon',1,1);

INSERT IGNORE INTO `categoria` (`idcategoria`,`nombre`,`descripcion`,`condicion`) VALUES (1,'General','Categoria por defecto',1);

INSERT IGNORE INTO `configuracion_empresa` (`idconfig`,`nombre_comercial`,`razon_social`,`ruc`,`direccion`,`telefono`,`celular`,`correo`,`web`,`logo`,`color_primario`,`color_secundario`,`serie_boleta`,`serie_factura`,`serie_ticket`,`impuesto_default`,`moneda`,`mensaje_ticket`) VALUES (1,'Mi Tienda','','','','','','','','','#0f766e','#f59e0b','B001','F001','T001',18.00,'PEN','Gracias por su compra');

INSERT IGNORE INTO `migracion` (`archivo`) VALUES ('20260321_unidades_medida.sql'),('20260321_fase_comercial.sql'),('20260911_seguridad_inventario.sql'),('20260913_cotizaciones.sql'),('20260915_perfil_negocio.sql'),('20260916_ferreteria.sql'),('20260917_abarrotes.sql'),('20260918_ropa.sql'),('20260919_pos_ticket.sql'),('20260920_roles_permisos.sql'),('20260921_precios_arqueo.sql'),('20260922_igv_correlativo.sql'),('20260923_comprobante_qr.sql'),('20260924_conteo_inventario.sql'),('20260925_pago_mixto.sql'),('20260926_notas_credito.sql'),('20260927_almacenes.sql');

SET FOREIGN_KEY_CHECKS=1;

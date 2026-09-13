-- Migracion: Seguridad, precios en articulo, ajustes de inventario, auditoria, caja integrada
-- Fecha: 2026-09-11
-- Idempotente: puede ejecutarse varias veces sin efectos secundarios.

SET @schema_name = DATABASE();

-- ------------------------------------------------------------------
-- 1) articulo: precios de referencia y fechas
-- ------------------------------------------------------------------
SET @c = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME='articulo' AND COLUMN_NAME='precio_compra');
SET @s = IF(@c=0, 'ALTER TABLE `articulo` ADD COLUMN `precio_compra` DECIMAL(11,2) NOT NULL DEFAULT 0.00 AFTER `stock_minimo`', 'SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @c = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME='articulo' AND COLUMN_NAME='precio_venta');
SET @s = IF(@c=0, 'ALTER TABLE `articulo` ADD COLUMN `precio_venta` DECIMAL(11,2) NOT NULL DEFAULT 0.00 AFTER `precio_compra`', 'SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @c = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME='articulo' AND COLUMN_NAME='fecha_creacion');
SET @s = IF(@c=0, 'ALTER TABLE `articulo` ADD COLUMN `fecha_creacion` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP', 'SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @c = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME='articulo' AND COLUMN_NAME='fecha_actualizacion');
SET @s = IF(@c=0, 'ALTER TABLE `articulo` ADD COLUMN `fecha_actualizacion` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP', 'SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Rellenar precios desde el ultimo ingreso registrado (solo si siguen en 0)
UPDATE `articulo` a
SET a.precio_compra = IFNULL((SELECT di.precio_compra FROM detalle_ingreso di WHERE di.idarticulo=a.idarticulo ORDER BY di.iddetalle_ingreso DESC LIMIT 1), 0)
WHERE a.precio_compra = 0;

UPDATE `articulo` a
SET a.precio_venta = IFNULL((SELECT di.precio_venta FROM detalle_ingreso di WHERE di.idarticulo=a.idarticulo ORDER BY di.iddetalle_ingreso DESC LIMIT 1), 0)
WHERE a.precio_venta = 0;

SET @c = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME='articulo' AND INDEX_NAME='idx_articulo_codigo');
SET @s = IF(@c=0, 'ALTER TABLE `articulo` ADD KEY `idx_articulo_codigo` (`codigo`)', 'SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ------------------------------------------------------------------
-- 2) persona: baja logica
-- ------------------------------------------------------------------
SET @c = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME='persona' AND COLUMN_NAME='condicion');
SET @s = IF(@c=0, 'ALTER TABLE `persona` ADD COLUMN `condicion` TINYINT(4) NOT NULL DEFAULT 1', 'SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ------------------------------------------------------------------
-- 3) usuario: hash bcrypt (255) y ultimo acceso
-- ------------------------------------------------------------------
ALTER TABLE `usuario` MODIFY COLUMN `clave` VARCHAR(255) NOT NULL;

SET @c = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME='usuario' AND COLUMN_NAME='ultimo_acceso');
SET @s = IF(@c=0, 'ALTER TABLE `usuario` ADD COLUMN `ultimo_acceso` DATETIME NULL DEFAULT NULL', 'SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ------------------------------------------------------------------
-- 4) venta / ingreso: medio de pago y vinculo con caja
-- ------------------------------------------------------------------
SET @c = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME='venta' AND COLUMN_NAME='medio_pago');
SET @s = IF(@c=0, 'ALTER TABLE `venta` ADD COLUMN `medio_pago` VARCHAR(20) NOT NULL DEFAULT ''EFECTIVO'' AFTER `tipo_pago`', 'SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @c = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME='venta' AND COLUMN_NAME='idcaja');
SET @s = IF(@c=0, 'ALTER TABLE `venta` ADD COLUMN `idcaja` INT(11) NULL DEFAULT NULL AFTER `medio_pago`', 'SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @c = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME='venta' AND COLUMN_NAME='observacion');
SET @s = IF(@c=0, 'ALTER TABLE `venta` ADD COLUMN `observacion` VARCHAR(200) NULL DEFAULT NULL', 'SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @c = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME='ingreso' AND COLUMN_NAME='medio_pago');
SET @s = IF(@c=0, 'ALTER TABLE `ingreso` ADD COLUMN `medio_pago` VARCHAR(20) NOT NULL DEFAULT ''EFECTIVO'' AFTER `tipo_pago`', 'SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @c = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME='ingreso' AND COLUMN_NAME='observacion');
SET @s = IF(@c=0, 'ALTER TABLE `ingreso` ADD COLUMN `observacion` VARCHAR(200) NULL DEFAULT NULL', 'SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @c = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME='venta' AND INDEX_NAME='idx_venta_fecha');
SET @s = IF(@c=0, 'ALTER TABLE `venta` ADD KEY `idx_venta_fecha` (`fecha_hora`)', 'SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @c = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME='ingreso' AND INDEX_NAME='idx_ingreso_fecha');
SET @s = IF(@c=0, 'ALTER TABLE `ingreso` ADD KEY `idx_ingreso_fecha` (`fecha_hora`)', 'SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- caja_movimiento: referencia al documento origen
SET @c = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME='caja_movimiento' AND COLUMN_NAME='referencia');
SET @s = IF(@c=0, 'ALTER TABLE `caja_movimiento` ADD COLUMN `referencia` VARCHAR(40) NULL DEFAULT NULL AFTER `concepto`', 'SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @c = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME='caja_movimiento' AND COLUMN_NAME='medio_pago');
SET @s = IF(@c=0, 'ALTER TABLE `caja_movimiento` ADD COLUMN `medio_pago` VARCHAR(20) NOT NULL DEFAULT ''EFECTIVO'' AFTER `referencia`', 'SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ------------------------------------------------------------------
-- 5) Ajustes de inventario (entradas/salidas manuales con motivo)
-- ------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `ajuste_inventario` (
  `idajuste` INT(11) NOT NULL AUTO_INCREMENT,
  `idarticulo` INT(11) NOT NULL,
  `idusuario` INT(11) NOT NULL,
  `tipo` VARCHAR(10) NOT NULL COMMENT 'ENTRADA | SALIDA',
  `motivo` VARCHAR(40) NOT NULL COMMENT 'CONTEO, MERMA, VENCIMIENTO, DEVOLUCION_CLIENTE, DEVOLUCION_PROVEEDOR, ROBO, DONACION, USO_INTERNO, INICIAL, OTRO',
  `cantidad` DECIMAL(14,3) NOT NULL,
  `stock_anterior` DECIMAL(14,3) NOT NULL,
  `stock_nuevo` DECIMAL(14,3) NOT NULL,
  `costo_unitario` DECIMAL(11,2) NOT NULL DEFAULT 0.00,
  `observacion` VARCHAR(200) DEFAULT NULL,
  `fecha_hora` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`idajuste`),
  KEY `fk_ajuste_articulo_idx` (`idarticulo`),
  KEY `fk_ajuste_usuario_idx` (`idusuario`),
  KEY `idx_ajuste_fecha` (`fecha_hora`),
  CONSTRAINT `fk_ajuste_articulo` FOREIGN KEY (`idarticulo`) REFERENCES `articulo` (`idarticulo`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_ajuste_usuario` FOREIGN KEY (`idusuario`) REFERENCES `usuario` (`idusuario`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------------
-- 6) Seguridad: intentos de login y auditoria
-- ------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `intento_login` (
  `idintento` INT(11) NOT NULL AUTO_INCREMENT,
  `login` VARCHAR(60) DEFAULT NULL,
  `ip` VARCHAR(45) DEFAULT NULL,
  `exito` TINYINT(1) NOT NULL DEFAULT 0,
  `fecha_hora` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`idintento`),
  KEY `idx_intento_login` (`login`, `fecha_hora`),
  KEY `idx_intento_ip` (`ip`, `fecha_hora`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `auditoria` (
  `idauditoria` INT(11) NOT NULL AUTO_INCREMENT,
  `idusuario` INT(11) DEFAULT NULL,
  `usuario` VARCHAR(60) DEFAULT NULL,
  `modulo` VARCHAR(40) NOT NULL,
  `accion` VARCHAR(40) NOT NULL,
  `detalle` VARCHAR(255) DEFAULT NULL,
  `ip` VARCHAR(45) DEFAULT NULL,
  `fecha_hora` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`idauditoria`),
  KEY `idx_auditoria_fecha` (`fecha_hora`),
  KEY `idx_auditoria_usuario` (`idusuario`),
  KEY `idx_auditoria_modulo` (`modulo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------------
-- 7) Permisos ampliados (ids fijos usados por config/seguridad.php)
-- ------------------------------------------------------------------
INSERT IGNORE INTO `permiso` (`idpermiso`, `nombre`) VALUES
(8,  'Gestion Pro'),
(9,  'Empresa'),
(10, 'Centro Inteligente'),
(11, 'Cuentas CxC CxP'),
(12, 'Backup'),
(13, 'Centro Reportes'),
(14, 'Caja'),
(15, 'Ajustes Inventario');

-- Administradores (permiso 5 = Acceso) reciben todos los permisos nuevos
INSERT INTO `usuario_permiso` (`idusuario`, `idpermiso`)
SELECT up.idusuario, p.idpermiso
FROM usuario_permiso up
JOIN permiso p ON p.idpermiso IN (8,9,10,11,12,13,14,15)
WHERE up.idpermiso = 5
AND NOT EXISTS (SELECT 1 FROM usuario_permiso x WHERE x.idusuario=up.idusuario AND x.idpermiso=p.idpermiso);

-- Ventas (4) -> Caja (14) y Cuentas (11)
INSERT INTO `usuario_permiso` (`idusuario`, `idpermiso`)
SELECT up.idusuario, p.idpermiso
FROM usuario_permiso up
JOIN permiso p ON p.idpermiso IN (14, 11)
WHERE up.idpermiso = 4
AND NOT EXISTS (SELECT 1 FROM usuario_permiso x WHERE x.idusuario=up.idusuario AND x.idpermiso=p.idpermiso);

-- Compras (3) -> Cuentas (11)
INSERT INTO `usuario_permiso` (`idusuario`, `idpermiso`)
SELECT up.idusuario, 11
FROM usuario_permiso up
WHERE up.idpermiso = 3
AND NOT EXISTS (SELECT 1 FROM usuario_permiso x WHERE x.idusuario=up.idusuario AND x.idpermiso=11);

-- Almacen (2) -> Ajustes Inventario (15) y Centro Inteligente (10)
INSERT INTO `usuario_permiso` (`idusuario`, `idpermiso`)
SELECT up.idusuario, p.idpermiso
FROM usuario_permiso up
JOIN permiso p ON p.idpermiso IN (15, 10)
WHERE up.idpermiso = 2
AND NOT EXISTS (SELECT 1 FROM usuario_permiso x WHERE x.idusuario=up.idusuario AND x.idpermiso=p.idpermiso);

-- Consultas (6,7) -> Centro Reportes (13)
INSERT INTO `usuario_permiso` (`idusuario`, `idpermiso`)
SELECT DISTINCT up.idusuario, 13
FROM usuario_permiso up
WHERE up.idpermiso IN (6,7)
AND NOT EXISTS (SELECT 1 FROM usuario_permiso x WHERE x.idusuario=up.idusuario AND x.idpermiso=13);

-- ------------------------------------------------------------------
-- 8) configuracion_empresa: textos de comprobante
-- ------------------------------------------------------------------
SET @c = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME='configuracion_empresa' AND COLUMN_NAME='mensaje_ticket');
SET @s = IF(@c=0, 'ALTER TABLE `configuracion_empresa` ADD COLUMN `mensaje_ticket` VARCHAR(160) NOT NULL DEFAULT ''Gracias por su compra''', 'SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ------------------------------------------------------------------
-- 9) Control de migraciones aplicadas
-- ------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `migracion` (
  `idmigracion` INT(11) NOT NULL AUTO_INCREMENT,
  `archivo` VARCHAR(120) NOT NULL,
  `aplicada_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`idmigracion`),
  UNIQUE KEY `uq_migracion_archivo` (`archivo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO `migracion` (`archivo`) VALUES
('20260321_unidades_medida.sql'),
('20260321_fase_comercial.sql'),
('20260911_seguridad_inventario.sql');

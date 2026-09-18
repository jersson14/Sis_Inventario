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

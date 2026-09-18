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

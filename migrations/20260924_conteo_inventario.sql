-- Migracion: toma de inventario (conteo fisico con lector de barras)
-- Fecha: 2026-09-24
-- Idempotente.
--
-- conteo_inventario   una toma de inventario: todo el almacen o una categoria.
--                     Solo puede haber una ABIERTA a la vez (dos conteos
--                     abiertos aplicarian dos veces la misma diferencia).
--                     estado: ABIERTO | APLICADO | ANULADO
--                     por_lote = 1: se cuenta cada lote por separado
-- conteo_detalle      lo contado por articulo / talla-color / lote.
--                     stock_sistema = stock que tenia el sistema en la ultima
--                     lectura de esa linea; al aplicar se ajusta la diferencia
--                     (contado - stock_sistema) sobre el stock actual, asi las
--                     ventas hechas durante el conteo no se descuadran.
--                     idlote = 0 y lote_clave = '' : stock sin lote
--                     idlote = 0 y lote_clave <> '': lote nuevo encontrado al contar
-- ajuste_inventario.idconteo   ajustes generados al aplicar un conteo
-- lote_movimiento.tipo         admite ENTRADA (ajuste que suma a un lote existente)

SET @schema_name = DATABASE();

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

SET @c = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME='ajuste_inventario' AND COLUMN_NAME='idconteo');
SET @s = IF(@c=0, 'ALTER TABLE `ajuste_inventario` ADD COLUMN `idconteo` INT(11) DEFAULT NULL AFTER `observacion`, ADD KEY `idx_ajuste_conteo` (`idconteo`)', 'SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

ALTER TABLE `lote_movimiento` MODIFY `tipo` varchar(20) NOT NULL COMMENT 'VENTA | AJUSTE | ENTRADA';

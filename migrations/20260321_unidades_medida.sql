-- Migracion: Unidades de medida administrables + cantidades decimales
-- Fecha: 2026-03-21
-- Script seguro para reintento (si quedo a medias)

START TRANSACTION;

CREATE TABLE IF NOT EXISTS `unidad_medida` (
  `idunidad` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(60) NOT NULL,
  `abreviatura` varchar(10) NOT NULL,
  `descripcion` varchar(120) DEFAULT NULL,
  `condicion` tinyint(4) NOT NULL DEFAULT '1',
  PRIMARY KEY (`idunidad`),
  UNIQUE KEY `nombre_UNIQUE` (`nombre`),
  UNIQUE KEY `abreviatura_UNIQUE` (`abreviatura`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT IGNORE INTO `unidad_medida` (`nombre`, `abreviatura`, `descripcion`, `condicion`) VALUES
('Unidad', 'und', 'Unidad individual', 1),
('Kilogramo', 'kg', 'Peso en kilogramo', 1),
('Gramo', 'g', 'Peso en gramo', 1),
('Litro', 'lt', 'Volumen en litro', 1),
('Mililitro', 'ml', 'Volumen en mililitro', 1),
('Metro', 'm', 'Longitud en metro', 1),
('Centimetro', 'cm', 'Longitud en centimetro', 1),
('Caja', 'caja', 'Presentacion en caja', 1),
('Paquete', 'paq', 'Presentacion en paquete', 1);

SET @schema_name = DATABASE();

SET @col_idunidad_exists = (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA=@schema_name
    AND TABLE_NAME='articulo'
    AND COLUMN_NAME='idunidad'
);

SET @sql_add_column = IF(
  @col_idunidad_exists = 0,
  'ALTER TABLE `articulo` ADD COLUMN `idunidad` int(11) NULL AFTER `idcategoria`',
  'SELECT 1'
);
PREPARE stmt FROM @sql_add_column;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @idunidad_und = (
  SELECT idunidad
  FROM unidad_medida
  WHERE abreviatura='und'
  LIMIT 1
);

UPDATE `articulo` a
LEFT JOIN `unidad_medida` u ON u.idunidad = a.idunidad
SET a.idunidad = @idunidad_und
WHERE a.idunidad IS NULL OR a.idunidad=0 OR u.idunidad IS NULL;

ALTER TABLE `articulo`
  MODIFY COLUMN `stock` decimal(14,3) NOT NULL DEFAULT 0.000,
  MODIFY COLUMN `idunidad` int(11) NOT NULL;

SET @idx_exists = (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA=@schema_name
    AND TABLE_NAME='articulo'
    AND INDEX_NAME='fk_articulo_unidad_idx'
);

SET @sql_add_index = IF(
  @idx_exists = 0,
  'ALTER TABLE `articulo` ADD KEY `fk_articulo_unidad_idx` (`idunidad`)',
  'SELECT 1'
);
PREPARE stmt FROM @sql_add_index;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @fk_exists = (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
  WHERE TABLE_SCHEMA=@schema_name
    AND TABLE_NAME='articulo'
    AND CONSTRAINT_NAME='fk_articulo_unidad'
    AND CONSTRAINT_TYPE='FOREIGN KEY'
);

SET @sql_add_fk = IF(
  @fk_exists = 0,
  'ALTER TABLE `articulo` ADD CONSTRAINT `fk_articulo_unidad` FOREIGN KEY (`idunidad`) REFERENCES `unidad_medida` (`idunidad`) ON DELETE NO ACTION ON UPDATE NO ACTION',
  'SELECT 1'
);
PREPARE stmt FROM @sql_add_fk;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

ALTER TABLE `detalle_ingreso`
  MODIFY COLUMN `cantidad` decimal(14,3) NOT NULL;

ALTER TABLE `detalle_venta`
  MODIFY COLUMN `cantidad` decimal(14,3) NOT NULL;

COMMIT;

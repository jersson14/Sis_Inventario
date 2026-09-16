-- Migracion: Perfil de negocio (rubro)
-- Fecha: 2026-09-15
-- Idempotente.
--
-- Define a que rubro pertenece la tienda. El perfil decide que funciones se
-- habilitan (vencimientos en abarrotes, fracciones en ferreteria, tallas y
-- colores en ropa) y como se nombran las cosas en la interfaz.
-- Valores: GENERAL | ABARROTES | FERRETERIA | ROPA

SET @schema_name = DATABASE();

SET @c = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME='configuracion_empresa' AND COLUMN_NAME='tipo_negocio');
SET @s = IF(@c=0, 'ALTER TABLE `configuracion_empresa` ADD COLUMN `tipo_negocio` VARCHAR(20) NOT NULL DEFAULT ''GENERAL'' COMMENT ''GENERAL | ABARROTES | FERRETERIA | ROPA'' AFTER `moneda`', 'SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

INSERT IGNORE INTO `migracion` (`archivo`) VALUES ('20260915_perfil_negocio.sql');

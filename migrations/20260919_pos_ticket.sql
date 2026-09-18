-- Migracion: POS de ventas, ticket termico configurable y pagos por deposito
-- Fecha: 2026-09-19
-- Idempotente.
--
-- configuracion_empresa:
--   ticket_ancho          80 o 58 (mm del rollo de la ticketera)
--   ticket_auto_imprimir  1 = el POS imprime el ticket al cobrar
--   ticket_logo           1 = el ticket lleva el logo
--   ticket_cabecera       texto libre bajo los datos de la empresa (horario, redes...)
--   ticket_copias         copias por venta (1-3)
-- venta:
--   num_operacion   N de operacion de Yape/Plin/tarjeta/deposito
--   monto_recibido  efectivo entregado por el cliente (para el vuelto del ticket)
-- ingreso:
--   cuenta_pago     cuenta o banco del proveedor donde se deposito/transfirio
--   num_operacion   N de operacion del deposito/transferencia

SET @schema_name = DATABASE();

SET @c = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME='configuracion_empresa' AND COLUMN_NAME='ticket_ancho');
SET @s = IF(@c=0, 'ALTER TABLE `configuracion_empresa` ADD COLUMN `ticket_ancho` INT(11) NOT NULL DEFAULT 80, ADD COLUMN `ticket_auto_imprimir` TINYINT(1) NOT NULL DEFAULT 1, ADD COLUMN `ticket_logo` TINYINT(1) NOT NULL DEFAULT 1, ADD COLUMN `ticket_cabecera` VARCHAR(200) DEFAULT NULL, ADD COLUMN `ticket_copias` INT(11) NOT NULL DEFAULT 1', 'SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @c = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME='venta' AND COLUMN_NAME='num_operacion');
SET @s = IF(@c=0, 'ALTER TABLE `venta` ADD COLUMN `num_operacion` VARCHAR(40) DEFAULT NULL AFTER `medio_pago`, ADD COLUMN `monto_recibido` DECIMAL(11,2) DEFAULT NULL AFTER `total_venta`', 'SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @c = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME='ingreso' AND COLUMN_NAME='num_operacion');
SET @s = IF(@c=0, 'ALTER TABLE `ingreso` ADD COLUMN `cuenta_pago` VARCHAR(80) DEFAULT NULL AFTER `medio_pago`, ADD COLUMN `num_operacion` VARCHAR(40) DEFAULT NULL AFTER `cuenta_pago`', 'SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

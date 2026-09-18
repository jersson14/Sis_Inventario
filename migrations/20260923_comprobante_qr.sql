-- Migracion: QR en el ticket y consulta publica del comprobante
-- Fecha: 2026-09-23
-- Idempotente.
--
-- venta:
--   codigo_publico   clave aleatoria (16 caracteres) del enlace que lleva el QR:
--                    comprobante.php?c=CODIGO muestra solo esa venta, sin login.
--                    Se crea al imprimir el ticket o el PDF por primera vez.
-- configuracion_empresa:
--   url_publica      direccion con la que los clientes entran al sistema desde
--                    internet (ej. https://mitienda.pe). Vacia = la del navegador.
--   ticket_qr        1 = el ticket y el PDF llevan el QR
--   ticket_leyenda   aviso al pie (canje por comprobante electronico SUNAT)

SET @schema_name = DATABASE();

SET @c = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME='venta' AND COLUMN_NAME='codigo_publico');
SET @s = IF(@c=0, 'ALTER TABLE `venta` ADD COLUMN `codigo_publico` VARCHAR(20) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL AFTER `observacion`, ADD UNIQUE KEY `uq_venta_codigo_publico` (`codigo_publico`)', 'SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @c = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME='configuracion_empresa' AND COLUMN_NAME='ticket_qr');
SET @s = IF(@c=0, 'ALTER TABLE `configuracion_empresa` ADD COLUMN `url_publica` VARCHAR(200) DEFAULT NULL, ADD COLUMN `ticket_qr` TINYINT(1) NOT NULL DEFAULT 1, ADD COLUMN `ticket_leyenda` VARCHAR(250) DEFAULT ''Este comprobante interno puede canjearse por una boleta o factura electrónica válida ante SUNAT. Solicítela en tienda presentando este ticket.''', 'SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

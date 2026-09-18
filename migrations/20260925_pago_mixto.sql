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

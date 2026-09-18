-- Migracion: IGV guardado como porcentaje
-- Fecha: 2026-09-22
-- Idempotente (despues de la primera vez ya no hay valores entre 0 y 1).
--
-- Documentos antiguos (2021) guardaron el impuesto como fraccion (0.18) en vez
-- de porcentaje (18). Los comprobantes calculan la base con total/(1+imp/100),
-- asi que con 0.18 mostraban un IGV casi nulo. Se corrigen a porcentaje.
-- No se tocan los totales ni la numeracion: solo el porcentaje guardado.

UPDATE `venta`      SET `impuesto` = ROUND(`impuesto` * 100, 2) WHERE `impuesto` > 0 AND `impuesto` < 1;
UPDATE `ingreso`    SET `impuesto` = ROUND(`impuesto` * 100, 2) WHERE `impuesto` > 0 AND `impuesto` < 1;
UPDATE `cotizacion` SET `impuesto` = ROUND(`impuesto` * 100, 2) WHERE `impuesto` > 0 AND `impuesto` < 1;

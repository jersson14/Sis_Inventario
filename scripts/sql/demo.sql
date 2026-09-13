-- Datos de demostracion para Mi Tienda (ferreteria de ejemplo).
-- Requiere el esquema base y al menos un usuario creado (el instalador crea el administrador).
-- Idempotente a nivel de articulos/personas (INSERT IGNORE por nombre/documento); ventas y compras se insertan una sola vez
-- gracias a la comprobacion de comprobantes existentes.

SET NAMES utf8mb4;
SET @u = (SELECT MIN(idusuario) FROM usuario);

-- Categorias
INSERT IGNORE INTO categoria (nombre, descripcion, condicion) VALUES
('Ferretería', 'Pernos, tuercas, clavos y fijaciones', 1),
('Pinturas', 'Pinturas, esmaltes y accesorios', 1),
('Eléctricos', 'Cables, tomacorrientes e iluminación', 1),
('Herramientas', 'Herramientas manuales y eléctricas', 1);

SET @c_fer = (SELECT idcategoria FROM categoria WHERE nombre='Ferretería');
SET @c_pin = (SELECT idcategoria FROM categoria WHERE nombre='Pinturas');
SET @c_ele = (SELECT idcategoria FROM categoria WHERE nombre='Eléctricos');
SET @c_her = (SELECT idcategoria FROM categoria WHERE nombre='Herramientas');
SET @u_und = (SELECT idunidad FROM unidad_medida WHERE abreviatura='und');
SET @u_kg  = (SELECT idunidad FROM unidad_medida WHERE abreviatura='kg');
SET @u_gal = (SELECT idunidad FROM unidad_medida WHERE abreviatura='gal');
SET @u_m   = (SELECT idunidad FROM unidad_medida WHERE abreviatura='m');
SET @u_caja= (SELECT idunidad FROM unidad_medida WHERE abreviatura='caja');

-- Articulos (stock inicial 0: lo cargan los ingresos de demo)
INSERT IGNORE INTO articulo (idcategoria, idunidad, codigo, nombre, stock, stock_minimo, precio_compra, precio_venta, descripcion, imagen, condicion) VALUES
(@c_fer, @u_und, 'FER-100001', 'Perno hexagonal 1/2" x 2"', 0, 50, 0.80, 1.50, 'Acero zincado', '', 1),
(@c_fer, @u_und, 'FER-100002', 'Tuerca hexagonal 1/2"', 0, 50, 0.30, 0.60, 'Acero zincado', '', 1),
(@c_fer, @u_kg,  'FER-100003', 'Clavo 2 1/2" (kg)', 0, 20, 4.50, 7.00, 'Clavo de acero', '', 1),
(@c_fer, @u_und, 'FER-100004', 'Arandela plana 1/2"', 0, 100, 0.10, 0.25, '', '', 1),
(@c_fer, @u_caja,'FER-100005', 'Tornillo drywall 6x1" (caja x100)', 0, 10, 6.00, 9.50, '', '', 1),
(@c_pin, @u_gal, 'PIN-200001', 'Pintura látex blanco (galón)', 0, 5, 28.00, 42.00, 'Interior/exterior', '', 1),
(@c_pin, @u_gal, 'PIN-200002', 'Esmalte sintético negro (galón)', 0, 5, 35.00, 52.00, '', '', 1),
(@c_pin, @u_und, 'PIN-200003', 'Rodillo 9" con mango', 0, 5, 6.50, 12.00, '', '', 1),
(@c_ele, @u_m,   'ELE-300001', 'Cable THW 12 AWG (metro)', 0, 100, 1.80, 3.00, 'Indeco', '', 1),
(@c_ele, @u_und, 'ELE-300002', 'Tomacorriente doble', 0, 10, 4.00, 7.50, '', '', 1),
(@c_ele, @u_und, 'ELE-300003', 'Foco LED 9W', 0, 20, 5.50, 9.90, 'Luz blanca', '', 1),
(@c_her, @u_und, 'HER-400001', 'Martillo de carpintero 16 oz', 0, 3, 18.00, 29.00, '', '', 1),
(@c_her, @u_und, 'HER-400002', 'Alicate universal 8"', 0, 3, 15.00, 24.00, '', '', 1),
(@c_her, @u_und, 'HER-400003', 'Taladro percutor 650W', 0, 2, 150.00, 219.00, '', '', 1);

-- Clientes y proveedores
INSERT IGNORE INTO persona (tipo_persona, nombre, tipo_documento, num_documento, direccion, telefono, email, condicion) VALUES
('Cliente', 'Público general', 'DNI', '00000000', '', '', '', 1),
('Cliente', 'Constructora Andes SAC', 'RUC', '20512345678', 'Av. Principal 123', '987654321', 'compras@andes.pe', 1),
('Cliente', 'María Quispe', 'DNI', '45678912', 'Jr. Los Pinos 45', '956123456', 'maria.q@gmail.com', 1),
('Cliente', 'José Huamán', 'DNI', '41234567', 'Calle Sol 78', '944556677', '', 1),
('Proveedor', 'Distribuidora Ferretera Perú SAC', 'RUC', '20601234567', 'Av. Industrial 500', '01-5551234', 'ventas@ferreperu.com', 1),
('Proveedor', 'Pinturas del Sur EIRL', 'RUC', '20609876543', 'Jr. Comercio 210', '01-5559876', 'pedidos@pintasur.pe', 1),
('Proveedor', 'Electro Import SAC', 'RUC', '20605555555', 'Av. Argentina 1500', '01-5550000', 'ventas@electroimport.pe', 1);

SET @cli1 = (SELECT idpersona FROM persona WHERE num_documento='00000000' AND tipo_persona='Cliente');
SET @cli2 = (SELECT idpersona FROM persona WHERE num_documento='20512345678');
SET @cli3 = (SELECT idpersona FROM persona WHERE num_documento='45678912');
SET @cli4 = (SELECT idpersona FROM persona WHERE num_documento='41234567');
SET @prov1 = (SELECT idpersona FROM persona WHERE num_documento='20601234567');
SET @prov2 = (SELECT idpersona FROM persona WHERE num_documento='20609876543');
SET @prov3 = (SELECT idpersona FROM persona WHERE num_documento='20605555555');

-- Ids de articulos
SET @a1 = (SELECT idarticulo FROM articulo WHERE codigo='FER-100001');
SET @a2 = (SELECT idarticulo FROM articulo WHERE codigo='FER-100002');
SET @a3 = (SELECT idarticulo FROM articulo WHERE codigo='FER-100003');
SET @a4 = (SELECT idarticulo FROM articulo WHERE codigo='FER-100004');
SET @a5 = (SELECT idarticulo FROM articulo WHERE codigo='FER-100005');
SET @a6 = (SELECT idarticulo FROM articulo WHERE codigo='PIN-200001');
SET @a7 = (SELECT idarticulo FROM articulo WHERE codigo='PIN-200002');
SET @a8 = (SELECT idarticulo FROM articulo WHERE codigo='PIN-200003');
SET @a9 = (SELECT idarticulo FROM articulo WHERE codigo='ELE-300001');
SET @a10 = (SELECT idarticulo FROM articulo WHERE codigo='ELE-300002');
SET @a11 = (SELECT idarticulo FROM articulo WHERE codigo='ELE-300003');
SET @a12 = (SELECT idarticulo FROM articulo WHERE codigo='HER-400001');
SET @a13 = (SELECT idarticulo FROM articulo WHERE codigo='HER-400002');
SET @a14 = (SELECT idarticulo FROM articulo WHERE codigo='HER-400003');

-- Compras (solo si no existen ya los comprobantes de demo)
INSERT INTO ingreso (idproveedor, idusuario, tipo_comprobante, serie_comprobante, num_comprobante, fecha_hora, impuesto, tipo_pago, medio_pago, total_compra, estado, observacion)
SELECT @prov1, @u, 'Factura', 'F001', '00000101', DATE_SUB(NOW(), INTERVAL 25 DAY), 18, 'CONTADO', 'TRANSFERENCIA', 0, 'Aceptado', 'DEMO'
WHERE NOT EXISTS (SELECT 1 FROM ingreso WHERE serie_comprobante='F001' AND num_comprobante='00000101' AND tipo_comprobante='Factura');
SET @i1 = (SELECT idingreso FROM ingreso WHERE serie_comprobante='F001' AND num_comprobante='00000101' AND tipo_comprobante='Factura');
INSERT INTO detalle_ingreso (idingreso, idarticulo, cantidad, precio_compra, precio_venta)
SELECT @i1, @a1, 500, 0.80, 1.50 WHERE NOT EXISTS (SELECT 1 FROM detalle_ingreso WHERE idingreso=@i1);
INSERT INTO detalle_ingreso (idingreso, idarticulo, cantidad, precio_compra, precio_venta) SELECT @i1, @a2, 500, 0.30, 0.60 WHERE (SELECT COUNT(*) FROM detalle_ingreso WHERE idingreso=@i1)=1;
INSERT INTO detalle_ingreso (idingreso, idarticulo, cantidad, precio_compra, precio_venta) SELECT @i1, @a3, 60, 4.50, 7.00 WHERE (SELECT COUNT(*) FROM detalle_ingreso WHERE idingreso=@i1)=2;
INSERT INTO detalle_ingreso (idingreso, idarticulo, cantidad, precio_compra, precio_venta) SELECT @i1, @a4, 1000, 0.10, 0.25 WHERE (SELECT COUNT(*) FROM detalle_ingreso WHERE idingreso=@i1)=3;
INSERT INTO detalle_ingreso (idingreso, idarticulo, cantidad, precio_compra, precio_venta) SELECT @i1, @a5, 30, 6.00, 9.50 WHERE (SELECT COUNT(*) FROM detalle_ingreso WHERE idingreso=@i1)=4;
INSERT INTO detalle_ingreso (idingreso, idarticulo, cantidad, precio_compra, precio_venta) SELECT @i1, @a12, 10, 18.00, 29.00 WHERE (SELECT COUNT(*) FROM detalle_ingreso WHERE idingreso=@i1)=5;
INSERT INTO detalle_ingreso (idingreso, idarticulo, cantidad, precio_compra, precio_venta) SELECT @i1, @a13, 10, 15.00, 24.00 WHERE (SELECT COUNT(*) FROM detalle_ingreso WHERE idingreso=@i1)=6;
INSERT INTO detalle_ingreso (idingreso, idarticulo, cantidad, precio_compra, precio_venta) SELECT @i1, @a14, 4, 150.00, 219.00 WHERE (SELECT COUNT(*) FROM detalle_ingreso WHERE idingreso=@i1)=7;
UPDATE ingreso SET total_compra = (SELECT SUM(cantidad*precio_compra) FROM detalle_ingreso WHERE idingreso=@i1) WHERE idingreso=@i1;

INSERT INTO ingreso (idproveedor, idusuario, tipo_comprobante, serie_comprobante, num_comprobante, fecha_hora, impuesto, tipo_pago, medio_pago, total_compra, estado, observacion)
SELECT @prov2, @u, 'Factura', 'F001', '00000102', DATE_SUB(NOW(), INTERVAL 20 DAY), 18, 'CREDITO', 'EFECTIVO', 0, 'Aceptado', 'DEMO'
WHERE NOT EXISTS (SELECT 1 FROM ingreso WHERE serie_comprobante='F001' AND num_comprobante='00000102' AND tipo_comprobante='Factura');
SET @i2 = (SELECT idingreso FROM ingreso WHERE serie_comprobante='F001' AND num_comprobante='00000102' AND tipo_comprobante='Factura');
INSERT INTO detalle_ingreso (idingreso, idarticulo, cantidad, precio_compra, precio_venta) SELECT @i2, @a6, 24, 28.00, 42.00 WHERE NOT EXISTS (SELECT 1 FROM detalle_ingreso WHERE idingreso=@i2);
INSERT INTO detalle_ingreso (idingreso, idarticulo, cantidad, precio_compra, precio_venta) SELECT @i2, @a7, 12, 35.00, 52.00 WHERE (SELECT COUNT(*) FROM detalle_ingreso WHERE idingreso=@i2)=1;
INSERT INTO detalle_ingreso (idingreso, idarticulo, cantidad, precio_compra, precio_venta) SELECT @i2, @a8, 20, 6.50, 12.00 WHERE (SELECT COUNT(*) FROM detalle_ingreso WHERE idingreso=@i2)=2;
UPDATE ingreso SET total_compra = (SELECT SUM(cantidad*precio_compra) FROM detalle_ingreso WHERE idingreso=@i2) WHERE idingreso=@i2;
INSERT INTO cuenta_pagar (idproveedor, idingreso, fecha_emision, fecha_vencimiento, documento_ref, monto_total, saldo, estado, observacion)
SELECT @prov2, @i2, DATE(DATE_SUB(NOW(), INTERVAL 20 DAY)), DATE(DATE_ADD(NOW(), INTERVAL 10 DAY)), 'Factura F001-00000102', (SELECT total_compra FROM ingreso WHERE idingreso=@i2), (SELECT total_compra FROM ingreso WHERE idingreso=@i2), 'PENDIENTE', 'Generada automáticamente desde compra (demo)'
WHERE NOT EXISTS (SELECT 1 FROM cuenta_pagar WHERE idingreso=@i2);

INSERT INTO ingreso (idproveedor, idusuario, tipo_comprobante, serie_comprobante, num_comprobante, fecha_hora, impuesto, tipo_pago, medio_pago, total_compra, estado, observacion)
SELECT @prov3, @u, 'Factura', 'F001', '00000103', DATE_SUB(NOW(), INTERVAL 15 DAY), 18, 'CONTADO', 'EFECTIVO', 0, 'Aceptado', 'DEMO'
WHERE NOT EXISTS (SELECT 1 FROM ingreso WHERE serie_comprobante='F001' AND num_comprobante='00000103' AND tipo_comprobante='Factura');
SET @i3 = (SELECT idingreso FROM ingreso WHERE serie_comprobante='F001' AND num_comprobante='00000103' AND tipo_comprobante='Factura');
INSERT INTO detalle_ingreso (idingreso, idarticulo, cantidad, precio_compra, precio_venta) SELECT @i3, @a9, 300, 1.80, 3.00 WHERE NOT EXISTS (SELECT 1 FROM detalle_ingreso WHERE idingreso=@i3);
INSERT INTO detalle_ingreso (idingreso, idarticulo, cantidad, precio_compra, precio_venta) SELECT @i3, @a10, 40, 4.00, 7.50 WHERE (SELECT COUNT(*) FROM detalle_ingreso WHERE idingreso=@i3)=1;
INSERT INTO detalle_ingreso (idingreso, idarticulo, cantidad, precio_compra, precio_venta) SELECT @i3, @a11, 60, 5.50, 9.90 WHERE (SELECT COUNT(*) FROM detalle_ingreso WHERE idingreso=@i3)=2;
UPDATE ingreso SET total_compra = (SELECT SUM(cantidad*precio_compra) FROM detalle_ingreso WHERE idingreso=@i3) WHERE idingreso=@i3;

-- Ventas de los ultimos dias (los triggers descuentan stock)
-- Venta 1
INSERT INTO venta (idcliente, idusuario, tipo_comprobante, serie_comprobante, num_comprobante, fecha_hora, impuesto, tipo_pago, medio_pago, total_venta, estado, observacion)
SELECT @cli1, @u, 'Boleta', 'B001', '00000001', DATE_SUB(NOW(), INTERVAL 12 DAY) + INTERVAL 9 HOUR, 0, 'CONTADO', 'EFECTIVO', 0, 'Aceptado', 'DEMO'
WHERE NOT EXISTS (SELECT 1 FROM venta WHERE serie_comprobante='B001' AND num_comprobante='00000001' AND tipo_comprobante='Boleta');
SET @v = (SELECT idventa FROM venta WHERE serie_comprobante='B001' AND num_comprobante='00000001' AND tipo_comprobante='Boleta');
INSERT INTO detalle_venta (idventa, idarticulo, cantidad, precio_venta, descuento) SELECT @v, @a1, 40, 1.50, 0 WHERE NOT EXISTS (SELECT 1 FROM detalle_venta WHERE idventa=@v);
INSERT INTO detalle_venta (idventa, idarticulo, cantidad, precio_venta, descuento) SELECT @v, @a2, 40, 0.60, 0 WHERE (SELECT COUNT(*) FROM detalle_venta WHERE idventa=@v)=1;
UPDATE venta SET total_venta=(SELECT SUM(cantidad*precio_venta-descuento) FROM detalle_venta WHERE idventa=@v) WHERE idventa=@v;

-- Venta 2
INSERT INTO venta (idcliente, idusuario, tipo_comprobante, serie_comprobante, num_comprobante, fecha_hora, impuesto, tipo_pago, medio_pago, total_venta, estado, observacion)
SELECT @cli3, @u, 'Boleta', 'B001', '00000002', DATE_SUB(NOW(), INTERVAL 10 DAY) + INTERVAL 11 HOUR, 0, 'CONTADO', 'YAPE', 0, 'Aceptado', 'DEMO'
WHERE NOT EXISTS (SELECT 1 FROM venta WHERE serie_comprobante='B001' AND num_comprobante='00000002' AND tipo_comprobante='Boleta');
SET @v = (SELECT idventa FROM venta WHERE serie_comprobante='B001' AND num_comprobante='00000002' AND tipo_comprobante='Boleta');
INSERT INTO detalle_venta (idventa, idarticulo, cantidad, precio_venta, descuento) SELECT @v, @a6, 2, 42.00, 0 WHERE NOT EXISTS (SELECT 1 FROM detalle_venta WHERE idventa=@v);
INSERT INTO detalle_venta (idventa, idarticulo, cantidad, precio_venta, descuento) SELECT @v, @a8, 2, 12.00, 2.00 WHERE (SELECT COUNT(*) FROM detalle_venta WHERE idventa=@v)=1;
UPDATE venta SET total_venta=(SELECT SUM(cantidad*precio_venta-descuento) FROM detalle_venta WHERE idventa=@v) WHERE idventa=@v;

-- Venta 3 (factura al credito -> cuenta por cobrar)
INSERT INTO venta (idcliente, idusuario, tipo_comprobante, serie_comprobante, num_comprobante, fecha_hora, fecha_vencimiento, impuesto, tipo_pago, medio_pago, total_venta, estado, observacion)
SELECT @cli2, @u, 'Factura', 'F001', '00000001', DATE_SUB(NOW(), INTERVAL 8 DAY) + INTERVAL 15 HOUR, DATE(DATE_ADD(NOW(), INTERVAL 22 DAY)), 18, 'CREDITO', 'EFECTIVO', 0, 'Aceptado', 'DEMO'
WHERE NOT EXISTS (SELECT 1 FROM venta WHERE serie_comprobante='F001' AND num_comprobante='00000001' AND tipo_comprobante='Factura');
SET @v = (SELECT idventa FROM venta WHERE serie_comprobante='F001' AND num_comprobante='00000001' AND tipo_comprobante='Factura');
INSERT INTO detalle_venta (idventa, idarticulo, cantidad, precio_venta, descuento) SELECT @v, @a9, 150, 3.00, 0 WHERE NOT EXISTS (SELECT 1 FROM detalle_venta WHERE idventa=@v);
INSERT INTO detalle_venta (idventa, idarticulo, cantidad, precio_venta, descuento) SELECT @v, @a10, 15, 7.50, 0 WHERE (SELECT COUNT(*) FROM detalle_venta WHERE idventa=@v)=1;
INSERT INTO detalle_venta (idventa, idarticulo, cantidad, precio_venta, descuento) SELECT @v, @a11, 30, 9.90, 0 WHERE (SELECT COUNT(*) FROM detalle_venta WHERE idventa=@v)=2;
INSERT INTO detalle_venta (idventa, idarticulo, cantidad, precio_venta, descuento) SELECT @v, @a14, 1, 219.00, 19.00 WHERE (SELECT COUNT(*) FROM detalle_venta WHERE idventa=@v)=3;
UPDATE venta SET total_venta=(SELECT SUM(cantidad*precio_venta-descuento) FROM detalle_venta WHERE idventa=@v) WHERE idventa=@v;
INSERT INTO cuenta_cobrar (idcliente, idventa, fecha_emision, fecha_vencimiento, documento_ref, monto_total, saldo, estado, observacion)
SELECT @cli2, @v, DATE(DATE_SUB(NOW(), INTERVAL 8 DAY)), DATE(DATE_ADD(NOW(), INTERVAL 22 DAY)), 'Factura F001-00000001', (SELECT total_venta FROM venta WHERE idventa=@v), (SELECT total_venta FROM venta WHERE idventa=@v) - 200, 'PENDIENTE', 'Generada automáticamente desde venta (demo)'
WHERE NOT EXISTS (SELECT 1 FROM cuenta_cobrar WHERE idventa=@v);
INSERT INTO pago_cuenta_cobrar (idcuenta_cobrar, idusuario, fecha_hora, monto, medio_pago, observacion)
SELECT cc.idcuenta_cobrar, @u, DATE_SUB(NOW(), INTERVAL 3 DAY), 200.00, 'TRANSFERENCIA', 'Adelanto (demo)' FROM cuenta_cobrar cc WHERE cc.idventa=@v AND NOT EXISTS (SELECT 1 FROM pago_cuenta_cobrar p WHERE p.idcuenta_cobrar=cc.idcuenta_cobrar);

-- Venta 4
INSERT INTO venta (idcliente, idusuario, tipo_comprobante, serie_comprobante, num_comprobante, fecha_hora, impuesto, tipo_pago, medio_pago, total_venta, estado, observacion)
SELECT @cli4, @u, 'Ticket', 'T001', '00000001', DATE_SUB(NOW(), INTERVAL 6 DAY) + INTERVAL 17 HOUR, 0, 'CONTADO', 'EFECTIVO', 0, 'Aceptado', 'DEMO'
WHERE NOT EXISTS (SELECT 1 FROM venta WHERE serie_comprobante='T001' AND num_comprobante='00000001' AND tipo_comprobante='Ticket');
SET @v = (SELECT idventa FROM venta WHERE serie_comprobante='T001' AND num_comprobante='00000001' AND tipo_comprobante='Ticket');
INSERT INTO detalle_venta (idventa, idarticulo, cantidad, precio_venta, descuento) SELECT @v, @a3, 5, 7.00, 0 WHERE NOT EXISTS (SELECT 1 FROM detalle_venta WHERE idventa=@v);
INSERT INTO detalle_venta (idventa, idarticulo, cantidad, precio_venta, descuento) SELECT @v, @a12, 1, 29.00, 0 WHERE (SELECT COUNT(*) FROM detalle_venta WHERE idventa=@v)=1;
UPDATE venta SET total_venta=(SELECT SUM(cantidad*precio_venta-descuento) FROM detalle_venta WHERE idventa=@v) WHERE idventa=@v;

-- Venta 5
INSERT INTO venta (idcliente, idusuario, tipo_comprobante, serie_comprobante, num_comprobante, fecha_hora, impuesto, tipo_pago, medio_pago, total_venta, estado, observacion)
SELECT @cli1, @u, 'Boleta', 'B001', '00000003', DATE_SUB(NOW(), INTERVAL 4 DAY) + INTERVAL 10 HOUR, 0, 'CONTADO', 'TARJETA', 0, 'Aceptado', 'DEMO'
WHERE NOT EXISTS (SELECT 1 FROM venta WHERE serie_comprobante='B001' AND num_comprobante='00000003' AND tipo_comprobante='Boleta');
SET @v = (SELECT idventa FROM venta WHERE serie_comprobante='B001' AND num_comprobante='00000003' AND tipo_comprobante='Boleta');
INSERT INTO detalle_venta (idventa, idarticulo, cantidad, precio_venta, descuento) SELECT @v, @a7, 3, 52.00, 6.00 WHERE NOT EXISTS (SELECT 1 FROM detalle_venta WHERE idventa=@v);
INSERT INTO detalle_venta (idventa, idarticulo, cantidad, precio_venta, descuento) SELECT @v, @a5, 4, 9.50, 0 WHERE (SELECT COUNT(*) FROM detalle_venta WHERE idventa=@v)=1;
UPDATE venta SET total_venta=(SELECT SUM(cantidad*precio_venta-descuento) FROM detalle_venta WHERE idventa=@v) WHERE idventa=@v;

-- Venta 6
INSERT INTO venta (idcliente, idusuario, tipo_comprobante, serie_comprobante, num_comprobante, fecha_hora, impuesto, tipo_pago, medio_pago, total_venta, estado, observacion)
SELECT @cli3, @u, 'Boleta', 'B001', '00000004', DATE_SUB(NOW(), INTERVAL 2 DAY) + INTERVAL 12 HOUR, 0, 'CONTADO', 'PLIN', 0, 'Aceptado', 'DEMO'
WHERE NOT EXISTS (SELECT 1 FROM venta WHERE serie_comprobante='B001' AND num_comprobante='00000004' AND tipo_comprobante='Boleta');
SET @v = (SELECT idventa FROM venta WHERE serie_comprobante='B001' AND num_comprobante='00000004' AND tipo_comprobante='Boleta');
INSERT INTO detalle_venta (idventa, idarticulo, cantidad, precio_venta, descuento) SELECT @v, @a11, 6, 9.90, 0 WHERE NOT EXISTS (SELECT 1 FROM detalle_venta WHERE idventa=@v);
INSERT INTO detalle_venta (idventa, idarticulo, cantidad, precio_venta, descuento) SELECT @v, @a4, 100, 0.25, 0 WHERE (SELECT COUNT(*) FROM detalle_venta WHERE idventa=@v)=1;
UPDATE venta SET total_venta=(SELECT SUM(cantidad*precio_venta-descuento) FROM detalle_venta WHERE idventa=@v) WHERE idventa=@v;

-- Venta 7 (hoy)
INSERT INTO venta (idcliente, idusuario, tipo_comprobante, serie_comprobante, num_comprobante, fecha_hora, impuesto, tipo_pago, medio_pago, total_venta, estado, observacion)
SELECT @cli1, @u, 'Boleta', 'B001', '00000005', CURDATE() + INTERVAL 9 HOUR + INTERVAL 30 MINUTE, 0, 'CONTADO', 'EFECTIVO', 0, 'Aceptado', 'DEMO'
WHERE NOT EXISTS (SELECT 1 FROM venta WHERE serie_comprobante='B001' AND num_comprobante='00000005' AND tipo_comprobante='Boleta');
SET @v = (SELECT idventa FROM venta WHERE serie_comprobante='B001' AND num_comprobante='00000005' AND tipo_comprobante='Boleta');
INSERT INTO detalle_venta (idventa, idarticulo, cantidad, precio_venta, descuento) SELECT @v, @a13, 1, 24.00, 0 WHERE NOT EXISTS (SELECT 1 FROM detalle_venta WHERE idventa=@v);
INSERT INTO detalle_venta (idventa, idarticulo, cantidad, precio_venta, descuento) SELECT @v, @a1, 20, 1.50, 0 WHERE (SELECT COUNT(*) FROM detalle_venta WHERE idventa=@v)=1;
UPDATE venta SET total_venta=(SELECT SUM(cantidad*precio_venta-descuento) FROM detalle_venta WHERE idventa=@v) WHERE idventa=@v;

-- Ajuste de inventario de ejemplo (merma de pintura)
INSERT INTO ajuste_inventario (idarticulo, idusuario, tipo, motivo, cantidad, stock_anterior, stock_nuevo, costo_unitario, observacion, fecha_hora)
SELECT @a6, @u, 'SALIDA', 'MERMA', 1, (SELECT stock FROM articulo WHERE idarticulo=@a6), (SELECT stock FROM articulo WHERE idarticulo=@a6) - 1, 28.00, 'Galón dañado (demo)', DATE_SUB(NOW(), INTERVAL 5 DAY)
WHERE NOT EXISTS (SELECT 1 FROM ajuste_inventario WHERE observacion='Galón dañado (demo)');
UPDATE articulo SET stock = stock - 1 WHERE idarticulo=@a6 AND (SELECT COUNT(*) FROM ajuste_inventario WHERE observacion='Galón dañado (demo)')=1 AND NOT EXISTS (SELECT 1 FROM auditoria WHERE detalle='demo_ajuste_aplicado');
INSERT INTO auditoria (idusuario, usuario, modulo, accion, detalle, ip) SELECT @u, 'sistema', 'demo', 'carga', 'demo_ajuste_aplicado', '127.0.0.1' WHERE NOT EXISTS (SELECT 1 FROM auditoria WHERE detalle='demo_ajuste_aplicado');

-- Caja cerrada de ejemplo (ayer)
INSERT INTO caja_diaria (idusuario, fecha_apertura, fecha_cierre, monto_apertura, monto_cierre_sistema, monto_cierre_real, diferencia, estado, observacion)
SELECT @u, DATE_SUB(CURDATE(), INTERVAL 1 DAY) + INTERVAL 8 HOUR, DATE_SUB(CURDATE(), INTERVAL 1 DAY) + INTERVAL 19 HOUR, 100.00, 187.40, 187.00, -0.40, 'CERRADA', 'Caja demo | Cierre: cuadre OK'
WHERE NOT EXISTS (SELECT 1 FROM caja_diaria WHERE observacion LIKE 'Caja demo%');
SET @caja = (SELECT idcaja FROM caja_diaria WHERE observacion LIKE 'Caja demo%' LIMIT 1);
INSERT INTO caja_movimiento (idcaja, idusuario, tipo, concepto, referencia, medio_pago, monto, fecha_hora)
SELECT @caja, @u, 'INGRESO', 'Venta Boleta B001-00000004', 'DEMO', 'PLIN', 84.40, DATE_SUB(CURDATE(), INTERVAL 1 DAY) + INTERVAL 12 HOUR WHERE NOT EXISTS (SELECT 1 FROM caja_movimiento WHERE idcaja=@caja);
INSERT INTO caja_movimiento (idcaja, idusuario, tipo, concepto, referencia, medio_pago, monto, fecha_hora)
SELECT @caja, @u, 'EGRESO', 'Pago de movilidad', 'DEMO', 'EFECTIVO', 12.00, DATE_SUB(CURDATE(), INTERVAL 1 DAY) + INTERVAL 15 HOUR WHERE (SELECT COUNT(*) FROM caja_movimiento WHERE idcaja=@caja)=1;
INSERT INTO caja_movimiento (idcaja, idusuario, tipo, concepto, referencia, medio_pago, monto, fecha_hora)
SELECT @caja, @u, 'INGRESO', 'Venta Ticket T001-00000001', 'DEMO', 'EFECTIVO', 64.00, DATE_SUB(CURDATE(), INTERVAL 1 DAY) + INTERVAL 17 HOUR WHERE (SELECT COUNT(*) FROM caja_movimiento WHERE idcaja=@caja)=2;
INSERT INTO caja_movimiento (idcaja, idusuario, tipo, concepto, referencia, medio_pago, monto, fecha_hora)
SELECT @caja, @u, 'INGRESO', 'Venta Boleta B001-00000003', 'DEMO', 'TARJETA', 51.00, DATE_SUB(CURDATE(), INTERVAL 1 DAY) + INTERVAL 18 HOUR WHERE (SELECT COUNT(*) FROM caja_movimiento WHERE idcaja=@caja)=3;

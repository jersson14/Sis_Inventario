-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1:3307
-- Tiempo de generación: 30-03-2026 a las 00:36:21
-- Versión del servidor: 10.4.32-MariaDB
-- Versión de PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `mi_tienda`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `articulo`
--

CREATE TABLE `articulo` (
  `idarticulo` int(11) NOT NULL,
  `idcategoria` int(11) NOT NULL,
  `idunidad` int(11) NOT NULL,
  `codigo` varchar(50) DEFAULT NULL,
  `nombre` varchar(100) NOT NULL,
  `stock` decimal(14,3) NOT NULL DEFAULT 0.000,
  `stock_minimo` decimal(14,3) NOT NULL DEFAULT 1.000,
  `descripcion` varchar(256) DEFAULT NULL,
  `imagen` varchar(50) DEFAULT NULL,
  `condicion` tinyint(4) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Volcado de datos para la tabla `articulo`
--

INSERT INTO `articulo` (`idarticulo`, `idcategoria`, `idunidad`, `codigo`, `nombre`, `stock`, `stock_minimo`, `descripcion`, `imagen`, `condicion`) VALUES
(6, 7, 1, '00236', 'POLO', 100.000, 5.000, 'POLO TALLA XL', '1638138848.jpg', 1),
(7, 9, 1, '0040kl', 'disco solido', 58.000, 5.000, 'disco marca KINGSTON', '1535417431.jfif', 1),
(8, 9, 1, 'HJL-OP', 'DATATRABEL', 109.000, 5.000, 'usb de 15gb', '1535417452.jpg', 1),
(9, 13, 1, '1235', 'Pantalon JEAN PARADA 111', 12.000, 5.000, 'COMPRA D EPANTALOS JEAN', '1638066940.jpg', 1),
(10, 7, 6, '2112', 'Tubo', 439.000, 5.000, 'tubos de pavco', '', 1),
(11, 13, 1, 'ART-648686', 'Clavos', 213.000, 5.000, 'clavios de pares', '', 1),
(12, 7, 1, 'CLA-620389', 'clavo', 411.000, 5.000, 'sdfsdf', '', 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `backup_log`
--

CREATE TABLE `backup_log` (
  `idbackup` int(11) NOT NULL,
  `idusuario` int(11) NOT NULL,
  `archivo` varchar(180) NOT NULL,
  `tamano_bytes` bigint(20) NOT NULL DEFAULT 0,
  `fecha_hora` datetime NOT NULL DEFAULT current_timestamp(),
  `tipo` varchar(20) NOT NULL DEFAULT 'BACKUP'
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Volcado de datos para la tabla `backup_log`
--

INSERT INTO `backup_log` (`idbackup`, `idusuario`, `archivo`, `tamano_bytes`, `fecha_hora`, `tipo`) VALUES
(1, 3, 'backup_20260321_215648.sql', 39977, '2026-03-21 15:56:48', 'BACKUP');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `caja_diaria`
--

CREATE TABLE `caja_diaria` (
  `idcaja` int(11) NOT NULL,
  `idusuario` int(11) NOT NULL,
  `fecha_apertura` datetime NOT NULL,
  `fecha_cierre` datetime DEFAULT NULL,
  `monto_apertura` decimal(14,2) NOT NULL DEFAULT 0.00,
  `monto_cierre_sistema` decimal(14,2) DEFAULT NULL,
  `monto_cierre_real` decimal(14,2) DEFAULT NULL,
  `diferencia` decimal(14,2) DEFAULT NULL,
  `estado` varchar(20) NOT NULL DEFAULT 'ABIERTA',
  `observacion` varchar(200) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `caja_movimiento`
--

CREATE TABLE `caja_movimiento` (
  `idmovimiento` int(11) NOT NULL,
  `idcaja` int(11) NOT NULL,
  `idusuario` int(11) NOT NULL,
  `tipo` varchar(12) NOT NULL,
  `concepto` varchar(120) NOT NULL,
  `monto` decimal(14,2) NOT NULL,
  `fecha_hora` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `categoria`
--

CREATE TABLE `categoria` (
  `idcategoria` int(11) NOT NULL,
  `nombre` varchar(50) NOT NULL,
  `descripcion` varchar(256) DEFAULT NULL,
  `condicion` tinyint(4) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Volcado de datos para la tabla `categoria`
--

INSERT INTO `categoria` (`idcategoria`, `nombre`, `descripcion`, `condicion`) VALUES
(7, 'POLOS', 'POLOS MARGA CORTA', 1),
(8, 'CPU\'s', 'cpus gamers de alta categoria', 1),
(9, 'DISCOS DUROS', 'disco solidos', 1),
(12, 'MONITORES', 'monitores gamers', 1),
(13, 'PANTALONES', 'Pantalos JEAN', 1),
(14, 'herramienta', '', 1),
(15, 'sdfdsf', '', 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `configuracion_empresa`
--

CREATE TABLE `configuracion_empresa` (
  `idconfig` int(11) NOT NULL,
  `nombre_comercial` varchar(120) NOT NULL,
  `razon_social` varchar(150) DEFAULT NULL,
  `ruc` varchar(20) DEFAULT NULL,
  `direccion` varchar(180) DEFAULT NULL,
  `telefono` varchar(30) DEFAULT NULL,
  `celular` varchar(30) DEFAULT NULL,
  `correo` varchar(120) DEFAULT NULL,
  `web` varchar(120) DEFAULT NULL,
  `logo` varchar(100) DEFAULT NULL,
  `color_primario` varchar(10) NOT NULL DEFAULT '#0f766e',
  `color_secundario` varchar(10) NOT NULL DEFAULT '#f59e0b',
  `serie_boleta` varchar(10) NOT NULL DEFAULT 'B001',
  `serie_factura` varchar(10) NOT NULL DEFAULT 'F001',
  `serie_ticket` varchar(10) NOT NULL DEFAULT 'T001',
  `impuesto_default` decimal(5,2) NOT NULL DEFAULT 18.00,
  `moneda` varchar(10) NOT NULL DEFAULT 'PEN',
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Volcado de datos para la tabla `configuracion_empresa`
--

INSERT INTO `configuracion_empresa` (`idconfig`, `nombre_comercial`, `razon_social`, `ruc`, `direccion`, `telefono`, `celular`, `correo`, `web`, `logo`, `color_primario`, `color_secundario`, `serie_boleta`, `serie_factura`, `serie_ticket`, `impuesto_default`, `moneda`, `updated_at`) VALUES
(1, 'PERNO CENTRO', 'PERNO CENTRO &quot;SEÑOR DE HUANCA&quot;', '20603558422', 'Bar. Santa Rosa S/N (al costado del Grifo Wari), Abancay - Apurimac', '932381391', '932381391', 'ventas@pernocentro.com', '', 'logo1.jpeg', '#0b7f75', '#f59e0b', 'B001', 'F001', 'T001', 18.00, 'PEN', '2026-03-21 16:31:15');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `cuenta_cobrar`
--

CREATE TABLE `cuenta_cobrar` (
  `idcuenta_cobrar` int(11) NOT NULL,
  `idcliente` int(11) NOT NULL,
  `idventa` int(11) DEFAULT NULL,
  `fecha_emision` date NOT NULL,
  `fecha_vencimiento` date NOT NULL,
  `documento_ref` varchar(40) DEFAULT NULL,
  `monto_total` decimal(14,2) NOT NULL,
  `saldo` decimal(14,2) NOT NULL,
  `estado` varchar(20) NOT NULL DEFAULT 'PENDIENTE',
  `observacion` varchar(200) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `cuenta_pagar`
--

CREATE TABLE `cuenta_pagar` (
  `idcuenta_pagar` int(11) NOT NULL,
  `idproveedor` int(11) NOT NULL,
  `idingreso` int(11) DEFAULT NULL,
  `fecha_emision` date NOT NULL,
  `fecha_vencimiento` date NOT NULL,
  `documento_ref` varchar(40) DEFAULT NULL,
  `monto_total` decimal(14,2) NOT NULL,
  `saldo` decimal(14,2) NOT NULL,
  `estado` varchar(20) NOT NULL DEFAULT 'PENDIENTE',
  `observacion` varchar(200) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `detalle_ingreso`
--

CREATE TABLE `detalle_ingreso` (
  `iddetalle_ingreso` int(11) NOT NULL,
  `idingreso` int(11) NOT NULL,
  `idarticulo` int(11) NOT NULL,
  `cantidad` decimal(14,3) NOT NULL,
  `precio_compra` decimal(11,2) NOT NULL,
  `precio_venta` decimal(11,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Volcado de datos para la tabla `detalle_ingreso`
--

INSERT INTO `detalle_ingreso` (`iddetalle_ingreso`, `idingreso`, `idarticulo`, `cantidad`, `precio_compra`, `precio_venta`) VALUES
(16, 6, 6, 10.000, 20.00, 30.00),
(17, 6, 7, 5.000, 200.00, 250.00),
(18, 7, 8, 10.000, 16.00, 25.00),
(19, 8, 7, 10.000, 250.00, 300.00),
(20, 9, 8, 50.000, 20.00, 30.00),
(21, 10, 6, 10.000, 25.00, 30.00),
(22, 11, 7, 15.000, 250.00, 300.00),
(23, 12, 9, 7.000, 1.00, 1.00),
(24, 13, 12, 222.000, 1.00, 1.00),
(25, 13, 8, 3.000, 20.00, 1.00),
(26, 14, 10, 15.000, 35.00, 1.00),
(27, 15, 6, 100.000, 25.00, 1.00),
(28, 16, 10, 444.000, 35.00, 1.00);

--
-- Disparadores `detalle_ingreso`
--
DELIMITER $$
CREATE TRIGGER `tr_updStockIngreso` AFTER INSERT ON `detalle_ingreso` FOR EACH ROW BEGIN
UPDATE articulo SET stock=stock + NEW.cantidad
WHERE articulo.idarticulo = NEW.idarticulo;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `detalle_venta`
--

CREATE TABLE `detalle_venta` (
  `iddetalle_venta` int(11) NOT NULL,
  `idventa` int(11) NOT NULL,
  `idarticulo` int(11) NOT NULL,
  `cantidad` decimal(14,3) NOT NULL,
  `precio_venta` decimal(11,2) NOT NULL,
  `descuento` decimal(11,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Volcado de datos para la tabla `detalle_venta`
--

INSERT INTO `detalle_venta` (`iddetalle_venta`, `idventa`, `idarticulo`, `cantidad`, `precio_venta`, `descuento`) VALUES
(12, 10, 6, 10.000, 30.00, 5.00),
(13, 10, 7, 10.000, 250.00, 10.00),
(14, 11, 6, 1.000, 30.00, 0.00),
(15, 11, 7, 1.000, 250.00, 0.00),
(16, 12, 7, 4.000, 250.00, 0.00),
(17, 13, 7, 1.000, 250.00, 10.00),
(18, 14, 7, 2.000, 250.00, 10.00),
(19, 15, 6, 1.000, 30.00, 10.00),
(20, 16, 7, 1.000, 250.00, 5.00),
(21, 17, 7, 1.000, 250.00, 5.00),
(22, 18, 6, 1.000, 30.00, 0.00),
(23, 19, 7, 1.000, 250.00, 2.00),
(24, 20, 8, 2.000, 25.00, 0.00),
(25, 21, 6, 1.000, 30.00, 5.00),
(26, 22, 6, 1.000, 30.00, 0.00),
(27, 22, 7, 1.000, 300.00, 0.00),
(28, 22, 8, 1.000, 30.00, 0.00),
(29, 23, 9, 3.000, 150.00, 0.00),
(30, 24, 9, 4.000, 120.00, 0.00),
(31, 25, 6, 2.000, 30.00, 0.00),
(32, 26, 6, 2.000, 30.00, 0.00),
(33, 27, 12, 44.000, 2.00, 0.00),
(34, 27, 8, 1.000, 30.00, 0.00),
(35, 28, 10, 30.000, 33.00, 0.00),
(36, 29, 10, 10.000, 4.00, 0.00),
(37, 30, 6, 18.000, 30.00, 0.00),
(38, 31, 6, 3.000, 30.00, 0.00);

--
-- Disparadores `detalle_venta`
--
DELIMITER $$
CREATE TRIGGER `tr_udpStockVenta` AFTER INSERT ON `detalle_venta` FOR EACH ROW BEGIN
UPDATE articulo SET stock = stock - NEW.cantidad
WHERE articulo.idarticulo = NEW.idarticulo;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `ingreso`
--

CREATE TABLE `ingreso` (
  `idingreso` int(11) NOT NULL,
  `idproveedor` int(11) NOT NULL,
  `idusuario` int(11) DEFAULT NULL,
  `tipo_comprobante` varchar(20) NOT NULL,
  `serie_comprobante` varchar(7) DEFAULT NULL,
  `num_comprobante` varchar(10) NOT NULL,
  `fecha_hora` datetime NOT NULL,
  `fecha_vencimiento` date DEFAULT NULL,
  `impuesto` decimal(4,2) NOT NULL,
  `tipo_pago` varchar(20) NOT NULL DEFAULT 'CONTADO',
  `total_compra` decimal(11,2) NOT NULL,
  `estado` varchar(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Volcado de datos para la tabla `ingreso`
--

INSERT INTO `ingreso` (`idingreso`, `idproveedor`, `idusuario`, `tipo_comprobante`, `serie_comprobante`, `num_comprobante`, `fecha_hora`, `fecha_vencimiento`, `impuesto`, `tipo_pago`, `total_compra`, `estado`) VALUES
(6, 7, 1, 'Factura', '001', '0001', '2018-08-20 00:00:00', NULL, 18.00, 'CONTADO', 1200.00, 'Aceptado'),
(7, 7, 1, 'Factura', '001', '008', '2018-08-21 00:00:00', NULL, 18.00, 'CONTADO', 160.00, 'Aceptado'),
(8, 7, 1, 'Boleta', '0002', '0004', '2018-08-22 00:00:00', NULL, 0.00, 'CONTADO', 2500.00, 'Aceptado'),
(9, 9, 1, 'Factura', '001', '0005', '2018-08-23 00:00:00', NULL, 18.00, 'CONTADO', 1000.00, 'Aceptado'),
(10, 10, 1, 'Factura', '001', '0006', '2018-08-25 00:00:00', NULL, 18.00, 'CONTADO', 250.00, 'Aceptado'),
(11, 10, 1, 'Factura', '001', '0007', '2018-08-27 00:00:00', NULL, 18.00, 'CONTADO', 3750.00, 'Aceptado'),
(12, 9, 1, 'Boleta', '00', '292', '2021-11-27 00:00:00', NULL, 0.18, 'CONTADO', 1.00, 'Aceptado'),
(13, 9, 4, 'Boleta', 'S233', '2222', '2026-03-21 00:00:00', NULL, 0.00, 'CONTADO', 282.00, 'Aceptado'),
(14, 9, 3, 'Boleta', 'B001', '1212', '2026-03-29 00:00:00', NULL, 0.00, 'CONTADO', 525.00, 'Aceptado'),
(15, 9, 3, 'Boleta', 'B001', '00001213', '2026-03-29 00:00:00', NULL, 0.00, 'CONTADO', 2500.00, 'Aceptado'),
(16, 13, 3, 'Boleta', 'B001', '00001214', '2026-03-29 00:00:00', NULL, 0.00, 'CONTADO', 15540.00, 'Anulado');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `pago_cuenta_cobrar`
--

CREATE TABLE `pago_cuenta_cobrar` (
  `idpago_cobrar` int(11) NOT NULL,
  `idcuenta_cobrar` int(11) NOT NULL,
  `idusuario` int(11) NOT NULL,
  `fecha_hora` datetime NOT NULL DEFAULT current_timestamp(),
  `monto` decimal(14,2) NOT NULL,
  `medio_pago` varchar(30) DEFAULT NULL,
  `observacion` varchar(150) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `pago_cuenta_pagar`
--

CREATE TABLE `pago_cuenta_pagar` (
  `idpago_pagar` int(11) NOT NULL,
  `idcuenta_pagar` int(11) NOT NULL,
  `idusuario` int(11) NOT NULL,
  `fecha_hora` datetime NOT NULL DEFAULT current_timestamp(),
  `monto` decimal(14,2) NOT NULL,
  `medio_pago` varchar(30) DEFAULT NULL,
  `observacion` varchar(150) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `permiso`
--

CREATE TABLE `permiso` (
  `idpermiso` int(11) NOT NULL,
  `nombre` varchar(30) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Volcado de datos para la tabla `permiso`
--

INSERT INTO `permiso` (`idpermiso`, `nombre`) VALUES
(1, 'Escritorio'),
(2, 'Almacen'),
(3, 'Compras'),
(4, 'Ventas'),
(5, 'Acceso'),
(6, 'Consulta Compras'),
(7, 'Consulta Ventas'),
(8, 'Gestion Pro'),
(9, 'Empresa'),
(10, 'Centro Inteligente'),
(11, 'Cuentas CxC CxP'),
(12, 'Backup'),
(13, 'Centro Reportes');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `persona`
--

CREATE TABLE `persona` (
  `idpersona` int(11) NOT NULL,
  `tipo_persona` varchar(20) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `tipo_documento` varchar(20) DEFAULT NULL,
  `num_documento` varchar(20) DEFAULT NULL,
  `direccion` varchar(70) DEFAULT NULL,
  `telefono` varchar(20) DEFAULT NULL,
  `email` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Volcado de datos para la tabla `persona`
--

INSERT INTO `persona` (`idpersona`, `tipo_persona`, `nombre`, `tipo_documento`, `num_documento`, `direccion`, `telefono`, `email`) VALUES
(7, 'Proveedor', 'INKA-PC S.R.L', 'RUC', '12587845254', 'Av. los pinos 201', '54328730', 'inkapc@hotmail.com'),
(8, 'Cliente', 'publico general', 'DNI', '30224520', 'Av.jose olaya 215', '54325230', 'public@hotmail.com'),
(9, 'Proveedor', 'TECNO-PC', 'RUC', '20485248751', 'Calle los naranjales 245', '054587852', 'tecno@gmail.com'),
(10, 'Proveedor', 'INFONET', 'RUC', '40485245824', 'Av. quiñones 102', '054789854', 'infonet@hotmail.com'),
(11, 'Cliente', 'pedro', 'DNI', '458521748', 'Simon bolivar 120', '78954263', 'pedro@gmailcom'),
(12, 'Cliente', 'Jose Luis', 'DNI', '58256554', 'Av. Abancay N° 333', '985236548', ''),
(13, 'Proveedor', 'VISTONY', 'RUC', '21050511515', 'JR. CUSCO - LIMA PERU', '9115151515', 'vistony12@gmail.com');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `unidad_medida`
--

CREATE TABLE `unidad_medida` (
  `idunidad` int(11) NOT NULL,
  `nombre` varchar(60) NOT NULL,
  `abreviatura` varchar(10) NOT NULL,
  `descripcion` varchar(120) DEFAULT NULL,
  `condicion` tinyint(4) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Volcado de datos para la tabla `unidad_medida`
--

INSERT INTO `unidad_medida` (`idunidad`, `nombre`, `abreviatura`, `descripcion`, `condicion`) VALUES
(1, 'Unidad', 'und', 'Unidad individual', 1),
(2, 'Kilogramo', 'kg', 'Peso en kilogramo', 1),
(3, 'Gramo', 'g', 'Peso en gramo', 1),
(4, 'Litro', 'lt', 'Volumen en litro', 1),
(5, 'Mililitro', 'ml', 'Volumen en mililitro', 1),
(6, 'Metro', 'm', 'Longitud en metro', 1),
(7, 'Centimetro', 'cm', 'Longitud en centimetro', 1),
(8, 'Caja', 'caja', 'Presentacion en caja', 1),
(9, 'Paquete', 'paq', 'Presentacion en paquete', 1),
(20, 'Galon', 'gal', '23', 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuario`
--

CREATE TABLE `usuario` (
  `idusuario` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `tipo_documento` varchar(20) NOT NULL,
  `num_documento` varchar(20) NOT NULL,
  `direccion` varchar(70) DEFAULT NULL,
  `telefono` varchar(20) DEFAULT NULL,
  `email` varchar(50) DEFAULT NULL,
  `cargo` varchar(20) DEFAULT NULL,
  `login` varchar(20) NOT NULL,
  `clave` varchar(64) NOT NULL,
  `imagen` varchar(50) NOT NULL,
  `condicion` tinyint(4) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Volcado de datos para la tabla `usuario`
--

INSERT INTO `usuario` (`idusuario`, `nombre`, `tipo_documento`, `num_documento`, `direccion`, `telefono`, `email`, `cargo`, `login`, `clave`, `imagen`, `condicion`) VALUES
(1, 'JOSE ANTONIO CHAHUA TAIPE', 'DNI', '72154871', 'JR. JULIO C TELLO 230', '944952429', 'antonio2021@gmail.com', 'Vendedor', 'antonio2021', '5994471abb01112afcc18159f6cc74b4f511b99806da59b3caf5a9c173cacfc5', '1535417472.jpg', 0),
(2, 'PATRICIA LIMA BENDEZU', 'DNI', '30115425', 'AV. CIRCUNVALACION S/N', '956135290', 'patricia2021@hotmail.com', 'Empleado de Compras', 'patricia2021', '5994471abb01112afcc18159f6cc74b4f511b99806da59b3caf5a9c173cacfc5', '1535417486.jpg', 0),
(3, 'Jersson Corilla Miranda', 'DNI', '72646121', 'Jr. Nicolas de Pierola', '952956235', 'jerry12@gmail.com', 'ADMINISTRADOR', 'jersson2026', '5dd6da038e891a44e9ee7893a1a108d2353244f46684976877c0ba59915f313f', '1774123566.png', 1),
(4, 'WILFREDO CARRIÓN UMERES', 'DNI', '31044054', 'Jr 28 de Abril', '932381391', 'wcuu@hotmail.com', 'ADMINISTRADOR', 'clavito2026', '5994471abb01112afcc18159f6cc74b4f511b99806da59b3caf5a9c173cacfc5', '1638142070.jpeg', 1),
(5, 'YAMIL SOLIS DIAZ', 'DNI', '65425825', 'JR. CHALHUANCA 515', '944413908', 'yamil2021@gmail.com', 'Almacenero', 'yamil2021', '5994471abb01112afcc18159f6cc74b4f511b99806da59b3caf5a9c173cacfc5', '', 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuario_permiso`
--

CREATE TABLE `usuario_permiso` (
  `idusuario_permiso` int(11) NOT NULL,
  `idusuario` int(11) NOT NULL,
  `idpermiso` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Volcado de datos para la tabla `usuario_permiso`
--

INSERT INTO `usuario_permiso` (`idusuario_permiso`, `idusuario`, `idpermiso`) VALUES
(116, 2, 1),
(117, 2, 3),
(118, 2, 6),
(119, 1, 1),
(120, 1, 4),
(121, 1, 7),
(122, 5, 1),
(123, 5, 2),
(193, 3, 1),
(194, 3, 2),
(195, 3, 3),
(196, 3, 4),
(197, 3, 5),
(198, 3, 6),
(199, 3, 7),
(200, 3, 8),
(201, 3, 9),
(202, 3, 10),
(203, 3, 11),
(204, 3, 12),
(205, 3, 13),
(206, 4, 1),
(207, 4, 2),
(208, 4, 3),
(209, 4, 4),
(210, 4, 5),
(211, 4, 6),
(212, 4, 7),
(213, 4, 8),
(214, 4, 9),
(215, 4, 10),
(216, 4, 11),
(217, 4, 12),
(218, 4, 13);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `venta`
--

CREATE TABLE `venta` (
  `idventa` int(11) NOT NULL,
  `idcliente` int(11) NOT NULL,
  `idusuario` int(11) NOT NULL,
  `tipo_comprobante` varchar(20) NOT NULL,
  `serie_comprobante` varchar(7) DEFAULT NULL,
  `num_comprobante` varchar(10) NOT NULL,
  `fecha_hora` datetime NOT NULL,
  `fecha_vencimiento` date DEFAULT NULL,
  `impuesto` decimal(4,2) DEFAULT NULL,
  `tipo_pago` varchar(20) NOT NULL DEFAULT 'CONTADO',
  `total_venta` decimal(11,2) DEFAULT NULL,
  `estado` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Volcado de datos para la tabla `venta`
--

INSERT INTO `venta` (`idventa`, `idcliente`, `idusuario`, `tipo_comprobante`, `serie_comprobante`, `num_comprobante`, `fecha_hora`, `fecha_vencimiento`, `impuesto`, `tipo_pago`, `total_venta`, `estado`) VALUES
(10, 8, 1, 'Boleta', '001', '0001', '2018-01-08 00:00:00', NULL, 0.00, 'CONTADO', 11800.15, 'Aceptado'),
(11, 8, 1, 'Factura', '001', '0002', '2018-03-05 00:00:00', NULL, 18.00, 'CONTADO', 3800.00, 'Aceptado'),
(12, 8, 1, 'Ticket', '001', '0001', '2018-04-17 00:00:00', NULL, 0.00, 'CONTADO', 1000.00, 'Aceptado'),
(13, 8, 1, 'Factura', '001', '0002', '2018-06-09 00:00:00', NULL, 18.00, 'CONTADO', 240.00, 'Aceptado'),
(14, 8, 1, 'Factura', '20', '30', '2018-07-24 00:00:00', NULL, 18.00, 'CONTADO', 490.00, 'Aceptado'),
(15, 8, 1, 'Factura', '001', '0008', '2018-08-26 00:00:00', NULL, 18.00, 'CONTADO', 20.00, 'Aceptado'),
(16, 8, 1, 'Boleta', '001', '0070', '2018-08-26 00:00:00', NULL, 0.00, 'CONTADO', 245.00, 'Aceptado'),
(17, 8, 1, 'Factura', '002', '0004', '2018-08-26 00:00:00', NULL, 18.00, 'CONTADO', 245.00, 'Aceptado'),
(18, 8, 1, 'Boleta', '001', '0006', '2018-08-26 00:00:00', NULL, 0.00, 'CONTADO', 30.00, 'Aceptado'),
(19, 8, 1, 'Factura', '001', '0009', '2018-08-26 00:00:00', NULL, 18.00, 'CONTADO', 248.00, 'Aceptado'),
(20, 8, 1, 'Factura', '001', '002', '2018-08-26 00:00:00', NULL, 18.00, 'CONTADO', 50.00, 'Aceptado'),
(21, 8, 1, 'Factura', '001', '0004', '2018-08-27 00:00:00', NULL, 18.00, 'CONTADO', 25.00, 'Aceptado'),
(22, 11, 1, 'Ticket', '001', '0004', '2018-08-27 00:00:00', NULL, 0.00, 'CONTADO', 360.00, 'Aceptado'),
(23, 12, 1, 'Boleta', '0011', '266', '2021-11-27 00:00:00', NULL, 0.18, 'CONTADO', 450.00, 'Aceptado'),
(24, 12, 3, 'Factura', '0219', '226', '2021-11-28 00:00:00', NULL, 18.00, 'CONTADO', 1.00, 'Aceptado'),
(25, 11, 3, 'Boleta', '0001', '28', '2021-11-28 00:00:00', NULL, 0.18, 'CONTADO', 60.00, 'Aceptado'),
(26, 11, 3, 'Ticket', '0002', '112', '2021-11-28 00:00:00', NULL, 0.18, 'CONTADO', 60.00, 'Aceptado'),
(27, 11, 4, 'Boleta', 'fpp2', '155115', '2026-03-21 00:00:00', NULL, 0.00, 'CONTADO', 118.00, 'Aceptado'),
(28, 11, 3, 'Boleta', 'B001', '23434', '2026-03-29 00:00:00', NULL, 0.00, 'CONTADO', 990.00, 'Aceptado'),
(29, 12, 3, 'Boleta', 'B001', '4334', '2026-03-29 00:00:00', NULL, 0.00, 'CONTADO', 40.00, 'Aceptado'),
(30, 12, 3, 'Boleta', 'B001', '433434', '2026-03-29 00:00:00', NULL, 0.00, 'CONTADO', 540.00, 'Aceptado'),
(31, 11, 3, 'Boleta', 'B001', '00433435', '2026-03-29 00:00:00', NULL, 0.00, 'CONTADO', 90.00, 'Aceptado');

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `articulo`
--
ALTER TABLE `articulo`
  ADD PRIMARY KEY (`idarticulo`),
  ADD UNIQUE KEY `nombre_UNIQUE` (`nombre`),
  ADD KEY `fk_articulo_categoria_idx` (`idcategoria`),
  ADD KEY `fk_articulo_unidad_idx` (`idunidad`);

--
-- Indices de la tabla `backup_log`
--
ALTER TABLE `backup_log`
  ADD PRIMARY KEY (`idbackup`),
  ADD KEY `fk_backup_usuario_idx` (`idusuario`);

--
-- Indices de la tabla `caja_diaria`
--
ALTER TABLE `caja_diaria`
  ADD PRIMARY KEY (`idcaja`),
  ADD KEY `fk_caja_usuario_idx` (`idusuario`);

--
-- Indices de la tabla `caja_movimiento`
--
ALTER TABLE `caja_movimiento`
  ADD PRIMARY KEY (`idmovimiento`),
  ADD KEY `fk_cajamov_caja_idx` (`idcaja`),
  ADD KEY `fk_cajamov_usuario_idx` (`idusuario`);

--
-- Indices de la tabla `categoria`
--
ALTER TABLE `categoria`
  ADD PRIMARY KEY (`idcategoria`),
  ADD UNIQUE KEY `nombre_UNIQUE` (`nombre`);

--
-- Indices de la tabla `configuracion_empresa`
--
ALTER TABLE `configuracion_empresa`
  ADD PRIMARY KEY (`idconfig`);

--
-- Indices de la tabla `cuenta_cobrar`
--
ALTER TABLE `cuenta_cobrar`
  ADD PRIMARY KEY (`idcuenta_cobrar`),
  ADD KEY `fk_cobrar_cliente_idx` (`idcliente`),
  ADD KEY `fk_cobrar_venta_idx` (`idventa`);

--
-- Indices de la tabla `cuenta_pagar`
--
ALTER TABLE `cuenta_pagar`
  ADD PRIMARY KEY (`idcuenta_pagar`),
  ADD KEY `fk_pagar_proveedor_idx` (`idproveedor`),
  ADD KEY `fk_pagar_ingreso_idx` (`idingreso`);

--
-- Indices de la tabla `detalle_ingreso`
--
ALTER TABLE `detalle_ingreso`
  ADD PRIMARY KEY (`iddetalle_ingreso`),
  ADD KEY `fk_detalle_ingreso_idx` (`idingreso`),
  ADD KEY `fk_detalle_articulo_idx` (`idarticulo`);

--
-- Indices de la tabla `detalle_venta`
--
ALTER TABLE `detalle_venta`
  ADD PRIMARY KEY (`iddetalle_venta`),
  ADD KEY `fk_detalle_venta_venta_idx` (`idventa`),
  ADD KEY `fk_detalle_venta_articulo_idx` (`idarticulo`);

--
-- Indices de la tabla `ingreso`
--
ALTER TABLE `ingreso`
  ADD PRIMARY KEY (`idingreso`),
  ADD KEY `fk_ingreso_persona_idx` (`idproveedor`),
  ADD KEY `fk_ingreso_usuario_idx` (`idusuario`);

--
-- Indices de la tabla `pago_cuenta_cobrar`
--
ALTER TABLE `pago_cuenta_cobrar`
  ADD PRIMARY KEY (`idpago_cobrar`),
  ADD KEY `fk_pagocobrar_cuenta_idx` (`idcuenta_cobrar`),
  ADD KEY `fk_pagocobrar_usuario_idx` (`idusuario`);

--
-- Indices de la tabla `pago_cuenta_pagar`
--
ALTER TABLE `pago_cuenta_pagar`
  ADD PRIMARY KEY (`idpago_pagar`),
  ADD KEY `fk_pagopagar_cuenta_idx` (`idcuenta_pagar`),
  ADD KEY `fk_pagopagar_usuario_idx` (`idusuario`);

--
-- Indices de la tabla `permiso`
--
ALTER TABLE `permiso`
  ADD PRIMARY KEY (`idpermiso`);

--
-- Indices de la tabla `persona`
--
ALTER TABLE `persona`
  ADD PRIMARY KEY (`idpersona`);

--
-- Indices de la tabla `unidad_medida`
--
ALTER TABLE `unidad_medida`
  ADD PRIMARY KEY (`idunidad`),
  ADD UNIQUE KEY `nombre_UNIQUE` (`nombre`),
  ADD UNIQUE KEY `abreviatura_UNIQUE` (`abreviatura`);

--
-- Indices de la tabla `usuario`
--
ALTER TABLE `usuario`
  ADD PRIMARY KEY (`idusuario`),
  ADD UNIQUE KEY `login_UNIQUE` (`login`);

--
-- Indices de la tabla `usuario_permiso`
--
ALTER TABLE `usuario_permiso`
  ADD PRIMARY KEY (`idusuario_permiso`),
  ADD KEY `fk_u_permiso_usuario_idx` (`idusuario`),
  ADD KEY `fk_usuario_permiso_idx` (`idpermiso`);

--
-- Indices de la tabla `venta`
--
ALTER TABLE `venta`
  ADD PRIMARY KEY (`idventa`),
  ADD KEY `fk_venta_persona_idx` (`idcliente`),
  ADD KEY `fk_venta_usuario_idx` (`idusuario`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `articulo`
--
ALTER TABLE `articulo`
  MODIFY `idarticulo` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT de la tabla `backup_log`
--
ALTER TABLE `backup_log`
  MODIFY `idbackup` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `caja_diaria`
--
ALTER TABLE `caja_diaria`
  MODIFY `idcaja` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `caja_movimiento`
--
ALTER TABLE `caja_movimiento`
  MODIFY `idmovimiento` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `categoria`
--
ALTER TABLE `categoria`
  MODIFY `idcategoria` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT de la tabla `configuracion_empresa`
--
ALTER TABLE `configuracion_empresa`
  MODIFY `idconfig` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `cuenta_cobrar`
--
ALTER TABLE `cuenta_cobrar`
  MODIFY `idcuenta_cobrar` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `cuenta_pagar`
--
ALTER TABLE `cuenta_pagar`
  MODIFY `idcuenta_pagar` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `detalle_ingreso`
--
ALTER TABLE `detalle_ingreso`
  MODIFY `iddetalle_ingreso` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- AUTO_INCREMENT de la tabla `detalle_venta`
--
ALTER TABLE `detalle_venta`
  MODIFY `iddetalle_venta` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=39;

--
-- AUTO_INCREMENT de la tabla `ingreso`
--
ALTER TABLE `ingreso`
  MODIFY `idingreso` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT de la tabla `pago_cuenta_cobrar`
--
ALTER TABLE `pago_cuenta_cobrar`
  MODIFY `idpago_cobrar` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `pago_cuenta_pagar`
--
ALTER TABLE `pago_cuenta_pagar`
  MODIFY `idpago_pagar` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `permiso`
--
ALTER TABLE `permiso`
  MODIFY `idpermiso` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT de la tabla `persona`
--
ALTER TABLE `persona`
  MODIFY `idpersona` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT de la tabla `unidad_medida`
--
ALTER TABLE `unidad_medida`
  MODIFY `idunidad` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT de la tabla `usuario`
--
ALTER TABLE `usuario`
  MODIFY `idusuario` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de la tabla `usuario_permiso`
--
ALTER TABLE `usuario_permiso`
  MODIFY `idusuario_permiso` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=219;

--
-- AUTO_INCREMENT de la tabla `venta`
--
ALTER TABLE `venta`
  MODIFY `idventa` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=32;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `articulo`
--
ALTER TABLE `articulo`
  ADD CONSTRAINT `fk_articulo_categoria` FOREIGN KEY (`idcategoria`) REFERENCES `categoria` (`idcategoria`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  ADD CONSTRAINT `fk_articulo_unidad` FOREIGN KEY (`idunidad`) REFERENCES `unidad_medida` (`idunidad`) ON DELETE NO ACTION ON UPDATE NO ACTION;

--
-- Filtros para la tabla `backup_log`
--
ALTER TABLE `backup_log`
  ADD CONSTRAINT `fk_backup_usuario` FOREIGN KEY (`idusuario`) REFERENCES `usuario` (`idusuario`) ON DELETE NO ACTION ON UPDATE NO ACTION;

--
-- Filtros para la tabla `caja_diaria`
--
ALTER TABLE `caja_diaria`
  ADD CONSTRAINT `fk_caja_usuario` FOREIGN KEY (`idusuario`) REFERENCES `usuario` (`idusuario`) ON DELETE NO ACTION ON UPDATE NO ACTION;

--
-- Filtros para la tabla `caja_movimiento`
--
ALTER TABLE `caja_movimiento`
  ADD CONSTRAINT `fk_cajamov_caja` FOREIGN KEY (`idcaja`) REFERENCES `caja_diaria` (`idcaja`) ON DELETE CASCADE ON UPDATE NO ACTION,
  ADD CONSTRAINT `fk_cajamov_usuario` FOREIGN KEY (`idusuario`) REFERENCES `usuario` (`idusuario`) ON DELETE NO ACTION ON UPDATE NO ACTION;

--
-- Filtros para la tabla `cuenta_cobrar`
--
ALTER TABLE `cuenta_cobrar`
  ADD CONSTRAINT `fk_cobrar_cliente` FOREIGN KEY (`idcliente`) REFERENCES `persona` (`idpersona`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  ADD CONSTRAINT `fk_cobrar_venta` FOREIGN KEY (`idventa`) REFERENCES `venta` (`idventa`) ON DELETE SET NULL ON UPDATE NO ACTION;

--
-- Filtros para la tabla `cuenta_pagar`
--
ALTER TABLE `cuenta_pagar`
  ADD CONSTRAINT `fk_pagar_ingreso` FOREIGN KEY (`idingreso`) REFERENCES `ingreso` (`idingreso`) ON DELETE SET NULL ON UPDATE NO ACTION,
  ADD CONSTRAINT `fk_pagar_proveedor` FOREIGN KEY (`idproveedor`) REFERENCES `persona` (`idpersona`) ON DELETE NO ACTION ON UPDATE NO ACTION;

--
-- Filtros para la tabla `detalle_ingreso`
--
ALTER TABLE `detalle_ingreso`
  ADD CONSTRAINT `fk_detalle_articulo` FOREIGN KEY (`idarticulo`) REFERENCES `articulo` (`idarticulo`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  ADD CONSTRAINT `fk_detalle_ingreso` FOREIGN KEY (`idingreso`) REFERENCES `ingreso` (`idingreso`) ON DELETE NO ACTION ON UPDATE NO ACTION;

--
-- Filtros para la tabla `detalle_venta`
--
ALTER TABLE `detalle_venta`
  ADD CONSTRAINT `fk_detalle_venta_articulo` FOREIGN KEY (`idarticulo`) REFERENCES `articulo` (`idarticulo`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  ADD CONSTRAINT `fk_detalle_venta_venta` FOREIGN KEY (`idventa`) REFERENCES `venta` (`idventa`) ON DELETE NO ACTION ON UPDATE NO ACTION;

--
-- Filtros para la tabla `ingreso`
--
ALTER TABLE `ingreso`
  ADD CONSTRAINT `fk_ingreso_persona` FOREIGN KEY (`idproveedor`) REFERENCES `persona` (`idpersona`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  ADD CONSTRAINT `fk_ingreso_usuario` FOREIGN KEY (`idusuario`) REFERENCES `usuario` (`idusuario`) ON DELETE NO ACTION ON UPDATE NO ACTION;

--
-- Filtros para la tabla `pago_cuenta_cobrar`
--
ALTER TABLE `pago_cuenta_cobrar`
  ADD CONSTRAINT `fk_pagocobrar_cuenta` FOREIGN KEY (`idcuenta_cobrar`) REFERENCES `cuenta_cobrar` (`idcuenta_cobrar`) ON DELETE CASCADE ON UPDATE NO ACTION,
  ADD CONSTRAINT `fk_pagocobrar_usuario` FOREIGN KEY (`idusuario`) REFERENCES `usuario` (`idusuario`) ON DELETE NO ACTION ON UPDATE NO ACTION;

--
-- Filtros para la tabla `pago_cuenta_pagar`
--
ALTER TABLE `pago_cuenta_pagar`
  ADD CONSTRAINT `fk_pagopagar_cuenta` FOREIGN KEY (`idcuenta_pagar`) REFERENCES `cuenta_pagar` (`idcuenta_pagar`) ON DELETE CASCADE ON UPDATE NO ACTION,
  ADD CONSTRAINT `fk_pagopagar_usuario` FOREIGN KEY (`idusuario`) REFERENCES `usuario` (`idusuario`) ON DELETE NO ACTION ON UPDATE NO ACTION;

--
-- Filtros para la tabla `usuario_permiso`
--
ALTER TABLE `usuario_permiso`
  ADD CONSTRAINT `fk_u_permiso_usuario` FOREIGN KEY (`idusuario`) REFERENCES `usuario` (`idusuario`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  ADD CONSTRAINT `fk_usuario_permiso` FOREIGN KEY (`idpermiso`) REFERENCES `permiso` (`idpermiso`) ON DELETE NO ACTION ON UPDATE NO ACTION;

--
-- Filtros para la tabla `venta`
--
ALTER TABLE `venta`
  ADD CONSTRAINT `fk_venta_persona` FOREIGN KEY (`idcliente`) REFERENCES `persona` (`idpersona`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  ADD CONSTRAINT `fk_venta_usuario` FOREIGN KEY (`idusuario`) REFERENCES `usuario` (`idusuario`) ON DELETE NO ACTION ON UPDATE NO ACTION;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

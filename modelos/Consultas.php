<?php
/**
 * Modelo de consultas / reportes / dashboard.
 *
 * Todas las consultas con datos externos (fechas, ids) usan sentencias
 * preparadas. Los LIMIT se castean a (int) antes de concatenarse.
 *
 * Los metodos "clasicos" (usados por vistas/escritorio.php y ajax/consultas.php)
 * devuelven mysqli_result (fetch_object). Los metodos nuevos del dashboard
 * (resumenAlertas, utilidadResumenRango, ventasPor*) devuelven arrays.
 *
 * Reglas de negocio:
 *  - Solo suman ventas/ingresos con estado='Aceptado'.
 *  - Los kardex/valorizaciones incluyen ajuste_inventario (ENTRADA suma, SALIDA resta).
 *  - Costo de un articulo: costo promedio de ingresos del periodo o, si no hay,
 *    articulo.precio_compra.
 */
require_once "../config/Conexion.php";
require_once "../modelos/Lote.php";
require_once "../modelos/Variante.php";

class Consultas
{
	public function __construct()
	{
	}

	/** Normaliza un LIMIT a entero positivo. */
	private function limite($limit, $default)
	{
		$limit = (int)$limit;
		return $limit <= 0 ? (int)$default : $limit;
	}

	// ---------------------------------------------------------------
	// Consultas por fecha (comprasfecha / ventasfechacliente)
	// ---------------------------------------------------------------

	public function comprasfecha($fecha_inicio, $fecha_fin)
	{
		$sql = "SELECT DATE(i.fecha_hora) AS fecha, u.nombre AS usuario, p.nombre AS proveedor,
			i.tipo_comprobante, i.serie_comprobante, i.num_comprobante, i.total_compra, i.impuesto, i.estado
			FROM ingreso i
			INNER JOIN persona p ON i.idproveedor=p.idpersona
			INNER JOIN usuario u ON i.idusuario=u.idusuario
			WHERE DATE(i.fecha_hora)>=? AND DATE(i.fecha_hora)<=?
			ORDER BY i.fecha_hora DESC";
		return dbQuery($sql, array((string)$fecha_inicio, (string)$fecha_fin));
	}

	public function ventasfechacliente($fecha_inicio, $fecha_fin, $idcliente)
	{
		$sql = "SELECT DATE(v.fecha_hora) AS fecha, u.nombre AS usuario, p.nombre AS cliente,
			v.tipo_comprobante, v.serie_comprobante, v.num_comprobante, v.total_venta, v.impuesto, v.estado
			FROM venta v
			INNER JOIN persona p ON v.idcliente=p.idpersona
			INNER JOIN usuario u ON v.idusuario=u.idusuario
			WHERE DATE(v.fecha_hora)>=? AND DATE(v.fecha_hora)<=? AND v.idcliente=?
			ORDER BY v.fecha_hora DESC";
		return dbQuery($sql, array((string)$fecha_inicio, (string)$fecha_fin, (int)$idcliente));
	}

	// ---------------------------------------------------------------
	// Totales rapidos (sin datos externos)
	// ---------------------------------------------------------------

	public function totalcomprahoy()
	{
		$sql = "SELECT IFNULL(SUM(total_compra),0) AS total_compra FROM ingreso
			WHERE DATE(fecha_hora)=CURDATE() AND estado='Aceptado'";
		return dbQuery($sql);
	}

	public function totalventahoy()
	{
		$sql = "SELECT IFNULL(SUM(total_venta),0) AS total_venta FROM venta
			WHERE DATE(fecha_hora)=CURDATE() AND estado='Aceptado'";
		return dbQuery($sql);
	}

	public function comprasultimos_10dias()
	{
		$sql = "SELECT DATE(fecha_hora) AS fecha, IFNULL(SUM(total_compra),0) AS total
			FROM ingreso
			WHERE fecha_hora >= DATE_SUB(CURDATE(), INTERVAL 9 DAY) AND estado='Aceptado'
			GROUP BY DATE(fecha_hora)
			ORDER BY DATE(fecha_hora) ASC";
		return dbQuery($sql);
	}

	public function ventasultimos_12meses()
	{
		$sql = "SELECT DATE_FORMAT(fecha_hora,'%b %Y') AS fecha, IFNULL(SUM(total_venta),0) AS total
			FROM venta
			WHERE fecha_hora >= DATE_SUB(CURDATE(), INTERVAL 11 MONTH) AND estado='Aceptado'
			GROUP BY YEAR(fecha_hora), MONTH(fecha_hora)
			ORDER BY YEAR(fecha_hora), MONTH(fecha_hora)";
		return dbQuery($sql);
	}

	public function totalcomprasemes()
	{
		$sql = "SELECT IFNULL(SUM(total_compra),0) AS total_compra FROM ingreso
			WHERE YEAR(fecha_hora)=YEAR(CURDATE()) AND MONTH(fecha_hora)=MONTH(CURDATE()) AND estado='Aceptado'";
		return dbQuery($sql);
	}

	public function totalventasmes()
	{
		$sql = "SELECT IFNULL(SUM(total_venta),0) AS total_venta FROM venta
			WHERE YEAR(fecha_hora)=YEAR(CURDATE()) AND MONTH(fecha_hora)=MONTH(CURDATE()) AND estado='Aceptado'";
		return dbQuery($sql);
	}

	public function kpisgenerales()
	{
		$sql = "SELECT
			(SELECT COUNT(*) FROM articulo WHERE condicion=1) AS articulos_activos,
			(SELECT COUNT(*) FROM categoria WHERE condicion=1) AS categorias_activas,
			(SELECT COUNT(*) FROM persona WHERE tipo_persona='Cliente' AND condicion=1) AS clientes,
			(SELECT COUNT(*) FROM persona WHERE tipo_persona='Proveedor' AND condicion=1) AS proveedores,
			(SELECT IFNULL(SUM(stock),0) FROM articulo WHERE condicion=1) AS stock_total";
		return dbQuery($sql);
	}

	public function topproductosvendidos($limit = 7)
	{
		$limit = $this->limite($limit, 7);
		$sql = "SELECT a.nombre AS producto,
			IFNULL(SUM(dv.cantidad*dv.factor),0) AS cantidad,
			IFNULL(SUM((dv.cantidad*dv.precio_venta)-dv.descuento),0) AS total
			FROM detalle_venta dv
			INNER JOIN articulo a ON a.idarticulo=dv.idarticulo
			INNER JOIN venta v ON v.idventa=dv.idventa
			WHERE v.estado='Aceptado'
			GROUP BY dv.idarticulo, a.nombre
			ORDER BY total DESC
			LIMIT " . (int)$limit;
		return dbQuery($sql);
	}

	public function ventasporcategoria($limit = 8)
	{
		$limit = $this->limite($limit, 8);
		$sql = "SELECT c.nombre AS categoria,
			IFNULL(SUM((dv.cantidad*dv.precio_venta)-dv.descuento),0) AS total
			FROM detalle_venta dv
			INNER JOIN venta v ON v.idventa=dv.idventa
			INNER JOIN articulo a ON a.idarticulo=dv.idarticulo
			INNER JOIN categoria c ON c.idcategoria=a.idcategoria
			WHERE v.estado='Aceptado'
			AND YEAR(v.fecha_hora)=YEAR(CURDATE()) AND MONTH(v.fecha_hora)=MONTH(CURDATE())
			GROUP BY c.idcategoria, c.nombre
			ORDER BY total DESC
			LIMIT " . (int)$limit;
		return dbQuery($sql);
	}

	public function comprasultimos_6meses()
	{
		$sql = "SELECT DATE_FORMAT(fecha_hora,'%Y-%m') AS periodo, DATE_FORMAT(fecha_hora,'%b %Y') AS fecha, IFNULL(SUM(total_compra),0) AS total
			FROM ingreso
			WHERE fecha_hora >= DATE_SUB(CURDATE(), INTERVAL 5 MONTH) AND estado='Aceptado'
			GROUP BY DATE_FORMAT(fecha_hora,'%Y-%m'), DATE_FORMAT(fecha_hora,'%b %Y')
			ORDER BY YEAR(fecha_hora), MONTH(fecha_hora)";
		return dbQuery($sql);
	}

	public function ventasultimos_6meses()
	{
		$sql = "SELECT DATE_FORMAT(fecha_hora,'%Y-%m') AS periodo, DATE_FORMAT(fecha_hora,'%b %Y') AS fecha, IFNULL(SUM(total_venta),0) AS total
			FROM venta
			WHERE fecha_hora >= DATE_SUB(CURDATE(), INTERVAL 5 MONTH) AND estado='Aceptado'
			GROUP BY DATE_FORMAT(fecha_hora,'%Y-%m'), DATE_FORMAT(fecha_hora,'%b %Y')
			ORDER BY YEAR(fecha_hora), MONTH(fecha_hora)";
		return dbQuery($sql);
	}

	// ---------------------------------------------------------------
	// Reportes por periodo
	// ---------------------------------------------------------------

	/**
	 * Utilidad por articulo en el periodo. Costo: promedio de ingresos aceptados
	 * del periodo o, si no hubo ingresos, articulo.precio_compra.
	 */
	public function utilidadPorPeriodo($fecha_inicio, $fecha_fin)
	{
		$sql = "SELECT
			a.codigo,
			a.nombre AS articulo,
			IFNULL(c.nombre,'SIN CATEGORIA') AS categoria,
			IFNULL(SUM(dv.cantidad*dv.factor),0) AS cantidad_vendida,
			IFNULL(SUM((dv.cantidad*dv.precio_venta)-dv.descuento),0) AS venta_total,
			IFNULL(SUM(dv.cantidad*dv.factor*IFNULL(cp.costo_unitario, a.precio_compra)),0) AS costo_estimado
			FROM detalle_venta dv
			INNER JOIN venta v ON v.idventa=dv.idventa
			INNER JOIN articulo a ON a.idarticulo=dv.idarticulo
			LEFT JOIN categoria c ON c.idcategoria=a.idcategoria
			LEFT JOIN (
				SELECT di.idarticulo,
				CASE WHEN SUM(di.cantidad*di.factor)>0 THEN SUM(di.cantidad*di.precio_compra)/SUM(di.cantidad*di.factor) ELSE NULL END AS costo_unitario
				FROM detalle_ingreso di
				INNER JOIN ingreso i ON i.idingreso=di.idingreso
				WHERE DATE(i.fecha_hora)>=? AND DATE(i.fecha_hora)<=? AND i.estado='Aceptado'
				GROUP BY di.idarticulo
			) cp ON cp.idarticulo=dv.idarticulo
			WHERE DATE(v.fecha_hora)>=? AND DATE(v.fecha_hora)<=? AND v.estado='Aceptado'
			GROUP BY a.idarticulo, a.codigo, a.nombre, c.nombre, a.precio_compra
			ORDER BY venta_total DESC";
		return dbQuery($sql, array((string)$fecha_inicio, (string)$fecha_fin, (string)$fecha_inicio, (string)$fecha_fin));
	}

	public function topProductosPeriodo($fecha_inicio, $fecha_fin, $limit = 20, $modo = 'MAS')
	{
		$limit = $this->limite($limit, 20);
		$modo = strtoupper(trim((string)$modo));
		$orden = ($modo === 'MENOS') ? 'ASC' : 'DESC';
		$sql = "SELECT
			a.codigo,
			a.nombre AS articulo,
			IFNULL(c.nombre,'SIN CATEGORIA') AS categoria,
			IFNULL(u.abreviatura,'und') AS unidad,
			IFNULL(SUM(CASE WHEN v.idventa IS NOT NULL THEN dv.cantidad*dv.factor ELSE 0 END),0) AS cantidad,
			IFNULL(SUM(CASE WHEN v.idventa IS NOT NULL THEN ((dv.cantidad*dv.precio_venta)-dv.descuento) ELSE 0 END),0) AS total
			FROM articulo a
			LEFT JOIN categoria c ON c.idcategoria=a.idcategoria
			LEFT JOIN unidad_medida u ON u.idunidad=a.idunidad
			LEFT JOIN detalle_venta dv ON dv.idarticulo=a.idarticulo
			LEFT JOIN venta v ON v.idventa=dv.idventa
				AND DATE(v.fecha_hora)>=? AND DATE(v.fecha_hora)<=? AND v.estado='Aceptado'
			WHERE a.condicion=1
			GROUP BY a.idarticulo, a.codigo, a.nombre, c.nombre, u.abreviatura
			ORDER BY total " . $orden . ", cantidad " . $orden . "
			LIMIT " . (int)$limit;
		return dbQuery($sql, array((string)$fecha_inicio, (string)$fecha_fin));
	}

	/**
	 * Stock critico. El ultimo movimiento considera ingresos, ventas y ajustes.
	 */
	public function stockCritico()
	{
		$sql = "SELECT
			a.codigo,
			a.nombre AS articulo,
			IFNULL(c.nombre,'SIN CATEGORIA') AS categoria,
			IFNULL(u.abreviatura,'und') AS unidad,
			a.stock,
			IFNULL(a.stock_minimo,1) AS stock_minimo,
			IFNULL(DATEDIFF(CURDATE(), DATE(m.fecha_ultimo)),9999) AS dias_sin_mov,
			CASE
				WHEN a.stock<=0 THEN 'AGOTADO'
				WHEN a.stock<=IFNULL(a.stock_minimo,1) THEN 'BAJO MINIMO'
				WHEN a.stock<=IFNULL(a.stock_minimo,1)+5 THEN 'PROXIMO A AGOTARSE'
				WHEN m.fecha_ultimo IS NULL OR DATEDIFF(CURDATE(), DATE(m.fecha_ultimo))>=30 THEN 'SIN MOVIMIENTO'
				ELSE 'OK'
			END AS alerta,
			IFNULL(DATE_FORMAT(m.fecha_ultimo,'%d/%m/%Y'),'--') AS ultimo_mov
			FROM articulo a
			LEFT JOIN categoria c ON c.idcategoria=a.idcategoria
			LEFT JOIN unidad_medida u ON u.idunidad=a.idunidad
			LEFT JOIN (
				SELECT t.idarticulo, MAX(t.fecha_hora) AS fecha_ultimo
				FROM (
					SELECT di.idarticulo, i.fecha_hora
					FROM detalle_ingreso di
					INNER JOIN ingreso i ON i.idingreso=di.idingreso
					WHERE i.estado='Aceptado'
					UNION ALL
					SELECT dv.idarticulo, v.fecha_hora
					FROM detalle_venta dv
					INNER JOIN venta v ON v.idventa=dv.idventa
					WHERE v.estado='Aceptado'
					UNION ALL
					SELECT aj.idarticulo, aj.fecha_hora
					FROM ajuste_inventario aj
				) t
				GROUP BY t.idarticulo
			) m ON m.idarticulo=a.idarticulo
			WHERE a.condicion=1
			ORDER BY
				CASE
					WHEN a.stock<=0 THEN 0
					WHEN a.stock<=IFNULL(a.stock_minimo,1) THEN 1
					WHEN a.stock<=IFNULL(a.stock_minimo,1)+5 THEN 2
					WHEN m.fecha_ultimo IS NULL OR DATEDIFF(CURDATE(), DATE(m.fecha_ultimo))>=30 THEN 3
					ELSE 4
				END ASC,
				a.stock ASC,
				a.nombre ASC";
		return dbQuery($sql);
	}

	/**
	 * Kardex valorizado del periodo. Entradas = ingresos + ajustes ENTRADA,
	 * salidas = ventas + ajustes SALIDA. Costo promedio de ingresos del periodo
	 * o precio_compra del articulo.
	 */
	public function kardexValorizado($fecha_inicio, $fecha_fin)
	{
		$sql = "SELECT
			a.codigo,
			a.nombre AS articulo,
			IFNULL(u.abreviatura,'und') AS unidad,
			IFNULL(ent.entrada,0) + IFNULL(aje.entrada,0) AS entrada,
			IFNULL(sal.salida,0) + IFNULL(ajs.salida,0) AS salida,
			IFNULL(a.stock,0) AS saldo,
			IFNULL(ent.costo_promedio, a.precio_compra) AS costo_promedio,
			ROUND(IFNULL(a.stock,0)*IFNULL(ent.costo_promedio, a.precio_compra),2) AS valor_stock
			FROM articulo a
			LEFT JOIN unidad_medida u ON u.idunidad=a.idunidad
			LEFT JOIN (
				SELECT di.idarticulo,
				IFNULL(SUM(di.cantidad*di.factor),0) AS entrada,
				CASE WHEN SUM(di.cantidad*di.factor)>0 THEN SUM(di.cantidad*di.precio_compra)/SUM(di.cantidad*di.factor) ELSE NULL END AS costo_promedio
				FROM detalle_ingreso di
				INNER JOIN ingreso i ON i.idingreso=di.idingreso
				WHERE DATE(i.fecha_hora)>=? AND DATE(i.fecha_hora)<=? AND i.estado='Aceptado'
				GROUP BY di.idarticulo
			) ent ON ent.idarticulo=a.idarticulo
			LEFT JOIN (
				SELECT dv.idarticulo, IFNULL(SUM(dv.cantidad*dv.factor),0) AS salida
				FROM detalle_venta dv
				INNER JOIN venta v ON v.idventa=dv.idventa
				WHERE DATE(v.fecha_hora)>=? AND DATE(v.fecha_hora)<=? AND v.estado='Aceptado'
				GROUP BY dv.idarticulo
			) sal ON sal.idarticulo=a.idarticulo
			LEFT JOIN (
				SELECT aj.idarticulo, IFNULL(SUM(aj.cantidad),0) AS entrada
				FROM ajuste_inventario aj
				WHERE aj.tipo='ENTRADA' AND DATE(aj.fecha_hora)>=? AND DATE(aj.fecha_hora)<=?
				GROUP BY aj.idarticulo
			) aje ON aje.idarticulo=a.idarticulo
			LEFT JOIN (
				SELECT aj.idarticulo, IFNULL(SUM(aj.cantidad),0) AS salida
				FROM ajuste_inventario aj
				WHERE aj.tipo='SALIDA' AND DATE(aj.fecha_hora)>=? AND DATE(aj.fecha_hora)<=?
				GROUP BY aj.idarticulo
			) ajs ON ajs.idarticulo=a.idarticulo
			WHERE a.condicion=1
			ORDER BY a.nombre ASC";
		$fi = (string)$fecha_inicio;
		$ff = (string)$fecha_fin;
		return dbQuery($sql, array($fi, $ff, $fi, $ff, $fi, $ff, $fi, $ff));
	}

	public function clientesProveedoresPeriodo($fecha_inicio, $fecha_fin, $tipo = 'TODOS')
	{
		$tipo = strtoupper(trim((string)$tipo));
		$fi = (string)$fecha_inicio;
		$ff = (string)$fecha_fin;

		$sqlClientes = "SELECT
			'CLIENTE' AS tipo,
			IFNULL(p.nombre,'-') AS persona,
			IFNULL(p.num_documento,'-') AS documento,
			IFNULL(p.telefono,'-') AS telefono,
			COUNT(v.idventa) AS operaciones,
			IFNULL(SUM(v.total_venta),0) AS total,
			IFNULL(DATE_FORMAT(MAX(v.fecha_hora),'%d/%m/%Y'),'--') AS ultimo_mov
			FROM venta v
			LEFT JOIN persona p ON p.idpersona=v.idcliente
			WHERE DATE(v.fecha_hora)>=? AND DATE(v.fecha_hora)<=? AND v.estado='Aceptado'
			GROUP BY v.idcliente, p.nombre, p.num_documento, p.telefono";

		$sqlProveedores = "SELECT
			'PROVEEDOR' AS tipo,
			IFNULL(p.nombre,'-') AS persona,
			IFNULL(p.num_documento,'-') AS documento,
			IFNULL(p.telefono,'-') AS telefono,
			COUNT(i.idingreso) AS operaciones,
			IFNULL(SUM(i.total_compra),0) AS total,
			IFNULL(DATE_FORMAT(MAX(i.fecha_hora),'%d/%m/%Y'),'--') AS ultimo_mov
			FROM ingreso i
			LEFT JOIN persona p ON p.idpersona=i.idproveedor
			WHERE DATE(i.fecha_hora)>=? AND DATE(i.fecha_hora)<=? AND i.estado='Aceptado'
			GROUP BY i.idproveedor, p.nombre, p.num_documento, p.telefono";

		if ($tipo === 'CLIENTE') {
			return dbQuery($sqlClientes . " ORDER BY total DESC", array($fi, $ff));
		}
		if ($tipo === 'PROVEEDOR') {
			return dbQuery($sqlProveedores . " ORDER BY total DESC", array($fi, $ff));
		}
		$sql = "SELECT * FROM (" . $sqlClientes . " UNION ALL " . $sqlProveedores . ") q ORDER BY q.total DESC";
		return dbQuery($sql, array($fi, $ff, $fi, $ff));
	}

	public function ultimomovimientos($limit = 10)
	{
		$limit = $this->limite($limit, 10);
		$sql = "SELECT * FROM (
			SELECT 'Venta' AS tipo, v.fecha_hora AS fecha,
				CONCAT(v.tipo_comprobante,' ',v.serie_comprobante,'-',v.num_comprobante) AS documento,
				IFNULL(p.nombre,'-') AS persona, v.total_venta AS total, v.estado
			FROM venta v
			LEFT JOIN persona p ON p.idpersona=v.idcliente
			UNION ALL
			SELECT 'Compra' AS tipo, i.fecha_hora AS fecha,
				CONCAT(i.tipo_comprobante,' ',i.serie_comprobante,'-',i.num_comprobante) AS documento,
				IFNULL(p.nombre,'-') AS persona, i.total_compra AS total, i.estado
			FROM ingreso i
			LEFT JOIN persona p ON p.idpersona=i.idproveedor
			) t
			ORDER BY t.fecha DESC
			LIMIT " . (int)$limit;
		return dbQuery($sql);
	}

	// ---------------------------------------------------------------
	// Rangos (usados por vistas/escritorio.php)
	// ---------------------------------------------------------------

	public function totalcomprarango($fecha_inicio, $fecha_fin)
	{
		$sql = "SELECT IFNULL(SUM(total_compra),0) AS total_compra
			FROM ingreso
			WHERE DATE(fecha_hora)>=? AND DATE(fecha_hora)<=? AND estado='Aceptado'";
		return dbQuery($sql, array((string)$fecha_inicio, (string)$fecha_fin));
	}

	public function totalventarango($fecha_inicio, $fecha_fin)
	{
		$sql = "SELECT IFNULL(SUM(total_venta),0) AS total_venta
			FROM venta
			WHERE DATE(fecha_hora)>=? AND DATE(fecha_hora)<=? AND estado='Aceptado'";
		return dbQuery($sql, array((string)$fecha_inicio, (string)$fecha_fin));
	}

	public function comprasdiariasrango($fecha_inicio, $fecha_fin)
	{
		$sql = "SELECT DATE(fecha_hora) AS fecha, IFNULL(SUM(total_compra),0) AS total
			FROM ingreso
			WHERE DATE(fecha_hora)>=? AND DATE(fecha_hora)<=? AND estado='Aceptado'
			GROUP BY DATE(fecha_hora)
			ORDER BY DATE(fecha_hora) ASC";
		return dbQuery($sql, array((string)$fecha_inicio, (string)$fecha_fin));
	}

	public function ventasmensualesrango($fecha_inicio, $fecha_fin)
	{
		$sql = "SELECT DATE_FORMAT(fecha_hora,'%Y-%m') AS periodo, DATE_FORMAT(fecha_hora,'%b %Y') AS fecha, IFNULL(SUM(total_venta),0) AS total
			FROM venta
			WHERE DATE(fecha_hora)>=? AND DATE(fecha_hora)<=? AND estado='Aceptado'
			GROUP BY DATE_FORMAT(fecha_hora,'%Y-%m'), DATE_FORMAT(fecha_hora,'%b %Y')
			ORDER BY periodo ASC";
		return dbQuery($sql, array((string)$fecha_inicio, (string)$fecha_fin));
	}

	public function comprasmensualesrango($fecha_inicio, $fecha_fin)
	{
		$sql = "SELECT DATE_FORMAT(fecha_hora,'%Y-%m') AS periodo, DATE_FORMAT(fecha_hora,'%b %Y') AS fecha, IFNULL(SUM(total_compra),0) AS total
			FROM ingreso
			WHERE DATE(fecha_hora)>=? AND DATE(fecha_hora)<=? AND estado='Aceptado'
			GROUP BY DATE_FORMAT(fecha_hora,'%Y-%m'), DATE_FORMAT(fecha_hora,'%b %Y')
			ORDER BY periodo ASC";
		return dbQuery($sql, array((string)$fecha_inicio, (string)$fecha_fin));
	}

	public function ventasporcategoriarango($fecha_inicio, $fecha_fin, $limit = 8)
	{
		$limit = $this->limite($limit, 8);
		$sql = "SELECT c.nombre AS categoria,
			IFNULL(SUM((dv.cantidad*dv.precio_venta)-dv.descuento),0) AS total
			FROM detalle_venta dv
			INNER JOIN venta v ON v.idventa=dv.idventa
			INNER JOIN articulo a ON a.idarticulo=dv.idarticulo
			INNER JOIN categoria c ON c.idcategoria=a.idcategoria
			WHERE v.estado='Aceptado' AND DATE(v.fecha_hora)>=? AND DATE(v.fecha_hora)<=?
			GROUP BY c.idcategoria, c.nombre
			ORDER BY total DESC
			LIMIT " . (int)$limit;
		return dbQuery($sql, array((string)$fecha_inicio, (string)$fecha_fin));
	}

	public function topproductosvendidosrango($fecha_inicio, $fecha_fin, $limit = 7)
	{
		$limit = $this->limite($limit, 7);
		$sql = "SELECT a.nombre AS producto,
			IFNULL(SUM(dv.cantidad*dv.factor),0) AS cantidad,
			IFNULL(SUM((dv.cantidad*dv.precio_venta)-dv.descuento),0) AS total
			FROM detalle_venta dv
			INNER JOIN articulo a ON a.idarticulo=dv.idarticulo
			INNER JOIN venta v ON v.idventa=dv.idventa
			WHERE v.estado='Aceptado' AND DATE(v.fecha_hora)>=? AND DATE(v.fecha_hora)<=?
			GROUP BY dv.idarticulo, a.nombre
			ORDER BY total DESC
			LIMIT " . (int)$limit;
		return dbQuery($sql, array((string)$fecha_inicio, (string)$fecha_fin));
	}

	public function ultimomovimientosrango($fecha_inicio, $fecha_fin, $limit = 10)
	{
		$limit = $this->limite($limit, 10);
		$fi = (string)$fecha_inicio;
		$ff = (string)$fecha_fin;
		$sql = "SELECT * FROM (
			SELECT 'Venta' AS tipo, v.fecha_hora AS fecha,
				CONCAT(v.tipo_comprobante,' ',v.serie_comprobante,'-',v.num_comprobante) AS documento,
				IFNULL(p.nombre,'-') AS persona, v.total_venta AS total, v.estado
			FROM venta v
			LEFT JOIN persona p ON p.idpersona=v.idcliente
			WHERE DATE(v.fecha_hora)>=? AND DATE(v.fecha_hora)<=?
			UNION ALL
			SELECT 'Compra' AS tipo, i.fecha_hora AS fecha,
				CONCAT(i.tipo_comprobante,' ',i.serie_comprobante,'-',i.num_comprobante) AS documento,
				IFNULL(p.nombre,'-') AS persona, i.total_compra AS total, i.estado
			FROM ingreso i
			LEFT JOIN persona p ON p.idpersona=i.idproveedor
			WHERE DATE(i.fecha_hora)>=? AND DATE(i.fecha_hora)<=?
			) t
			ORDER BY t.fecha DESC
			LIMIT " . (int)$limit;
		return dbQuery($sql, array($fi, $ff, $fi, $ff));
	}

	// ---------------------------------------------------------------
	// Dashboard (devuelven arrays)
	// ---------------------------------------------------------------

	/**
	 * Resumen de alertas para el escritorio.
	 * Devuelve una fila (array asociativo) con contadores y montos.
	 */
	public function resumenAlertas($idusuario)
	{
		$sql = "SELECT
			(SELECT COUNT(*) FROM articulo WHERE condicion=1 AND stock<=0) AS articulos_agotados,
			(SELECT COUNT(*) FROM articulo WHERE condicion=1 AND stock>0 AND stock<=stock_minimo) AS articulos_bajo_minimo,
			(SELECT COUNT(*) FROM cuenta_cobrar WHERE estado='PENDIENTE' AND fecha_vencimiento<CURDATE()) AS cxc_vencidas,
			(SELECT IFNULL(SUM(saldo),0) FROM cuenta_cobrar WHERE estado='PENDIENTE' AND fecha_vencimiento<CURDATE()) AS cxc_vencidas_monto,
			(SELECT IFNULL(SUM(saldo),0) FROM cuenta_cobrar WHERE estado='PENDIENTE') AS cxc_pendiente_monto,
			(SELECT COUNT(*) FROM cuenta_pagar WHERE estado='PENDIENTE' AND fecha_vencimiento<CURDATE()) AS cxp_vencidas,
			(SELECT IFNULL(SUM(saldo),0) FROM cuenta_pagar WHERE estado='PENDIENTE' AND fecha_vencimiento<CURDATE()) AS cxp_vencidas_monto,
			(SELECT IFNULL(SUM(saldo),0) FROM cuenta_pagar WHERE estado='PENDIENTE') AS cxp_pendiente_monto,
			(SELECT IF(COUNT(*)>0,1,0) FROM caja_diaria WHERE idusuario=? AND estado='ABIERTA') AS caja_abierta,
			(SELECT IFNULL(SUM(total_venta),0) FROM venta WHERE estado='Aceptado' AND DATE(fecha_hora)=CURDATE()) AS ventas_hoy_monto,
			(SELECT COUNT(*) FROM venta WHERE estado='Aceptado' AND DATE(fecha_hora)=CURDATE()) AS ventas_hoy_cantidad,
			(SELECT IFNULL(SUM(total_compra),0) FROM ingreso WHERE estado='Aceptado' AND DATE(fecha_hora)=CURDATE()) AS compras_hoy_monto";
		$row = dbRow($sql, array((int)$idusuario));
		if (!$row) {
			$row = array(
				'articulos_agotados' => 0, 'articulos_bajo_minimo' => 0,
				'cxc_vencidas' => 0, 'cxc_vencidas_monto' => 0, 'cxc_pendiente_monto' => 0,
				'cxp_vencidas' => 0, 'cxp_vencidas_monto' => 0, 'cxp_pendiente_monto' => 0,
				'caja_abierta' => 0, 'ventas_hoy_monto' => 0, 'ventas_hoy_cantidad' => 0, 'compras_hoy_monto' => 0
			);
		}
		// Normalizar tipos
		foreach (array('articulos_agotados', 'articulos_bajo_minimo', 'cxc_vencidas', 'cxp_vencidas', 'caja_abierta', 'ventas_hoy_cantidad') as $k) {
			$row[$k] = (int)$row[$k];
		}
		foreach (array('cxc_vencidas_monto', 'cxc_pendiente_monto', 'cxp_vencidas_monto', 'cxp_pendiente_monto', 'ventas_hoy_monto', 'compras_hoy_monto') as $k) {
			$row[$k] = round((float)$row[$k], 2);
		}
		// Vencimientos (rubros con lotes)
		$row['usa_vencimientos'] = Lote::activo() ? 1 : 0;
		$row['lotes_vencidos'] = 0;
		$row['lotes_vencidos_valor'] = 0;
		$row['lotes_por_vencer'] = 0;
		$row['lotes_por_vencer_valor'] = 0;
		$row['dias_alerta_vencimiento'] = 0;
		if ($row['usa_vencimientos']) {
			$lr = Lote::resumen(Lote::diasAlerta());
			$row['lotes_vencidos'] = $lr['vencidos'];
			$row['lotes_vencidos_valor'] = $lr['vencidos_valor'];
			$row['lotes_por_vencer'] = $lr['por_vencer'];
			$row['lotes_por_vencer_valor'] = $lr['por_vencer_valor'];
			$row['dias_alerta_vencimiento'] = $lr['dias'];
		}
		// Tallas y colores agotados o bajo su minimo (rubro ropa)
		$row['usa_variantes'] = Variante::activo() ? 1 : 0;
		$row['variantes_agotadas'] = 0;
		$row['variantes_bajo_minimo'] = 0;
		if ($row['usa_variantes']) {
			$vr = Variante::resumenAlertas();
			$row['variantes_agotadas'] = $vr['agotadas'];
			$row['variantes_bajo_minimo'] = $vr['bajo_minimo'];
		}
		return $row;
	}

	/**
	 * Resumen de utilidad en un rango. Costo unitario por linea: precio de
	 * compra del ultimo ingreso aceptado con fecha <= fecha de la venta; si no
	 * existe, articulo.precio_compra.
	 */
	public function utilidadResumenRango($fecha_inicio, $fecha_fin)
	{
		$fi = (string)$fecha_inicio;
		$ff = (string)$fecha_fin;
		$sql = "SELECT
			IFNULL(SUM(t.venta),0) AS venta_total,
			IFNULL(SUM(t.costo),0) AS costo_total
			FROM (
				SELECT
					(dv.cantidad*dv.precio_venta)-dv.descuento AS venta,
					dv.cantidad*dv.factor*IFNULL((
						SELECT di2.precio_compra/di2.factor
						FROM detalle_ingreso di2
						INNER JOIN ingreso i2 ON i2.idingreso=di2.idingreso
						WHERE di2.idarticulo=dv.idarticulo AND i2.estado='Aceptado' AND i2.fecha_hora<=v.fecha_hora
						ORDER BY i2.fecha_hora DESC, di2.iddetalle_ingreso DESC
						LIMIT 1
					), a.precio_compra) AS costo
				FROM detalle_venta dv
				INNER JOIN venta v ON v.idventa=dv.idventa
				INNER JOIN articulo a ON a.idarticulo=dv.idarticulo
				WHERE v.estado='Aceptado' AND DATE(v.fecha_hora)>=? AND DATE(v.fecha_hora)<=?
			) t";
		$row = dbRow($sql, array($fi, $ff));
		$ventaTotal = $row ? (float)$row['venta_total'] : 0.0;
		$costoTotal = $row ? (float)$row['costo_total'] : 0.0;

		$numVentas = (int)dbValue(
			"SELECT COUNT(*) FROM venta WHERE estado='Aceptado' AND DATE(fecha_hora)>=? AND DATE(fecha_hora)<=?",
			array($fi, $ff),
			0
		);

		$utilidad = $ventaTotal - $costoTotal;
		$margen = $ventaTotal > 0 ? ($utilidad / $ventaTotal) * 100 : 0.0;
		$ticket = $numVentas > 0 ? ($ventaTotal / $numVentas) : 0.0;

		return array(
			'venta_total' => round($ventaTotal, 2),
			'costo_total' => round($costoTotal, 2),
			'utilidad' => round($utilidad, 2),
			'margen' => round($margen, 2),
			'num_ventas' => $numVentas,
			'ticket_promedio' => round($ticket, 2)
		);
	}

	/** Ventas aceptadas agrupadas por medio de pago. */
	public function ventasPorMedioPago($fecha_inicio, $fecha_fin)
	{
		$sql = "SELECT IFNULL(NULLIF(medio_pago,''),'OTRO') AS medio_pago,
			IFNULL(SUM(total_venta),0) AS total,
			COUNT(*) AS cantidad
			FROM venta
			WHERE estado='Aceptado' AND DATE(fecha_hora)>=? AND DATE(fecha_hora)<=?
			GROUP BY IFNULL(NULLIF(medio_pago,''),'OTRO')
			ORDER BY total DESC";
		return dbAll($sql, array((string)$fecha_inicio, (string)$fecha_fin));
	}

	/** Ventas aceptadas por vendedor (usuario). */
	public function ventasPorVendedor($fecha_inicio, $fecha_fin, $limit = 8)
	{
		$limit = $this->limite($limit, 8);
		$sql = "SELECT u.nombre AS vendedor,
			IFNULL(SUM(v.total_venta),0) AS total,
			COUNT(v.idventa) AS cantidad
			FROM venta v
			INNER JOIN usuario u ON u.idusuario=v.idusuario
			WHERE v.estado='Aceptado' AND DATE(v.fecha_hora)>=? AND DATE(v.fecha_hora)<=?
			GROUP BY v.idusuario, u.nombre
			ORDER BY total DESC
			LIMIT " . (int)$limit;
		return dbAll($sql, array((string)$fecha_inicio, (string)$fecha_fin));
	}

	/** Ventas aceptadas por dia. */
	public function ventasPorDiaRango($fecha_inicio, $fecha_fin)
	{
		$sql = "SELECT DATE(fecha_hora) AS fecha, IFNULL(SUM(total_venta),0) AS total
			FROM venta
			WHERE estado='Aceptado' AND DATE(fecha_hora)>=? AND DATE(fecha_hora)<=?
			GROUP BY DATE(fecha_hora)
			ORDER BY DATE(fecha_hora) ASC";
		return dbAll($sql, array((string)$fecha_inicio, (string)$fecha_fin));
	}

	/** Ventas aceptadas por hora del dia (0-23) para detectar horas pico. */
	public function ventasPorHora($fecha_inicio, $fecha_fin)
	{
		$sql = "SELECT HOUR(fecha_hora) AS hora, IFNULL(SUM(total_venta),0) AS total, COUNT(*) AS cantidad
			FROM venta
			WHERE estado='Aceptado' AND DATE(fecha_hora)>=? AND DATE(fecha_hora)<=?
			GROUP BY HOUR(fecha_hora)
			ORDER BY hora ASC";
		return dbAll($sql, array((string)$fecha_inicio, (string)$fecha_fin));
	}
}

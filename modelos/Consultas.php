<?php 
//incluir la conexion de base de datos
require "../config/Conexion.php";
class Consultas{


	//implementamos nuestro constructor
public function __construct(){

}

//listar registros
public function comprasfecha($fecha_inicio,$fecha_fin){
	$sql="SELECT DATE(i.fecha_hora) as fecha, u.nombre as usuario, p.nombre as proveedor, i.tipo_comprobante, i.serie_comprobante, i.num_comprobante, i.total_compra,i.impuesto,i.estado FROM ingreso i INNER JOIN persona p ON i.idproveedor=p.idpersona INNER JOIN usuario u ON i.idusuario=u.idusuario WHERE DATE(i.fecha_hora)>='$fecha_inicio' AND DATE(i.fecha_hora)<='$fecha_fin'";
	return ejecutarConsulta($sql);
}


public function ventasfechacliente($fecha_inicio,$fecha_fin,$idcliente){
	$sql="SELECT DATE(v.fecha_hora) as fecha, u.nombre as usuario, p.nombre as cliente, v.tipo_comprobante,v.serie_comprobante, v.num_comprobante , v.total_venta, v.impuesto, v.estado FROM venta v INNER JOIN persona p ON v.idcliente=p.idpersona INNER JOIN usuario u ON v.idusuario=u.idusuario WHERE DATE(v.fecha_hora)>='$fecha_inicio' AND DATE(v.fecha_hora)<='$fecha_fin' AND v.idcliente='$idcliente'";
	return ejecutarConsulta($sql);
}

public function totalcomprahoy(){
	$sql="SELECT IFNULL(SUM(total_compra),0) as total_compra FROM ingreso WHERE DATE(fecha_hora)=curdate()";
	return ejecutarConsulta($sql);
}

public function totalventahoy(){
	$sql="SELECT IFNULL(SUM(total_venta),0) as total_venta FROM venta WHERE DATE(fecha_hora)=curdate()";
	return ejecutarConsulta($sql);
}

public function comprasultimos_10dias(){
	$sql="SELECT DATE(fecha_hora) AS fecha, SUM(total_compra) AS total
	FROM ingreso
	WHERE fecha_hora >= DATE_SUB(CURDATE(), INTERVAL 9 DAY)
	GROUP BY DATE(fecha_hora)
	ORDER BY DATE(fecha_hora) ASC";
	return ejecutarConsulta($sql);
}

public function ventasultimos_12meses(){
	$sql="SELECT DATE_FORMAT(fecha_hora,'%b %Y') AS fecha, SUM(total_venta) AS total
	FROM venta
	WHERE fecha_hora >= DATE_SUB(CURDATE(), INTERVAL 11 MONTH)
	GROUP BY YEAR(fecha_hora), MONTH(fecha_hora)
	ORDER BY YEAR(fecha_hora), MONTH(fecha_hora)";
	return ejecutarConsulta($sql);
}

public function totalcomprasemes(){
	$sql="SELECT IFNULL(SUM(total_compra),0) AS total_compra
	FROM ingreso
	WHERE YEAR(fecha_hora)=YEAR(CURDATE()) AND MONTH(fecha_hora)=MONTH(CURDATE())";
	return ejecutarConsulta($sql);
}

public function totalventasmes(){
	$sql="SELECT IFNULL(SUM(total_venta),0) AS total_venta
	FROM venta
	WHERE YEAR(fecha_hora)=YEAR(CURDATE()) AND MONTH(fecha_hora)=MONTH(CURDATE())";
	return ejecutarConsulta($sql);
}

public function kpisgenerales(){
	$sql="SELECT
	(SELECT COUNT(*) FROM articulo WHERE condicion=1) AS articulos_activos,
	(SELECT COUNT(*) FROM categoria WHERE condicion=1) AS categorias_activas,
	(SELECT COUNT(*) FROM persona WHERE tipo_persona='Cliente') AS clientes,
	(SELECT COUNT(*) FROM persona WHERE tipo_persona='Proveedor') AS proveedores,
	(SELECT IFNULL(SUM(stock),0) FROM articulo WHERE condicion=1) AS stock_total";
	return ejecutarConsulta($sql);
}

public function topproductosvendidos($limit=7){
	$limit=(int)$limit;
	if ($limit<=0) $limit=7;
	$sql="SELECT a.nombre AS producto,
	IFNULL(SUM(dv.cantidad),0) AS cantidad,
	IFNULL(SUM((dv.cantidad*dv.precio_venta)-dv.descuento),0) AS total
	FROM detalle_venta dv
	INNER JOIN articulo a ON a.idarticulo=dv.idarticulo
	INNER JOIN venta v ON v.idventa=dv.idventa
	WHERE v.estado='Aceptado'
	GROUP BY dv.idarticulo, a.nombre
	ORDER BY total DESC
	LIMIT ".$limit;
	return ejecutarConsulta($sql);
}

public function ventasporcategoria($limit=8){
	$limit=(int)$limit;
	if ($limit<=0) $limit=8;
	$sql="SELECT c.nombre AS categoria,
	IFNULL(SUM((dv.cantidad*dv.precio_venta)-dv.descuento),0) AS total
	FROM detalle_venta dv
	INNER JOIN venta v ON v.idventa=dv.idventa
	INNER JOIN articulo a ON a.idarticulo=dv.idarticulo
	INNER JOIN categoria c ON c.idcategoria=a.idcategoria
	WHERE v.estado='Aceptado'
	AND YEAR(v.fecha_hora)=YEAR(CURDATE())
	AND MONTH(v.fecha_hora)=MONTH(CURDATE())
	GROUP BY c.idcategoria, c.nombre
	ORDER BY total DESC
	LIMIT ".$limit;
	return ejecutarConsulta($sql);
}

public function comprasultimos_6meses(){
	$sql="SELECT DATE_FORMAT(fecha_hora,'%Y-%m') AS periodo, DATE_FORMAT(fecha_hora,'%b %Y') AS fecha, IFNULL(SUM(total_compra),0) AS total
	FROM ingreso
	WHERE fecha_hora >= DATE_SUB(CURDATE(), INTERVAL 5 MONTH)
	GROUP BY DATE_FORMAT(fecha_hora,'%Y-%m'), DATE_FORMAT(fecha_hora,'%b %Y')
	ORDER BY YEAR(fecha_hora), MONTH(fecha_hora)";
	return ejecutarConsulta($sql);
}

public function ventasultimos_6meses(){
	$sql="SELECT DATE_FORMAT(fecha_hora,'%Y-%m') AS periodo, DATE_FORMAT(fecha_hora,'%b %Y') AS fecha, IFNULL(SUM(total_venta),0) AS total
	FROM venta
	WHERE fecha_hora >= DATE_SUB(CURDATE(), INTERVAL 5 MONTH)
	GROUP BY DATE_FORMAT(fecha_hora,'%Y-%m'), DATE_FORMAT(fecha_hora,'%b %Y')
	ORDER BY YEAR(fecha_hora), MONTH(fecha_hora)";
	return ejecutarConsulta($sql);
}

public function utilidadPorPeriodo($fecha_inicio,$fecha_fin){
	$sql="SELECT
	a.codigo,
	a.nombre AS articulo,
	IFNULL(c.nombre,'SIN CATEGORIA') AS categoria,
	IFNULL(SUM(dv.cantidad),0) AS cantidad_vendida,
	IFNULL(SUM((dv.cantidad*dv.precio_venta)-dv.descuento),0) AS venta_total,
	IFNULL(SUM(dv.cantidad*IFNULL(cp.costo_unitario,0)),0) AS costo_estimado
	FROM detalle_venta dv
	INNER JOIN venta v ON v.idventa=dv.idventa
	INNER JOIN articulo a ON a.idarticulo=dv.idarticulo
	LEFT JOIN categoria c ON c.idcategoria=a.idcategoria
	LEFT JOIN (
		SELECT di.idarticulo,
		CASE WHEN SUM(di.cantidad)>0 THEN SUM(di.cantidad*di.precio_compra)/SUM(di.cantidad) ELSE 0 END AS costo_unitario
		FROM detalle_ingreso di
		INNER JOIN ingreso i ON i.idingreso=di.idingreso
		WHERE DATE(i.fecha_hora)>='$fecha_inicio' AND DATE(i.fecha_hora)<='$fecha_fin'
		AND (i.estado='Aceptado' OR i.estado IS NULL OR i.estado='')
		GROUP BY di.idarticulo
	) cp ON cp.idarticulo=dv.idarticulo
	WHERE DATE(v.fecha_hora)>='$fecha_inicio' AND DATE(v.fecha_hora)<='$fecha_fin'
	AND v.estado='Aceptado'
	GROUP BY a.idarticulo,a.codigo,a.nombre,c.nombre
	ORDER BY venta_total DESC";
	return ejecutarConsulta($sql);
}

public function topProductosPeriodo($fecha_inicio,$fecha_fin,$limit=20,$modo='MAS'){
	$limit=(int)$limit;
	if ($limit<=0) $limit=20;
	$modo = strtoupper(trim((string)$modo));
	$orden = ($modo==='MENOS') ? 'ASC' : 'DESC';
	$sql="SELECT
	a.codigo,
	a.nombre AS articulo,
	IFNULL(c.nombre,'SIN CATEGORIA') AS categoria,
	IFNULL(u.abreviatura,'und') AS unidad,
	IFNULL(SUM(CASE WHEN v.idventa IS NOT NULL THEN dv.cantidad ELSE 0 END),0) AS cantidad,
	IFNULL(SUM(CASE WHEN v.idventa IS NOT NULL THEN ((dv.cantidad*dv.precio_venta)-dv.descuento) ELSE 0 END),0) AS total
	FROM articulo a
	LEFT JOIN categoria c ON c.idcategoria=a.idcategoria
	LEFT JOIN unidad_medida u ON u.idunidad=a.idunidad
	LEFT JOIN detalle_venta dv ON dv.idarticulo=a.idarticulo
	LEFT JOIN venta v ON v.idventa=dv.idventa
		AND DATE(v.fecha_hora)>='$fecha_inicio'
		AND DATE(v.fecha_hora)<='$fecha_fin'
		AND v.estado='Aceptado'
	WHERE a.condicion=1
	GROUP BY a.idarticulo,a.codigo,a.nombre,c.nombre,u.abreviatura
	ORDER BY total ".$orden.", cantidad ".$orden."
	LIMIT ".$limit;
	return ejecutarConsulta($sql);
}

public function stockCritico(){
	$sql="SELECT
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
			UNION ALL
			SELECT dv.idarticulo, v.fecha_hora
			FROM detalle_venta dv
			INNER JOIN venta v ON v.idventa=dv.idventa
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
	return ejecutarConsulta($sql);
}

public function kardexValorizado($fecha_inicio,$fecha_fin){
	$sql="SELECT
	a.codigo,
	a.nombre AS articulo,
	IFNULL(u.abreviatura,'und') AS unidad,
	IFNULL(ent.entrada,0) AS entrada,
	IFNULL(sal.salida,0) AS salida,
	IFNULL(a.stock,0) AS saldo,
	IFNULL(ent.costo_promedio,0) AS costo_promedio,
	ROUND(IFNULL(a.stock,0)*IFNULL(ent.costo_promedio,0),2) AS valor_stock
	FROM articulo a
	LEFT JOIN unidad_medida u ON u.idunidad=a.idunidad
	LEFT JOIN (
		SELECT
		di.idarticulo,
		IFNULL(SUM(di.cantidad),0) AS entrada,
		CASE WHEN SUM(di.cantidad)>0 THEN SUM(di.cantidad*di.precio_compra)/SUM(di.cantidad) ELSE 0 END AS costo_promedio
		FROM detalle_ingreso di
		INNER JOIN ingreso i ON i.idingreso=di.idingreso
		WHERE DATE(i.fecha_hora)>='$fecha_inicio' AND DATE(i.fecha_hora)<='$fecha_fin'
		AND (i.estado='Aceptado' OR i.estado IS NULL OR i.estado='')
		GROUP BY di.idarticulo
	) ent ON ent.idarticulo=a.idarticulo
	LEFT JOIN (
		SELECT
		dv.idarticulo,
		IFNULL(SUM(dv.cantidad),0) AS salida
		FROM detalle_venta dv
		INNER JOIN venta v ON v.idventa=dv.idventa
		WHERE DATE(v.fecha_hora)>='$fecha_inicio' AND DATE(v.fecha_hora)<='$fecha_fin'
		AND v.estado='Aceptado'
		GROUP BY dv.idarticulo
	) sal ON sal.idarticulo=a.idarticulo
	WHERE a.condicion=1
	ORDER BY a.nombre ASC";
	return ejecutarConsulta($sql);
}

public function clientesProveedoresPeriodo($fecha_inicio,$fecha_fin,$tipo='TODOS'){
	$tipo = strtoupper(trim((string)$tipo));
	if ($tipo==='CLIENTE') {
		$sql="SELECT
		'CLIENTE' AS tipo,
		IFNULL(p.nombre,'-') AS persona,
		IFNULL(p.num_documento,'-') AS documento,
		IFNULL(p.telefono,'-') AS telefono,
		COUNT(v.idventa) AS operaciones,
		IFNULL(SUM(v.total_venta),0) AS total,
		IFNULL(DATE_FORMAT(MAX(v.fecha_hora),'%d/%m/%Y'),'--') AS ultimo_mov
		FROM venta v
		LEFT JOIN persona p ON p.idpersona=v.idcliente
		WHERE DATE(v.fecha_hora)>='$fecha_inicio' AND DATE(v.fecha_hora)<='$fecha_fin'
		AND v.estado='Aceptado'
		GROUP BY v.idcliente,p.nombre,p.num_documento,p.telefono
		ORDER BY total DESC";
		return ejecutarConsulta($sql);
	}

	if ($tipo==='PROVEEDOR') {
		$sql="SELECT
		'PROVEEDOR' AS tipo,
		IFNULL(p.nombre,'-') AS persona,
		IFNULL(p.num_documento,'-') AS documento,
		IFNULL(p.telefono,'-') AS telefono,
		COUNT(i.idingreso) AS operaciones,
		IFNULL(SUM(i.total_compra),0) AS total,
		IFNULL(DATE_FORMAT(MAX(i.fecha_hora),'%d/%m/%Y'),'--') AS ultimo_mov
		FROM ingreso i
		LEFT JOIN persona p ON p.idpersona=i.idproveedor
		WHERE DATE(i.fecha_hora)>='$fecha_inicio' AND DATE(i.fecha_hora)<='$fecha_fin'
		AND (i.estado='Aceptado' OR i.estado IS NULL OR i.estado='')
		GROUP BY i.idproveedor,p.nombre,p.num_documento,p.telefono
		ORDER BY total DESC";
		return ejecutarConsulta($sql);
	}

	$sql="SELECT * FROM (
		SELECT
		'CLIENTE' AS tipo,
		IFNULL(p.nombre,'-') AS persona,
		IFNULL(p.num_documento,'-') AS documento,
		IFNULL(p.telefono,'-') AS telefono,
		COUNT(v.idventa) AS operaciones,
		IFNULL(SUM(v.total_venta),0) AS total,
		IFNULL(DATE_FORMAT(MAX(v.fecha_hora),'%d/%m/%Y'),'--') AS ultimo_mov
		FROM venta v
		LEFT JOIN persona p ON p.idpersona=v.idcliente
		WHERE DATE(v.fecha_hora)>='$fecha_inicio' AND DATE(v.fecha_hora)<='$fecha_fin'
		AND v.estado='Aceptado'
		GROUP BY v.idcliente,p.nombre,p.num_documento,p.telefono
		UNION ALL
		SELECT
		'PROVEEDOR' AS tipo,
		IFNULL(p.nombre,'-') AS persona,
		IFNULL(p.num_documento,'-') AS documento,
		IFNULL(p.telefono,'-') AS telefono,
		COUNT(i.idingreso) AS operaciones,
		IFNULL(SUM(i.total_compra),0) AS total,
		IFNULL(DATE_FORMAT(MAX(i.fecha_hora),'%d/%m/%Y'),'--') AS ultimo_mov
		FROM ingreso i
		LEFT JOIN persona p ON p.idpersona=i.idproveedor
		WHERE DATE(i.fecha_hora)>='$fecha_inicio' AND DATE(i.fecha_hora)<='$fecha_fin'
		AND (i.estado='Aceptado' OR i.estado IS NULL OR i.estado='')
		GROUP BY i.idproveedor,p.nombre,p.num_documento,p.telefono
	) q
	ORDER BY q.total DESC";
	return ejecutarConsulta($sql);
}

public function ultimomovimientos($limit=10){
	$limit=(int)$limit;
	if ($limit<=0) $limit=10;
	$sql="SELECT * FROM (
		SELECT
		'Venta' AS tipo,
		v.fecha_hora AS fecha,
		CONCAT(v.tipo_comprobante,' ',v.serie_comprobante,'-',v.num_comprobante) AS documento,
		IFNULL(p.nombre,'-') AS persona,
		v.total_venta AS total
		FROM venta v
		LEFT JOIN persona p ON p.idpersona=v.idcliente
		UNION ALL
		SELECT
		'Compra' AS tipo,
		i.fecha_hora AS fecha,
		CONCAT(i.tipo_comprobante,' ',i.serie_comprobante,'-',i.num_comprobante) AS documento,
		IFNULL(p.nombre,'-') AS persona,
		i.total_compra AS total
		FROM ingreso i
		LEFT JOIN persona p ON p.idpersona=i.idproveedor
	) t
	ORDER BY t.fecha DESC
	LIMIT ".$limit;
	return ejecutarConsulta($sql);
}

public function totalcomprarango($fecha_inicio,$fecha_fin){
	$sql="SELECT IFNULL(SUM(total_compra),0) AS total_compra
	FROM ingreso
	WHERE DATE(fecha_hora)>='$fecha_inicio' AND DATE(fecha_hora)<='$fecha_fin'";
	return ejecutarConsulta($sql);
}

public function totalventarango($fecha_inicio,$fecha_fin){
	$sql="SELECT IFNULL(SUM(total_venta),0) AS total_venta
	FROM venta
	WHERE DATE(fecha_hora)>='$fecha_inicio' AND DATE(fecha_hora)<='$fecha_fin'";
	return ejecutarConsulta($sql);
}

public function comprasdiariasrango($fecha_inicio,$fecha_fin){
	$sql="SELECT DATE(fecha_hora) AS fecha, IFNULL(SUM(total_compra),0) AS total
	FROM ingreso
	WHERE DATE(fecha_hora)>='$fecha_inicio' AND DATE(fecha_hora)<='$fecha_fin'
	GROUP BY DATE(fecha_hora)
	ORDER BY DATE(fecha_hora) ASC";
	return ejecutarConsulta($sql);
}

public function ventasmensualesrango($fecha_inicio,$fecha_fin){
	$sql="SELECT DATE_FORMAT(fecha_hora,'%Y-%m') AS periodo, DATE_FORMAT(fecha_hora,'%b %Y') AS fecha, IFNULL(SUM(total_venta),0) AS total
	FROM venta
	WHERE DATE(fecha_hora)>='$fecha_inicio' AND DATE(fecha_hora)<='$fecha_fin'
	GROUP BY DATE_FORMAT(fecha_hora,'%Y-%m'), DATE_FORMAT(fecha_hora,'%b %Y')
	ORDER BY YEAR(fecha_hora), MONTH(fecha_hora)";
	return ejecutarConsulta($sql);
}

public function comprasmensualesrango($fecha_inicio,$fecha_fin){
	$sql="SELECT DATE_FORMAT(fecha_hora,'%Y-%m') AS periodo, DATE_FORMAT(fecha_hora,'%b %Y') AS fecha, IFNULL(SUM(total_compra),0) AS total
	FROM ingreso
	WHERE DATE(fecha_hora)>='$fecha_inicio' AND DATE(fecha_hora)<='$fecha_fin'
	GROUP BY DATE_FORMAT(fecha_hora,'%Y-%m'), DATE_FORMAT(fecha_hora,'%b %Y')
	ORDER BY YEAR(fecha_hora), MONTH(fecha_hora)";
	return ejecutarConsulta($sql);
}

public function ventasporcategoriarango($fecha_inicio,$fecha_fin,$limit=8){
	$limit=(int)$limit;
	if ($limit<=0) $limit=8;
	$sql="SELECT c.nombre AS categoria,
	IFNULL(SUM((dv.cantidad*dv.precio_venta)-dv.descuento),0) AS total
	FROM detalle_venta dv
	INNER JOIN venta v ON v.idventa=dv.idventa
	INNER JOIN articulo a ON a.idarticulo=dv.idarticulo
	INNER JOIN categoria c ON c.idcategoria=a.idcategoria
	WHERE v.estado='Aceptado'
	AND DATE(v.fecha_hora)>='$fecha_inicio'
	AND DATE(v.fecha_hora)<='$fecha_fin'
	GROUP BY c.idcategoria, c.nombre
	ORDER BY total DESC
	LIMIT ".$limit;
	return ejecutarConsulta($sql);
}

public function topproductosvendidosrango($fecha_inicio,$fecha_fin,$limit=7){
	$limit=(int)$limit;
	if ($limit<=0) $limit=7;
	$sql="SELECT a.nombre AS producto,
	IFNULL(SUM(dv.cantidad),0) AS cantidad,
	IFNULL(SUM((dv.cantidad*dv.precio_venta)-dv.descuento),0) AS total
	FROM detalle_venta dv
	INNER JOIN articulo a ON a.idarticulo=dv.idarticulo
	INNER JOIN venta v ON v.idventa=dv.idventa
	WHERE v.estado='Aceptado'
	AND DATE(v.fecha_hora)>='$fecha_inicio'
	AND DATE(v.fecha_hora)<='$fecha_fin'
	GROUP BY dv.idarticulo, a.nombre
	ORDER BY total DESC
	LIMIT ".$limit;
	return ejecutarConsulta($sql);
}

public function ultimomovimientosrango($fecha_inicio,$fecha_fin,$limit=10){
	$limit=(int)$limit;
	if ($limit<=0) $limit=10;
	$sql="SELECT * FROM (
		SELECT
		'Venta' AS tipo,
		v.fecha_hora AS fecha,
		CONCAT(v.tipo_comprobante,' ',v.serie_comprobante,'-',v.num_comprobante) AS documento,
		IFNULL(p.nombre,'-') AS persona,
		v.total_venta AS total
		FROM venta v
		LEFT JOIN persona p ON p.idpersona=v.idcliente
		WHERE DATE(v.fecha_hora)>='$fecha_inicio' AND DATE(v.fecha_hora)<='$fecha_fin'
		UNION ALL
		SELECT
		'Compra' AS tipo,
		i.fecha_hora AS fecha,
		CONCAT(i.tipo_comprobante,' ',i.serie_comprobante,'-',i.num_comprobante) AS documento,
		IFNULL(p.nombre,'-') AS persona,
		i.total_compra AS total
		FROM ingreso i
		LEFT JOIN persona p ON p.idpersona=i.idproveedor
		WHERE DATE(i.fecha_hora)>='$fecha_inicio' AND DATE(i.fecha_hora)<='$fecha_fin'
	) t
	ORDER BY t.fecha DESC
	LIMIT ".$limit;
	return ejecutarConsulta($sql);
}


}

 ?>

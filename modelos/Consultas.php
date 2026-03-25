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
	$sql="SELECT DATE_FORMAT(fecha_hora,'%b %Y') AS fecha, IFNULL(SUM(total_compra),0) AS total
	FROM ingreso
	WHERE fecha_hora >= DATE_SUB(CURDATE(), INTERVAL 5 MONTH)
	GROUP BY YEAR(fecha_hora), MONTH(fecha_hora)
	ORDER BY YEAR(fecha_hora), MONTH(fecha_hora)";
	return ejecutarConsulta($sql);
}

public function ventasultimos_6meses(){
	$sql="SELECT DATE_FORMAT(fecha_hora,'%b %Y') AS fecha, IFNULL(SUM(total_venta),0) AS total
	FROM venta
	WHERE fecha_hora >= DATE_SUB(CURDATE(), INTERVAL 5 MONTH)
	GROUP BY YEAR(fecha_hora), MONTH(fecha_hora)
	ORDER BY YEAR(fecha_hora), MONTH(fecha_hora)";
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


}

 ?>

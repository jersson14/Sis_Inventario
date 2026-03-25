<?php 
//incluir la conexion de base de datos
require "../config/Conexion.php";
class Ingreso{


	//implementamos nuestro constructor
public function __construct(){

}

//metodo insertar registro
public function insertar($idproveedor,$idusuario,$tipo_comprobante,$serie_comprobante,$num_comprobante,$fecha_hora,$impuesto,$total_compra,$idarticulo,$cantidad,$precio_compra,$precio_venta){
	$sql="INSERT INTO ingreso (idproveedor,idusuario,tipo_comprobante,serie_comprobante,num_comprobante,fecha_hora,impuesto,total_compra,estado) VALUES ('$idproveedor','$idusuario','$tipo_comprobante','$serie_comprobante','$num_comprobante','$fecha_hora','$impuesto','$total_compra','Aceptado')";
	//return ejecutarConsulta($sql);
	 $idingresonew=ejecutarConsulta_retornarID($sql);
	 $num_elementos=0;
	 $sw=true;
	 while ($num_elementos < count($idarticulo)) {

	 	$sql_detalle="INSERT INTO detalle_ingreso (idingreso,idarticulo,cantidad,precio_compra,precio_venta) VALUES('$idingresonew','$idarticulo[$num_elementos]','$cantidad[$num_elementos]','$precio_compra[$num_elementos]','$precio_venta[$num_elementos]')";

	 	ejecutarConsulta($sql_detalle) or $sw=false;

	 	$num_elementos=$num_elementos+1;
	 }
	 return $sw;
}

public function anular($idingreso){
	$sql="UPDATE ingreso SET estado='Anulado' WHERE idingreso='$idingreso'";
	return ejecutarConsulta($sql);
}


//metodo para mostrar registros
public function mostrar($idingreso){
	$sql="SELECT i.idingreso,DATE(i.fecha_hora) as fecha,i.idproveedor,p.nombre as proveedor,u.idusuario,u.nombre as usuario, i.tipo_comprobante,i.serie_comprobante,i.num_comprobante,i.total_compra,i.impuesto,i.estado FROM ingreso i INNER JOIN persona p ON i.idproveedor=p.idpersona INNER JOIN usuario u ON i.idusuario=u.idusuario WHERE idingreso='$idingreso'";
	return ejecutarConsultaSimpleFila($sql);
}

public function listarDetalle($idingreso){
	$sql="SELECT di.idingreso,di.idarticulo,a.nombre,IFNULL(u.abreviatura,'und') as unidad,di.cantidad,di.precio_compra,di.precio_venta
	FROM detalle_ingreso di
	INNER JOIN articulo a ON di.idarticulo=a.idarticulo
	LEFT JOIN unidad_medida u ON a.idunidad=u.idunidad
	WHERE di.idingreso='$idingreso'";
	return ejecutarConsulta($sql);
}

//listar registros
public function listar(){
	$sql="SELECT i.idingreso,DATE(i.fecha_hora) as fecha,i.idproveedor,p.nombre as proveedor,u.idusuario,u.nombre as usuario, i.tipo_comprobante,i.serie_comprobante,i.num_comprobante,i.total_compra,i.impuesto,i.estado FROM ingreso i INNER JOIN persona p ON i.idproveedor=p.idpersona INNER JOIN usuario u ON i.idusuario=u.idusuario ORDER BY i.idingreso DESC";
	return ejecutarConsulta($sql);
}

public function ingresocabecera($idingreso){
	$sql="SELECT i.idingreso, i.idproveedor, p.nombre AS proveedor, p.direccion, p.tipo_documento, p.num_documento, p.email, p.telefono, i.idusuario, u.nombre AS usuario, i.tipo_comprobante, i.serie_comprobante, i.num_comprobante, DATE_FORMAT(i.fecha_hora,'%d/%m/%Y') AS fecha, i.impuesto, i.total_compra
	FROM ingreso i
	INNER JOIN persona p ON i.idproveedor=p.idpersona
	INNER JOIN usuario u ON i.idusuario=u.idusuario
	WHERE i.idingreso='$idingreso'";
	return ejecutarConsulta($sql);
}

public function ingresodetalles($idingreso){
	$sql="SELECT a.nombre AS articulo, a.codigo, IFNULL(u.abreviatura,'und') as unidad, d.cantidad, d.precio_compra, d.precio_venta, (d.cantidad*d.precio_compra) AS subtotal
	FROM detalle_ingreso d
	INNER JOIN articulo a ON d.idarticulo=a.idarticulo
	LEFT JOIN unidad_medida u ON a.idunidad=u.idunidad
	WHERE d.idingreso='$idingreso'";
	return ejecutarConsulta($sql);
}

}

 ?>

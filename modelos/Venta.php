<?php 
//incluir la conexion de base de datos
require "../config/Conexion.php";
class Venta{


	//implementamos nuestro constructor
public function __construct(){

}

//metodo insertar registro
public function insertar($idcliente,$idusuario,$tipo_comprobante,$serie_comprobante,$num_comprobante,$fecha_hora,$impuesto,$total_venta,$idarticulo,$cantidad,$precio_venta,$descuento){
	$sql="INSERT INTO venta (idcliente,idusuario,tipo_comprobante,serie_comprobante,num_comprobante,fecha_hora,impuesto,total_venta,estado) VALUES ('$idcliente','$idusuario','$tipo_comprobante','$serie_comprobante','$num_comprobante','$fecha_hora','$impuesto','$total_venta','Aceptado')";
	//return ejecutarConsulta($sql);
	 $idventanew=ejecutarConsulta_retornarID($sql);
	 $num_elementos=0;
	 $sw=true;
	 $articulosAfectados=array();
	 while ($num_elementos < count($idarticulo)) {

	 	$sql_detalle="INSERT INTO detalle_venta (idventa,idarticulo,cantidad,precio_venta,descuento) VALUES('$idventanew','$idarticulo[$num_elementos]','$cantidad[$num_elementos]','$precio_venta[$num_elementos]','$descuento[$num_elementos]')";

	 	ejecutarConsulta($sql_detalle) or $sw=false;
	 	$idArticuloActual=(int)$idarticulo[$num_elementos];
	 	if ($idArticuloActual>0) {
	 		$articulosAfectados[$idArticuloActual]=true;
	 	}

	 	$num_elementos=$num_elementos+1;
	 }

	 if (!$sw) {
	 	return array(
	 		"ok"=>false,
	 		"message"=>"No se pudo registrar el detalle de la venta"
	 	);
	 }

	 $alertas=array();
	 if (count($articulosAfectados)>0) {
	 	$ids=implode(",", array_keys($articulosAfectados));
	 	$sqlAlertas="SELECT idarticulo,codigo,nombre,stock,IFNULL(stock_minimo,0) AS stock_minimo
	 	FROM articulo
	 	WHERE idarticulo IN ($ids)
	 	AND stock<=GREATEST(IFNULL(stock_minimo,0),5)";
	 	$rsAlertas=ejecutarConsulta($sqlAlertas);
	 	while ($reg=$rsAlertas->fetch_assoc()) {
	 		$alertas[]=array(
	 			"idarticulo"=>$reg["idarticulo"],
	 			"codigo"=>$reg["codigo"],
	 			"nombre"=>$reg["nombre"],
	 			"stock"=>(float)$reg["stock"],
	 			"stock_minimo"=>(float)$reg["stock_minimo"]
	 		);
	 	}
	 }

	 return array(
	 	"ok"=>true,
	 	"idventa"=>$idventanew,
	 	"alertas"=>$alertas
	 );
}

public function anular($idventa){
	$sql="UPDATE venta SET estado='Anulado' WHERE idventa='$idventa'";
	return ejecutarConsulta($sql);
}


//implementar un metodopara mostrar los datos de unregistro a modificar
public function mostrar($idventa){
	$sql="SELECT v.idventa,DATE(v.fecha_hora) as fecha,v.idcliente,p.nombre as cliente,u.idusuario,u.nombre as usuario, v.tipo_comprobante,v.serie_comprobante,v.num_comprobante,v.total_venta,v.impuesto,v.estado FROM venta v INNER JOIN persona p ON v.idcliente=p.idpersona INNER JOIN usuario u ON v.idusuario=u.idusuario WHERE idventa='$idventa'";
	return ejecutarConsultaSimpleFila($sql);
}

public function listarDetalle($idventa){
	$sql="SELECT dv.idventa,dv.idarticulo,a.nombre,IFNULL(u.abreviatura,'und') as unidad,dv.cantidad,dv.precio_venta,dv.descuento,(dv.cantidad*dv.precio_venta-dv.descuento) as subtotal
	FROM detalle_venta dv
	INNER JOIN articulo a ON dv.idarticulo=a.idarticulo
	LEFT JOIN unidad_medida u ON a.idunidad=u.idunidad
	WHERE dv.idventa='$idventa'";
	return ejecutarConsulta($sql);
}

//listar registros
public function listar(){
	$sql="SELECT v.idventa,DATE(v.fecha_hora) as fecha,v.idcliente,p.nombre as cliente,u.idusuario,u.nombre as usuario, v.tipo_comprobante,v.serie_comprobante,v.num_comprobante,v.total_venta,v.impuesto,v.estado FROM venta v INNER JOIN persona p ON v.idcliente=p.idpersona INNER JOIN usuario u ON v.idusuario=u.idusuario ORDER BY v.idventa DESC";
	return ejecutarConsulta($sql);
}


public function ventacabecera($idventa){
	$sql= "SELECT v.idventa, v.idcliente, p.nombre AS cliente, p.direccion, p.tipo_documento, p.num_documento, p.email, p.telefono, v.idusuario, u.nombre AS usuario, v.tipo_comprobante, v.serie_comprobante, v.num_comprobante, DATE_FORMAT(v.fecha_hora,'%d/%m/%Y') AS fecha, v.impuesto, v.total_venta FROM venta v INNER JOIN persona p ON v.idcliente=p.idpersona INNER JOIN usuario u ON v.idusuario=u.idusuario WHERE v.idventa='$idventa'";
	return ejecutarConsulta($sql);
}

public function ventadetalles($idventa){
	$sql="SELECT a.nombre AS articulo, a.codigo, IFNULL(u.abreviatura,'und') as unidad, d.cantidad, d.precio_venta, d.descuento, (d.cantidad*d.precio_venta-d.descuento) AS subtotal
	FROM detalle_venta d
	INNER JOIN articulo a ON d.idarticulo=a.idarticulo
	LEFT JOIN unidad_medida u ON a.idunidad=u.idunidad
	WHERE d.idventa='$idventa'";
         return ejecutarConsulta($sql);
}


}

 ?>

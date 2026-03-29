<?php 
//incluir la conexion de base de datos
require "../config/Conexion.php";
class Ingreso{


	//implementamos nuestro constructor
public function __construct(){

}

private function normalizarTipoComprobante($tipo){
	$tipo = trim((string)$tipo);
	$permitidos = array("Boleta","Factura","Ticket");
	if (!in_array($tipo, $permitidos, true)) {
		return "Boleta";
	}
	return $tipo;
}

private function normalizarSerieComprobante($serie, $tipoComprobante){
	$serie = strtoupper(trim((string)$serie));
	$serie = preg_replace('/[^A-Z0-9]/', '', $serie);
	$serie = substr($serie, 0, 7);
	if ($serie !== '') {
		return $serie;
	}
	if ($tipoComprobante === "Factura") {
		return "F001";
	}
	if ($tipoComprobante === "Ticket") {
		return "T001";
	}
	return "B001";
}

private function obtenerCorrelativoInterno($tipoComprobante, $serieComprobante, $forUpdate = false){
	$tipoComprobante = $this->normalizarTipoComprobante($tipoComprobante);
	$serieComprobante = $this->normalizarSerieComprobante($serieComprobante, $tipoComprobante);
	$lockSql = $forUpdate ? " FOR UPDATE" : "";

	$sql = "SELECT IFNULL(MAX(CAST(num_comprobante AS UNSIGNED)),0) AS maximo
		FROM ingreso
		WHERE tipo_comprobante='$tipoComprobante'
		AND serie_comprobante='$serieComprobante'".$lockSql;
	$row = ejecutarConsultaSimpleFila($sql);
	$siguiente = isset($row["maximo"]) ? ((int)$row["maximo"] + 1) : 1;
	if ($siguiente <= 0) {
		$siguiente = 1;
	}

	return array(
		"tipo_comprobante"=>$tipoComprobante,
		"serie_comprobante"=>$serieComprobante,
		"correlativo"=>$siguiente,
		"numero"=>str_pad((string)$siguiente, 8, "0", STR_PAD_LEFT)
	);
}

public function obtenerSiguienteCorrelativo($tipoComprobante, $serieComprobante){
	$data = $this->obtenerCorrelativoInterno($tipoComprobante, $serieComprobante, false);
	return array(
		"ok"=>true,
		"tipo_comprobante"=>$data["tipo_comprobante"],
		"serie_comprobante"=>$data["serie_comprobante"],
		"correlativo"=>$data["correlativo"],
		"numero"=>$data["numero"]
	);
}

//metodo insertar registro
public function insertar($idproveedor,$idusuario,$tipo_comprobante,$serie_comprobante,$num_comprobante,$fecha_hora,$impuesto,$total_compra,$idarticulo,$cantidad,$precio_compra,$precio_venta){
	global $conexion;

	if (!is_array($idarticulo) || count($idarticulo) === 0) {
		return array(
			"ok"=>false,
			"message"=>"Debes agregar al menos un articulo al ingreso"
		);
	}
	if (!is_array($cantidad) || count($cantidad) !== count($idarticulo)) {
		return array(
			"ok"=>false,
			"message"=>"El detalle de cantidades no es valido"
		);
	}

	$detalles = array();
	for ($i = 0; $i < count($idarticulo); $i++) {
		$idArticuloActual = (int)$idarticulo[$i];
		$cantidadActual = (float)$cantidad[$i];
		$precioCompraActual = isset($precio_compra[$i]) ? (float)$precio_compra[$i] : 0;
		$precioVentaActual = isset($precio_venta[$i]) ? (float)$precio_venta[$i] : 0;

		if ($idArticuloActual <= 0) {
			return array(
				"ok"=>false,
				"message"=>"Se detecto un articulo invalido en el detalle"
			);
		}
		if ($cantidadActual <= 0) {
			return array(
				"ok"=>false,
				"message"=>"La cantidad debe ser mayor que cero"
			);
		}
		if ($precioCompraActual < 0 || $precioVentaActual < 0) {
			return array(
				"ok"=>false,
				"message"=>"Los precios no pueden ser negativos"
			);
		}

		$detalles[] = array(
			"idarticulo"=>$idArticuloActual,
			"cantidad"=>$cantidadActual,
			"precio_compra"=>$precioCompraActual,
			"precio_venta"=>$precioVentaActual
		);
	}

	$conexion->autocommit(false);

	try {
		$correlativo = $this->obtenerCorrelativoInterno($tipo_comprobante, $serie_comprobante, true);
		$tipo_comprobante = $correlativo["tipo_comprobante"];
		$serie_comprobante = $correlativo["serie_comprobante"];
		$num_comprobante = $correlativo["numero"];

		$sql="INSERT INTO ingreso (idproveedor,idusuario,tipo_comprobante,serie_comprobante,num_comprobante,fecha_hora,impuesto,total_compra,estado) VALUES ('$idproveedor','$idusuario','$tipo_comprobante','$serie_comprobante','$num_comprobante','$fecha_hora','$impuesto','$total_compra','Aceptado')";
		$idingresonew=ejecutarConsulta_retornarID($sql);
		if (!$idingresonew) {
			$conexion->rollback();
			$conexion->autocommit(true);
			return array(
				"ok"=>false,
				"message"=>"No se pudo registrar la cabecera del ingreso"
			);
		}

		$sw=true;
		for ($j = 0; $j < count($detalles); $j++) {
			$d = $detalles[$j];
			$sql_detalle="INSERT INTO detalle_ingreso (idingreso,idarticulo,cantidad,precio_compra,precio_venta) VALUES('".$idingresonew."','".$d["idarticulo"]."','".$d["cantidad"]."','".$d["precio_compra"]."','".$d["precio_venta"]."')";
			ejecutarConsulta($sql_detalle) or $sw=false;
			if (!$sw) {
				break;
			}
		}

		if (!$sw) {
			$conexion->rollback();
			$conexion->autocommit(true);
			return array(
				"ok"=>false,
				"message"=>"No se pudo registrar el detalle del ingreso"
			);
		}

		$conexion->commit();
		$conexion->autocommit(true);
	} catch (Throwable $e) {
		$conexion->rollback();
		$conexion->autocommit(true);
		return array(
			"ok"=>false,
			"message"=>"No se pudo registrar el ingreso: ".$e->getMessage()
		);
	}

	return array(
		"ok"=>true,
		"idingreso"=>$idingresonew,
		"tipo_comprobante"=>$tipo_comprobante,
		"serie_comprobante"=>$serie_comprobante,
		"num_comprobante"=>$num_comprobante
	);
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

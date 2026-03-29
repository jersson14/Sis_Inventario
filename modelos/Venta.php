<?php 
//incluir la conexion de base de datos
require "../config/Conexion.php";
class Venta{


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
		FROM venta
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
public function insertar($idcliente,$idusuario,$tipo_comprobante,$serie_comprobante,$num_comprobante,$fecha_hora,$impuesto,$total_venta,$idarticulo,$cantidad,$precio_venta,$descuento){
	global $conexion;

	if (!is_array($idarticulo) || count($idarticulo) === 0) {
		return array(
			"ok"=>false,
			"message"=>"Debes agregar al menos un articulo a la venta"
		);
	}
	if (!is_array($cantidad) || count($cantidad) !== count($idarticulo)) {
		return array(
			"ok"=>false,
			"message"=>"El detalle de cantidades no es valido"
		);
	}

	$cantidadesSolicitadas = array();
	$articulosAfectados = array();
	$detalles = array();

	for ($i = 0; $i < count($idarticulo); $i++) {
		$idArticuloActual = (int)$idarticulo[$i];
		$cantidadActual = (float)$cantidad[$i];
		$precioActual = isset($precio_venta[$i]) ? (float)$precio_venta[$i] : 0;
		$descuentoActual = isset($descuento[$i]) ? (float)$descuento[$i] : 0;

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

		if (!isset($cantidadesSolicitadas[$idArticuloActual])) {
			$cantidadesSolicitadas[$idArticuloActual] = 0;
		}
		$cantidadesSolicitadas[$idArticuloActual] += $cantidadActual;
		$articulosAfectados[$idArticuloActual] = true;
		$detalles[] = array(
			"idarticulo"=>$idArticuloActual,
			"cantidad"=>$cantidadActual,
			"precio_venta"=>$precioActual,
			"descuento"=>$descuentoActual
		);
	}

	ksort($cantidadesSolicitadas);

	$conexion->autocommit(false);

	try {
		$correlativo = $this->obtenerCorrelativoInterno($tipo_comprobante, $serie_comprobante, true);
		$tipo_comprobante = $correlativo["tipo_comprobante"];
		$serie_comprobante = $correlativo["serie_comprobante"];
		$num_comprobante = $correlativo["numero"];

		$ids = implode(",", array_keys($cantidadesSolicitadas));
		$sqlStock = "SELECT idarticulo,nombre,stock FROM articulo WHERE idarticulo IN ($ids) FOR UPDATE";
		$rsStock = ejecutarConsulta($sqlStock);
		if (!$rsStock) {
			$conexion->rollback();
			$conexion->autocommit(true);
			return array(
				"ok"=>false,
				"message"=>"No se pudo validar el stock de los articulos"
			);
		}

		$stockActual = array();
		while ($row = $rsStock->fetch_assoc()) {
			$stockActual[(int)$row["idarticulo"]] = array(
				"nombre"=>$row["nombre"],
				"stock"=>(float)$row["stock"]
			);
		}

		$erroresStock = array();
		foreach ($cantidadesSolicitadas as $idArt => $cantSolicitada) {
			if (!isset($stockActual[$idArt])) {
				$erroresStock[] = "Articulo ID ".$idArt." no encontrado";
				continue;
			}
			$stockDisp = (float)$stockActual[$idArt]["stock"];
			if ($stockDisp < $cantSolicitada) {
				$erroresStock[] = $stockActual[$idArt]["nombre"]." (stock: ".number_format($stockDisp,3).", solicitado: ".number_format($cantSolicitada,3).")";
			}
		}

		if (count($erroresStock) > 0) {
			$conexion->rollback();
			$conexion->autocommit(true);
			return array(
				"ok"=>false,
				"message"=>"Stock insuficiente: ".implode("; ", $erroresStock)
			);
		}

		$sql="INSERT INTO venta (idcliente,idusuario,tipo_comprobante,serie_comprobante,num_comprobante,fecha_hora,impuesto,total_venta,estado) VALUES ('$idcliente','$idusuario','$tipo_comprobante','$serie_comprobante','$num_comprobante','$fecha_hora','$impuesto','$total_venta','Aceptado')";
		$idventanew=ejecutarConsulta_retornarID($sql);
		if (!$idventanew) {
			$conexion->rollback();
			$conexion->autocommit(true);
			return array(
				"ok"=>false,
				"message"=>"No se pudo registrar la cabecera de la venta"
			);
		}

		$sw=true;
		for ($j = 0; $j < count($detalles); $j++) {
			$d = $detalles[$j];
			$sql_detalle="INSERT INTO detalle_venta (idventa,idarticulo,cantidad,precio_venta,descuento) VALUES('".$idventanew."','".$d["idarticulo"]."','".$d["cantidad"]."','".$d["precio_venta"]."','".$d["descuento"]."')";
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
				"message"=>"No se pudo registrar el detalle de la venta"
			);
		}

		$conexion->commit();
		$conexion->autocommit(true);
	} catch (Throwable $e) {
		$conexion->rollback();
		$conexion->autocommit(true);
		return array(
			"ok"=>false,
			"message"=>"No se pudo registrar la venta: ".$e->getMessage()
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
		"tipo_comprobante"=>$tipo_comprobante,
		"serie_comprobante"=>$serie_comprobante,
		"num_comprobante"=>$num_comprobante,
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

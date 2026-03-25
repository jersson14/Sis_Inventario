<?php 
//incluir la conexion de base de datos
require "../config/Conexion.php";
class Articulo{


	//implementamos nuestro constructor
public function __construct(){

}

//metodo insertar registro
public function insertar($idcategoria,$idunidad,$codigo,$nombre,$stock,$stock_minimo,$descripcion,$imagen){
	$sql="INSERT INTO articulo (idcategoria,idunidad,codigo,nombre,stock,stock_minimo,descripcion,imagen,condicion)
	 VALUES ('$idcategoria','$idunidad','$codigo','$nombre','$stock','$stock_minimo','$descripcion','$imagen','1')";
	return ejecutarConsulta($sql);
}

public function editar($idarticulo,$idcategoria,$idunidad,$codigo,$nombre,$stock,$stock_minimo,$descripcion,$imagen){
	$sql="UPDATE articulo SET idcategoria='$idcategoria',idunidad='$idunidad',codigo='$codigo', nombre='$nombre',stock='$stock',stock_minimo='$stock_minimo',descripcion='$descripcion',imagen='$imagen' 
	WHERE idarticulo='$idarticulo'";
	return ejecutarConsulta($sql);
}
public function desactivar($idarticulo){
	$sql="UPDATE articulo SET condicion='0' WHERE idarticulo='$idarticulo'";
	return ejecutarConsulta($sql);
}
public function activar($idarticulo){
	$sql="UPDATE articulo SET condicion='1' WHERE idarticulo='$idarticulo'";
	return ejecutarConsulta($sql);
}

//metodo para mostrar registros
public function mostrar($idarticulo){
	$sql="SELECT * FROM articulo WHERE idarticulo='$idarticulo'";
	return ejecutarConsultaSimpleFila($sql);
}

//listar registros 
public function listar(){
	$sql="SELECT a.idarticulo,a.idcategoria,a.idunidad,c.nombre as categoria,u.nombre as unidad,u.abreviatura,a.codigo,a.nombre,a.stock,a.stock_minimo,a.descripcion,a.imagen,a.condicion
	FROM articulo a
	INNER JOIN categoria c ON a.idcategoria=c.idcategoria
	LEFT JOIN unidad_medida u ON a.idunidad=u.idunidad";
	return ejecutarConsulta($sql);
}

//listar registros activos
public function listarActivos(){
	$sql="SELECT a.idarticulo,a.idcategoria,a.idunidad,c.nombre as categoria,u.nombre as unidad,u.abreviatura,a.codigo,a.nombre,a.stock,a.stock_minimo,
	(SELECT di.precio_compra FROM detalle_ingreso di WHERE di.idarticulo=a.idarticulo ORDER BY di.iddetalle_ingreso DESC LIMIT 0,1) AS precio_compra_ref,
	(SELECT di.precio_venta FROM detalle_ingreso di WHERE di.idarticulo=a.idarticulo ORDER BY di.iddetalle_ingreso DESC LIMIT 0,1) AS precio_venta_ref,
	a.descripcion,a.imagen,a.condicion
	FROM articulo a
	INNER JOIN categoria c ON a.idcategoria=c.idcategoria
	LEFT JOIN unidad_medida u ON a.idunidad=u.idunidad
	WHERE a.condicion='1'";
	return ejecutarConsulta($sql);
}

//implementar un metodo para listar los activos, su ultimo precio y el stock(vamos a unir con el ultimo registro de la tabla detalle_ingreso)
public function listarActivosVenta(){
	$sql="SELECT a.idarticulo,a.idcategoria,a.idunidad,c.nombre as categoria,u.nombre as unidad,u.abreviatura,a.codigo,a.nombre,a.stock,a.stock_minimo,
	(SELECT precio_venta FROM detalle_ingreso WHERE idarticulo=a.idarticulo ORDER BY iddetalle_ingreso DESC LIMIT 0,1) AS precio_venta,
	a.descripcion,a.imagen,a.condicion
	FROM articulo a
	INNER JOIN categoria c ON a.idcategoria=c.idcategoria
	LEFT JOIN unidad_medida u ON a.idunidad=u.idunidad
	WHERE a.condicion='1'";
	return ejecutarConsulta($sql);
}

public function buscarActivoPorCodigo($codigo){
	$sql="SELECT a.idarticulo,a.codigo,a.nombre,a.stock,IFNULL(u.abreviatura,'und') as abreviatura,
	(SELECT precio_venta FROM detalle_ingreso WHERE idarticulo=a.idarticulo ORDER BY iddetalle_ingreso DESC LIMIT 0,1) AS precio_venta
	FROM articulo a
	LEFT JOIN unidad_medida u ON a.idunidad=u.idunidad
	WHERE a.condicion='1'
	AND (a.codigo='$codigo' OR a.codigo LIKE '$codigo%' OR a.nombre LIKE '%$codigo%')
	ORDER BY (a.codigo='$codigo') DESC, a.idarticulo ASC
	LIMIT 1";
	return ejecutarConsultaSimpleFila($sql);
}
}
 ?>

<?php
// Modelo de articulos: todas las consultas con datos externos usan sentencias preparadas.
require_once "../config/Conexion.php";

class Articulo{

	public function __construct(){
	}

	// Registrar un articulo. Devuelve true/false.
	public function insertar($idcategoria,$idunidad,$codigo,$nombre,$stock,$stock_minimo,$precio_compra,$precio_venta,$descripcion,$imagen){
		$sql="INSERT INTO articulo (idcategoria,idunidad,codigo,nombre,stock,stock_minimo,precio_compra,precio_venta,descripcion,imagen,condicion)
			VALUES (?,?,?,?,?,?,?,?,?,?,1)";
		return dbExec($sql, array(
			(int)$idcategoria, (int)$idunidad, (string)$codigo, (string)$nombre,
			(float)$stock, (float)$stock_minimo, (float)$precio_compra, (float)$precio_venta,
			(string)$descripcion, (string)$imagen
		));
	}

	// Actualizar un articulo. Devuelve true/false.
	public function editar($idarticulo,$idcategoria,$idunidad,$codigo,$nombre,$stock,$stock_minimo,$precio_compra,$precio_venta,$descripcion,$imagen){
		$sql="UPDATE articulo SET idcategoria=?,idunidad=?,codigo=?,nombre=?,stock=?,stock_minimo=?,precio_compra=?,precio_venta=?,descripcion=?,imagen=?
			WHERE idarticulo=?";
		return dbExec($sql, array(
			(int)$idcategoria, (int)$idunidad, (string)$codigo, (string)$nombre,
			(float)$stock, (float)$stock_minimo, (float)$precio_compra, (float)$precio_venta,
			(string)$descripcion, (string)$imagen, (int)$idarticulo
		));
	}

	public function desactivar($idarticulo){
		return dbExec("UPDATE articulo SET condicion=0 WHERE idarticulo=?", array((int)$idarticulo));
	}

	public function activar($idarticulo){
		return dbExec("UPDATE articulo SET condicion=1 WHERE idarticulo=?", array((int)$idarticulo));
	}

	// Devuelve la fila del articulo (array asociativo) o null.
	public function mostrar($idarticulo){
		$sql="SELECT idarticulo,idcategoria,idunidad,codigo,nombre,stock,stock_minimo,precio_compra,precio_venta,descripcion,imagen,condicion,fecha_creacion,fecha_actualizacion
			FROM articulo WHERE idarticulo=?";
		return dbRow($sql, array((int)$idarticulo));
	}

	// Existe otro articulo con el mismo codigo (excluyendo $excluirId).
	public function existeCodigo($codigo, $excluirId = 0){
		$n = dbValue("SELECT COUNT(*) FROM articulo WHERE codigo=? AND idarticulo<>?", array((string)$codigo, (int)$excluirId), 0);
		return (int)$n > 0;
	}

	// Existe otro articulo con el mismo nombre (excluyendo $excluirId).
	public function existeNombre($nombre, $excluirId = 0){
		$n = dbValue("SELECT COUNT(*) FROM articulo WHERE nombre=? AND idarticulo<>?", array((string)$nombre, (int)$excluirId), 0);
		return (int)$n > 0;
	}

	// Listado completo (mysqli_result, sin datos externos).
	public function listar(){
		$sql="SELECT a.idarticulo,a.idcategoria,a.idunidad,c.nombre as categoria,u.nombre as unidad,u.abreviatura,a.codigo,a.nombre,a.stock,a.stock_minimo,
			a.precio_compra,a.precio_venta,a.descripcion,a.imagen,a.condicion
			FROM articulo a
			INNER JOIN categoria c ON a.idcategoria=c.idcategoria
			LEFT JOIN unidad_medida u ON a.idunidad=u.idunidad
			ORDER BY a.nombre ASC";
		return dbQuery($sql);
	}

	// Activos para compras: precio de referencia desde articulo y, si es 0, desde el ultimo ingreso.
	public function listarActivos(){
		$sql="SELECT a.idarticulo,a.idcategoria,a.idunidad,c.nombre as categoria,u.nombre as unidad,u.abreviatura,a.codigo,a.nombre,a.stock,a.stock_minimo,
			a.precio_compra,a.precio_venta,
			IF(a.precio_compra>0, a.precio_compra, IFNULL((SELECT precio_compra FROM detalle_ingreso WHERE idarticulo=a.idarticulo ORDER BY iddetalle_ingreso DESC LIMIT 1),0)) AS precio_compra_ref,
			IF(a.precio_venta>0, a.precio_venta, IFNULL((SELECT precio_venta FROM detalle_ingreso WHERE idarticulo=a.idarticulo ORDER BY iddetalle_ingreso DESC LIMIT 1),0)) AS precio_venta_ref,
			a.descripcion,a.imagen,a.condicion
			FROM articulo a
			INNER JOIN categoria c ON a.idcategoria=c.idcategoria
			LEFT JOIN unidad_medida u ON a.idunidad=u.idunidad
			WHERE a.condicion=1
			ORDER BY a.nombre ASC";
		return dbQuery($sql);
	}

	// Activos para ventas: precio_venta desde articulo y, si es 0, desde el ultimo ingreso.
	public function listarActivosVenta(){
		$sql="SELECT a.idarticulo,a.idcategoria,a.idunidad,c.nombre as categoria,u.nombre as unidad,u.abreviatura,a.codigo,a.nombre,a.stock,a.stock_minimo,
			a.precio_compra,
			IF(a.precio_venta>0, a.precio_venta, IFNULL((SELECT precio_venta FROM detalle_ingreso WHERE idarticulo=a.idarticulo ORDER BY iddetalle_ingreso DESC LIMIT 1),0)) AS precio_venta,
			a.descripcion,a.imagen,a.condicion
			FROM articulo a
			INNER JOIN categoria c ON a.idcategoria=c.idcategoria
			LEFT JOIN unidad_medida u ON a.idunidad=u.idunidad
			WHERE a.condicion=1
			ORDER BY a.nombre ASC";
		return dbQuery($sql);
	}

	// Busca un articulo activo por codigo exacto, prefijo de codigo o parte del nombre. Devuelve array o null.
	public function buscarActivoPorCodigo($codigo){
		$codigo = (string)$codigo;
		$sql="SELECT a.idarticulo,a.codigo,a.nombre,a.stock,IFNULL(u.abreviatura,'und') as abreviatura,
			IF(a.precio_venta>0, a.precio_venta, IFNULL((SELECT precio_venta FROM detalle_ingreso WHERE idarticulo=a.idarticulo ORDER BY iddetalle_ingreso DESC LIMIT 1),0)) AS precio_venta
			FROM articulo a
			LEFT JOIN unidad_medida u ON a.idunidad=u.idunidad
			WHERE a.condicion=1
			AND (a.codigo=? OR a.codigo LIKE CONCAT(?, '%') OR a.nombre LIKE CONCAT('%', ?, '%'))
			ORDER BY (a.codigo=?) DESC, a.idarticulo ASC
			LIMIT 1";
		return dbRow($sql, array($codigo, $codigo, $codigo, $codigo));
	}

	// Articulos activos con stock igual o menor al minimo. Devuelve array de filas.
	public function stockBajoMinimo($limite = 50){
		$limite = (int)$limite;
		if ($limite <= 0) {
			$limite = 50;
		}
		$sql="SELECT a.idarticulo,a.codigo,a.nombre,a.stock,a.stock_minimo,IFNULL(u.abreviatura,'') as abreviatura
			FROM articulo a
			LEFT JOIN unidad_medida u ON a.idunidad=u.idunidad
			WHERE a.condicion=1 AND a.stock<=a.stock_minimo
			ORDER BY (a.stock-a.stock_minimo) ASC, a.nombre ASC
			LIMIT " . $limite;
		return dbAll($sql);
	}
}

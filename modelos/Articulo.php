<?php
// Modelo de articulos: todas las consultas con datos externos usan sentencias preparadas.
require_once "../config/Conexion.php";
require_once "../config/negocio.php";
require_once "../modelos/Lote.php";

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

	// Igual que insertar() pero devuelve el id creado (0 si falla).
	public function insertarId($idcategoria,$idunidad,$codigo,$nombre,$stock,$stock_minimo,$precio_compra,$precio_venta,$descripcion,$imagen){
		$sql="INSERT INTO articulo (idcategoria,idunidad,codigo,nombre,stock,stock_minimo,precio_compra,precio_venta,descripcion,imagen,condicion)
			VALUES (?,?,?,?,?,?,?,?,?,?,1)";
		return (int)dbInsert($sql, array(
			(int)$idcategoria, (int)$idunidad, (string)$codigo, (string)$nombre,
			(float)$stock, (float)$stock_minimo, (float)$precio_compra, (float)$precio_venta,
			(string)$descripcion, (string)$imagen
		));
	}

	// ¿La unidad de medida admite decimales? (sin mirar el rubro)
	public function unidadPermiteFraccion($idunidad){
		return (int)dbValue("SELECT permite_fraccion FROM unidad_medida WHERE idunidad=?", array((int)$idunidad), 0) === 1;
	}

	// ---------- Presentaciones (empaques con equivalencia) ----------

	public function presentaciones($idarticulo){
		return dbAll(
			"SELECT idpresentacion,nombre,factor,precio_venta,precio_compra,codigo
			 FROM articulo_presentacion WHERE idarticulo=? AND condicion=1 ORDER BY factor ASC",
			array((int)$idarticulo)
		);
	}

	/**
	 * Sincroniza las presentaciones del articulo con las recibidas: actualiza
	 * las que vienen con id, crea las nuevas y desactiva (no borra) las que ya
	 * no estan, porque ventas y compras antiguas se refieren a ellas.
	 * $filas: array de {idpresentacion,nombre,factor,precio_venta,precio_compra,codigo}
	 * ya validadas (nombres unicos entre si). Debe llamarse dentro de una transaccion.
	 */
	public function guardarPresentaciones($idarticulo, array $filas){
		$idarticulo = (int)$idarticulo;
		$activas = array();
		foreach (dbAll("SELECT idpresentacion FROM articulo_presentacion WHERE idarticulo=? AND condicion=1", array($idarticulo)) as $r) {
			$activas[(int)$r['idpresentacion']] = true;
		}
		$recibidas = array();
		foreach ($filas as $f) {
			if ((int)$f['idpresentacion'] > 0) {
				$recibidas[(int)$f['idpresentacion']] = true;
			}
		}
		// 1. Las que ya no vienen se desactivan
		foreach ($activas as $id => $_) {
			if (!isset($recibidas[$id]) && !dbExec("UPDATE articulo_presentacion SET condicion=0 WHERE idpresentacion=?", array($id))) {
				return false;
			}
		}
		foreach ($filas as $f) {
			$id = (int)$f['idpresentacion'];
			$codigo = $f['codigo'] !== '' ? $f['codigo'] : null;
			if ($id > 0 && isset($activas[$id])) {
				// Si una presentacion inactiva tiene el nombre que se quiere usar, se le cambia para liberarlo
				dbExec(
					"UPDATE articulo_presentacion SET nombre=CONCAT(LEFT(nombre,40),' (anterior ',idpresentacion,')')
					 WHERE idarticulo=? AND nombre=? AND idpresentacion<>? AND condicion=0",
					array($idarticulo, $f['nombre'], $id)
				);
				$ok = dbExec(
					"UPDATE articulo_presentacion SET nombre=?,factor=?,precio_venta=?,precio_compra=?,codigo=?,condicion=1 WHERE idpresentacion=? AND idarticulo=?",
					array($f['nombre'], $f['factor'], $f['precio_venta'], $f['precio_compra'], $codigo, $id, $idarticulo)
				);
				if (!$ok) { return false; }
				continue;
			}
			// Nueva: si existio antes con el mismo nombre se reactiva (conserva su id e historial)
			$previa = (int)dbValue(
				"SELECT idpresentacion FROM articulo_presentacion WHERE idarticulo=? AND nombre=? AND condicion=0 LIMIT 1",
				array($idarticulo, $f['nombre']),
				0
			);
			if ($previa > 0) {
				$ok = dbExec(
					"UPDATE articulo_presentacion SET factor=?,precio_venta=?,precio_compra=?,codigo=?,condicion=1 WHERE idpresentacion=?",
					array($f['factor'], $f['precio_venta'], $f['precio_compra'], $codigo, $previa)
				);
				if (!$ok) { return false; }
				continue;
			}
			$nuevo = dbInsert(
				"INSERT INTO articulo_presentacion (idarticulo,nombre,factor,precio_venta,precio_compra,codigo,condicion) VALUES (?,?,?,?,?,?,1)",
				array($idarticulo, $f['nombre'], $f['factor'], $f['precio_venta'], $f['precio_compra'], $codigo)
			);
			if ($nuevo <= 0) { return false; }
		}
		return true;
	}

	// ---------- Precio por mayor ----------

	public function escalas($idarticulo){
		return dbAll(
			"SELECT cantidad_minima,precio FROM articulo_precio_escala WHERE idarticulo=? ORDER BY cantidad_minima ASC",
			array((int)$idarticulo)
		);
	}

	// Reemplaza las escalas del articulo. $filas: array de {cantidad_minima, precio}.
	public function guardarEscalas($idarticulo, array $filas){
		if (!dbExec("DELETE FROM articulo_precio_escala WHERE idarticulo=?", array((int)$idarticulo))) {
			return false;
		}
		foreach ($filas as $f) {
			$ok = dbInsert(
				"INSERT INTO articulo_precio_escala (idarticulo,cantidad_minima,precio) VALUES (?,?,?)",
				array((int)$idarticulo, $f['cantidad_minima'], $f['precio'])
			);
			if ($ok <= 0) { return false; }
		}
		return true;
	}

	// ¿El codigo lo usa otro articulo o una presentacion de otro articulo?
	public function codigoEnUso($codigo, $excluirArticulo = 0){
		$n = (int)dbValue(
			"SELECT (SELECT COUNT(*) FROM articulo WHERE codigo=? AND idarticulo<>?)
			      + (SELECT COUNT(*) FROM articulo_presentacion WHERE codigo=? AND idarticulo<>? AND condicion=1)",
			array((string)$codigo, (int)$excluirArticulo, (string)$codigo, (int)$excluirArticulo),
			0
		);
		return $n > 0;
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
			IF(a.precio_compra>0, a.precio_compra, IFNULL((SELECT precio_compra/factor FROM detalle_ingreso WHERE idarticulo=a.idarticulo ORDER BY iddetalle_ingreso DESC LIMIT 1),0)) AS precio_compra_ref,
			IF(a.precio_venta>0, a.precio_venta, IFNULL((SELECT precio_venta FROM detalle_ingreso WHERE idarticulo=a.idarticulo AND idpresentacion IS NULL ORDER BY iddetalle_ingreso DESC LIMIT 1),0)) AS precio_venta_ref,
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
			IF(a.precio_venta>0, a.precio_venta, IFNULL((SELECT precio_venta FROM detalle_ingreso WHERE idarticulo=a.idarticulo AND idpresentacion IS NULL ORDER BY iddetalle_ingreso DESC LIMIT 1),0)) AS precio_venta,
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
			IF(a.precio_venta>0, a.precio_venta, IFNULL((SELECT precio_venta FROM detalle_ingreso WHERE idarticulo=a.idarticulo AND idpresentacion IS NULL ORDER BY iddetalle_ingreso DESC LIMIT 1),0)) AS precio_venta
			FROM articulo a
			LEFT JOIN unidad_medida u ON a.idunidad=u.idunidad
			WHERE a.condicion=1
			AND (a.codigo=? OR a.codigo LIKE CONCAT(?, '%') OR a.nombre LIKE CONCAT('%', ?, '%'))
			ORDER BY (a.codigo=?) DESC, a.idarticulo ASC
			LIMIT 1";
		return dbRow($sql, array($codigo, $codigo, $codigo, $codigo));
	}

	/**
	 * Todo lo que el punto de venta o de compra necesita para agregar un
	 * articulo: datos base, si admite decimales, presentaciones y escalas de
	 * precio por mayor. Devuelve array o null si no existe o esta inactivo.
	 */
	public function fichaOperacion($idarticulo, $paraVenta = false){
		$a = dbRow(
			"SELECT a.idarticulo,a.codigo,a.nombre,a.stock,a.stock_minimo,IFNULL(u.abreviatura,'und') AS unidad,IFNULL(u.permite_fraccion,0) AS permite_fraccion,
				IF(a.precio_venta>0, a.precio_venta, IFNULL((SELECT precio_venta FROM detalle_ingreso WHERE idarticulo=a.idarticulo AND idpresentacion IS NULL ORDER BY iddetalle_ingreso DESC LIMIT 1),0)) AS precio_venta,
				IF(a.precio_compra>0, a.precio_compra, IFNULL((SELECT precio_compra/factor FROM detalle_ingreso WHERE idarticulo=a.idarticulo ORDER BY iddetalle_ingreso DESC LIMIT 1),0)) AS precio_compra
			 FROM articulo a
			 LEFT JOIN unidad_medida u ON a.idunidad=u.idunidad
			 WHERE a.idarticulo=? AND a.condicion=1",
			array((int)$idarticulo)
		);
		if (!$a) {
			return null;
		}
		$presentaciones = array();
		foreach (presentacionesArticulo($a['idarticulo']) as $p) {
			$presentaciones[] = array(
				'idpresentacion' => (int)$p['idpresentacion'],
				'nombre' => html_entity_decode((string)$p['nombre'], ENT_QUOTES, 'UTF-8'),
				'factor' => round((float)$p['factor'], 3),
				'precio_venta' => round((float)$p['precio_venta'], 2),
				'precio_compra' => round((float)$p['precio_compra'], 2)
			);
		}
		$escalas = array();
		foreach (escalasPrecioArticulo($a['idarticulo']) as $e) {
			$escalas[] = array('cantidad_minima' => round((float)$e['cantidad_minima'], 3), 'precio' => round((float)$e['precio'], 2));
		}
		// Vencimientos: en la venta, lo vencido no cuenta como disponible
		$vencido = 0.0;
		$proximo = null;
		if (Lote::activo()) {
			$vencido = Lote::stockVencido($a['idarticulo']);
			$p = Lote::proximoVencimiento($a['idarticulo']);
			if ($p) {
				$proximo = array('fecha' => $p['fecha_vencimiento'], 'codigo_lote' => (string)$p['codigo_lote'], 'stock' => round((float)$p['stock'], 3));
			}
		}
		$stock = round((float)$a['stock'], 3);
		return array(
			'idarticulo' => (int)$a['idarticulo'],
			'codigo' => (string)$a['codigo'],
			'stock_total' => $stock,
			'stock_vencido' => $vencido,
			'proximo_vencimiento' => $proximo,
			'nombre' => html_entity_decode((string)$a['nombre'], ENT_QUOTES, 'UTF-8'),
			'unidad' => (string)$a['unidad'],
			'stock' => $paraVenta ? max(round($stock - $vencido, 3), 0) : $stock,
			'stock_minimo' => round((float)$a['stock_minimo'], 3),
			'precio_venta' => round((float)$a['precio_venta'], 2),
			'precio_compra' => round((float)$a['precio_compra'], 2),
			'permite_fraccion' => negocioTiene('fracciones') && (int)$a['permite_fraccion'] === 1,
			'presentaciones' => $presentaciones,
			'escalas' => $escalas
		);
	}

	// Presentacion activa con ese codigo de barras exacto: array {idarticulo, idpresentacion} o null.
	public function buscarPresentacionPorCodigo($codigo){
		if (!negocioTiene('equivalencias') || (string)$codigo === '') {
			return null;
		}
		return dbRow(
			"SELECT p.idarticulo, p.idpresentacion FROM articulo_presentacion p
			 INNER JOIN articulo a ON a.idarticulo=p.idarticulo
			 WHERE p.codigo=? AND p.condicion=1 AND a.condicion=1 LIMIT 1",
			array((string)$codigo)
		);
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

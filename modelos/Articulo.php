<?php
// Modelo de articulos: todas las consultas con datos externos usan sentencias preparadas.
require_once "../config/Conexion.php";
require_once "../config/negocio.php";
require_once "../modelos/Lote.php";
require_once "../modelos/Variante.php";

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

	// ¿El codigo lo usa otro articulo, o una presentacion o talla/color de otro articulo?
	public function codigoEnUso($codigo, $excluirArticulo = 0){
		$n = (int)dbValue(
			"SELECT (SELECT COUNT(*) FROM articulo WHERE codigo=? AND idarticulo<>?)
			      + (SELECT COUNT(*) FROM articulo_presentacion WHERE codigo=? AND idarticulo<>? AND condicion=1)
			      + (SELECT COUNT(*) FROM articulo_variante WHERE codigo=? AND idarticulo<>? AND condicion=1)",
			array((string)$codigo, (int)$excluirArticulo, (string)$codigo, (int)$excluirArticulo, (string)$codigo, (int)$excluirArticulo),
			0
		);
		return $n > 0;
	}

	// Temporada y coleccion (rubro ropa)
	public function guardarTemporada($idarticulo, $temporada, $coleccion){
		return dbExec("UPDATE articulo SET temporada=?, coleccion=? WHERE idarticulo=?", array($temporada !== '' ? $temporada : null, $coleccion !== '' ? $coleccion : null, (int)$idarticulo));
	}

	public function desactivar($idarticulo){
		return dbExec("UPDATE articulo SET condicion=0 WHERE idarticulo=?", array((int)$idarticulo));
	}

	public function activar($idarticulo){
		return dbExec("UPDATE articulo SET condicion=1 WHERE idarticulo=?", array((int)$idarticulo));
	}

	// Devuelve la fila del articulo (array asociativo) o null.
	public function mostrar($idarticulo){
		$sql="SELECT idarticulo,idcategoria,idunidad,codigo,nombre,stock,stock_minimo,precio_compra,precio_venta,descripcion,temporada,coleccion,imagen,condicion,fecha_creacion,fecha_actualizacion
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
			a.precio_compra,a.precio_venta,a.descripcion,a.temporada,a.coleccion,a.imagen,a.condicion,
			(SELECT COUNT(*) FROM articulo_variante v WHERE v.idarticulo=a.idarticulo AND v.condicion=1) AS variantes
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
	 * Buscador en linea (pantalla de compra): por codigo del articulo, de una
	 * presentacion o de una talla/color, o por parte del nombre. El codigo
	 * exacto va primero para que el lector de barras agregue el correcto.
	 */
	public function buscarRapido($termino, $limite = 15){
		$termino = trim((string)$termino);
		if ($termino === '') {
			return array();
		}
		$filas = dbAll(
			"SELECT a.idarticulo,a.codigo,a.nombre,a.stock,IFNULL(u.abreviatura,'und') AS unidad,c.nombre AS categoria,a.imagen,
				IF(a.precio_compra>0, a.precio_compra, IFNULL((SELECT precio_compra/factor FROM detalle_ingreso WHERE idarticulo=a.idarticulo ORDER BY iddetalle_ingreso DESC LIMIT 1),0)) AS precio_compra,
				(a.codigo=?
				 OR EXISTS(SELECT 1 FROM articulo_presentacion p WHERE p.idarticulo=a.idarticulo AND p.condicion=1 AND p.codigo=?)
				 OR EXISTS(SELECT 1 FROM articulo_variante v WHERE v.idarticulo=a.idarticulo AND v.condicion=1 AND v.codigo=?)) AS exacto
			 FROM articulo a
			 INNER JOIN categoria c ON a.idcategoria=c.idcategoria
			 LEFT JOIN unidad_medida u ON a.idunidad=u.idunidad
			 WHERE a.condicion=1
			 AND (a.codigo LIKE CONCAT(?, '%') OR a.nombre LIKE CONCAT('%', ?, '%')
				OR EXISTS(SELECT 1 FROM articulo_presentacion p WHERE p.idarticulo=a.idarticulo AND p.condicion=1 AND p.codigo=?)
				OR EXISTS(SELECT 1 FROM articulo_variante v WHERE v.idarticulo=a.idarticulo AND v.condicion=1 AND v.codigo=?))
			 ORDER BY exacto DESC, a.nombre ASC
			 LIMIT ?",
			array($termino, $termino, $termino, $termino, $termino, $termino, $termino, max(1, min(50, (int)$limite)))
		);
		foreach ($filas as &$f) {
			$f['idarticulo'] = (int)$f['idarticulo'];
			$f['stock'] = round((float)$f['stock'], 3);
			$f['precio_compra'] = round((float)$f['precio_compra'], 2);
			$f['exacto'] = (int)$f['exacto'] === 1;
			$f['imagen'] = nombreArchivoSeguro($f['imagen']);
		}
		unset($f);
		return $filas;
	}

	/**
	 * Catalogo del punto de venta: todos los activos con lo necesario para
	 * pintar la cuadricula (la ficha completa se pide al agregar).
	 */
	public function catalogoPos(){
		$filas = dbAll(
			"SELECT a.idarticulo,a.codigo,a.nombre,a.stock,IFNULL(a.stock_minimo,0) AS stock_minimo,IFNULL(u.abreviatura,'und') AS unidad,
				a.idcategoria,c.nombre AS categoria,a.imagen,
				IF(a.precio_venta>0, a.precio_venta, IFNULL((SELECT precio_venta FROM detalle_ingreso WHERE idarticulo=a.idarticulo AND idpresentacion IS NULL ORDER BY iddetalle_ingreso DESC LIMIT 1),0)) AS precio_venta,
				(SELECT COUNT(*) FROM articulo_presentacion p WHERE p.idarticulo=a.idarticulo AND p.condicion=1) AS presentaciones,
				(SELECT COUNT(*) FROM articulo_variante v WHERE v.idarticulo=a.idarticulo AND v.condicion=1) AS variantes
			 FROM articulo a
			 INNER JOIN categoria c ON a.idcategoria=c.idcategoria
			 LEFT JOIN unidad_medida u ON a.idunidad=u.idunidad
			 WHERE a.condicion=1
			 ORDER BY a.nombre ASC"
		);
		foreach ($filas as &$f) {
			$f['idarticulo'] = (int)$f['idarticulo'];
			$f['idcategoria'] = (int)$f['idcategoria'];
			$f['stock'] = round((float)$f['stock'], 3);
			$f['stock_minimo'] = round((float)$f['stock_minimo'], 3);
			$f['precio_venta'] = round((float)$f['precio_venta'], 2);
			$f['presentaciones'] = (int)$f['presentaciones'];
			$f['variantes'] = (int)$f['variantes'];
			$f['imagen'] = nombreArchivoSeguro($f['imagen']);
		}
		unset($f);
		return $filas;
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
		// Tallas y colores: cada una con su stock y su precio efectivo
		$variantes = array();
		foreach (Variante::deArticulo($a['idarticulo']) as $v) {
			$variantes[] = array(
				'idvariante' => (int)$v['idvariante'],
				'talla' => html_entity_decode((string)$v['talla'], ENT_QUOTES, 'UTF-8'),
				'color' => html_entity_decode((string)$v['color'], ENT_QUOTES, 'UTF-8'),
				'etiqueta' => html_entity_decode(Variante::etiqueta($v['talla'], $v['color']), ENT_QUOTES, 'UTF-8'),
				'stock' => round((float)$v['stock'], 3),
				'precio_venta' => (float)$v['precio_venta'] > 0 ? round((float)$v['precio_venta'], 2) : round((float)$a['precio_venta'], 2)
			);
		}
		return array(
			'variantes' => $variantes,
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

	/**
	 * Precio de lista de una linea, igual que lo calcula el POS
	 * (precioAutomatico en venta.js): el de la presentacion; si no, el de la
	 * talla/color o el del articulo, reemplazado por el precio por mayor cuya
	 * cantidad minima se alcance.
	 */
	public static function precioLista(array $ficha, $idpresentacion, $idvariante, $cantidad){
		foreach ($ficha['presentaciones'] as $p) {
			if ($p['idpresentacion'] === (int)$idpresentacion) {
				return $p['precio_venta'] > 0 ? $p['precio_venta'] : $ficha['precio_venta'] * $p['factor'];
			}
		}
		$precio = $ficha['precio_venta'];
		foreach ($ficha['variantes'] as $v) {
			if ($v['idvariante'] === (int)$idvariante) {
				$precio = $v['precio_venta'];
			}
		}
		foreach ($ficha['escalas'] as $es) {
			if ((float)$cantidad + 0.0005 >= $es['cantidad_minima']) {
				$precio = $es['precio'];
			}
		}
		return $precio;
	}

	/**
	 * Para quien no tiene el permiso "Cambiar precios y descuentos": cada linea
	 * debe ir al precio de lista y sin descuento. $permitidas son lineas ya
	 * aprobadas (las de una cotizacion hecha por un encargado) que se aceptan
	 * tal cual. Devuelve '' si todo esta bien o el mensaje para el usuario.
	 */
	public function validarPreciosDeLista($idarticulo, $idpresentacion, $idvariante, $cantidad, $precio, $descuento, array $permitidas = array()){
		$fichas = array();
		foreach ((array)$idarticulo as $i => $idRaw) {
			$id = (int)$idRaw;
			$idPres = isset($idpresentacion[$i]) ? (int)$idpresentacion[$i] : 0;
			$idVar = isset($idvariante[$i]) ? (int)$idvariante[$i] : 0;
			$cant = isset($cantidad[$i]) ? (float)str_replace(',', '.', (string)$cantidad[$i]) : 0;
			$pre = isset($precio[$i]) ? round((float)$precio[$i], 2) : 0;
			$des = isset($descuento[$i]) ? round((float)$descuento[$i], 2) : 0;
			if ($id <= 0) {
				continue;
			}
			foreach ($permitidas as $l) {
				if ((int)$l['idarticulo'] === $id && (int)$l['idpresentacion'] === $idPres && (int)$l['idvariante'] === $idVar
					&& abs((float)$l['precio'] - $pre) < 0.005 && abs((float)$l['descuento'] - $des) < 0.005) {
					continue 2;
				}
			}
			if ($des > 0.004) {
				return "Tu usuario no puede aplicar descuentos. Pídeselo a un encargado.";
			}
			if (!array_key_exists($id, $fichas)) {
				$fichas[$id] = $this->fichaOperacion($id, true);
			}
			if (!$fichas[$id]) {
				continue;   // articulo inexistente: lo rechaza el registro de la venta
			}
			$lista = round(self::precioLista($fichas[$id], $idPres, $idVar, $cant), 2);
			// ±1 centimo: el POS redondea con toFixed(2) y PHP con round()
			if (abs($lista - $pre) > 0.0101) {
				return "No puedes cambiar el precio de " . $fichas[$id]['nombre'] . " (precio de lista " . number_format($lista, 2) . "). Pídeselo a un encargado.";
			}
		}
		return '';
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

<?php
require_once "../config/seguridad.php";
requiereLogin();                 // 401 JSON si no hay sesion; valida CSRF en POST
// Lectura (listar, mostrar, selects) tambien para ventas y compras; escritura solo almacen (se valida en cada case).
requierePermiso(array('almacen', 'ventas', 'compras'));
require_once "../modelos/Articulo.php";

$articulo = new Articulo();

$idarticulo    = enteroSeguro(isset($_POST["idarticulo"]) ? $_POST["idarticulo"] : 0);
$idcategoria   = enteroSeguro(isset($_POST["idcategoria"]) ? $_POST["idcategoria"] : 0);
$idunidad      = enteroSeguro(isset($_POST["idunidad"]) ? $_POST["idunidad"] : 0);
$codigo        = isset($_POST["codigo"]) ? limpiarCadena($_POST["codigo"]) : "";
$nombre        = isset($_POST["nombre"]) ? limpiarCadena($_POST["nombre"]) : "";
// Stock con decimales solo si el rubro usa fracciones y la unidad lo admite
$stockFraccion = ($idunidad > 0 && negocioTiene('fracciones')) ? $articulo->unidadPermiteFraccion($idunidad) : false;
$stock         = cantidadSegura(isset($_POST["stock"]) ? $_POST["stock"] : 0, $stockFraccion);
$stock_minimo  = cantidadSegura(isset($_POST["stock_minimo"]) ? $_POST["stock_minimo"] : 1, $stockFraccion);
$precio_compra = decimalSeguro(isset($_POST["precio_compra"]) ? $_POST["precio_compra"] : 0, 2, 0);
$precio_venta  = decimalSeguro(isset($_POST["precio_venta"]) ? $_POST["precio_venta"] : 0, 2, 0);
$descripcion   = isset($_POST["descripcion"]) ? limpiarCadena($_POST["descripcion"]) : "";

if ($stock < 0) {
	$stock = 0;
}
if ($stock_minimo < 0) {
	$stock_minimo = 0;
}
if ($precio_compra < 0) {
	$precio_compra = 0.0;
}
if ($precio_venta < 0) {
	$precio_venta = 0.0;
}

/**
 * Lee y valida las presentaciones enviadas. Devuelve array(true, filas) o
 * array(false, mensaje). Nombres y codigos unicos, factor distinto de 1.
 */
function leerPresentacionesPost($articulo, $idarticulo, $codigoBase) {
	$ids      = isset($_POST['pres_id']) && is_array($_POST['pres_id']) ? $_POST['pres_id'] : array();
	$nombres  = isset($_POST['pres_nombre']) && is_array($_POST['pres_nombre']) ? $_POST['pres_nombre'] : array();
	$factores = isset($_POST['pres_factor']) && is_array($_POST['pres_factor']) ? $_POST['pres_factor'] : array();
	$pventa   = isset($_POST['pres_precio_venta']) && is_array($_POST['pres_precio_venta']) ? $_POST['pres_precio_venta'] : array();
	$pcompra  = isset($_POST['pres_precio_compra']) && is_array($_POST['pres_precio_compra']) ? $_POST['pres_precio_compra'] : array();
	$codigos  = isset($_POST['pres_codigo']) && is_array($_POST['pres_codigo']) ? $_POST['pres_codigo'] : array();

	$filas = array();
	$vistosNombre = array();
	$vistosCodigo = array();
	foreach ($nombres as $i => $nombreRaw) {
		$nombre = limpiarCadena($nombreRaw);
		$factor = round(decimalSeguro(isset($factores[$i]) ? $factores[$i] : 0, 3, 0), 3);
		$codigo = limpiarCadena(isset($codigos[$i]) ? $codigos[$i] : '');
		if ($nombre === '' && $factor <= 0 && $codigo === '') {
			continue; // fila vacia
		}
		if ($nombre === '' || mb_strlen($nombre) > 60) {
			return array(false, 'Cada presentación necesita un nombre de hasta 60 caracteres (ej. Caja x100)');
		}
		$clave = mb_strtolower($nombre);
		if (isset($vistosNombre[$clave])) {
			return array(false, 'La presentación "' . $nombre . '" está repetida');
		}
		$vistosNombre[$clave] = true;
		if ($factor <= 0) {
			return array(false, 'Indica cuántas unidades contiene la presentación "' . $nombre . '"');
		}
		if (abs($factor - 1) < 0.0005) {
			return array(false, 'La presentación "' . $nombre . '" contiene 1 unidad: es igual a la unidad base, no hace falta crearla');
		}
		$precioVenta = decimalSeguro(isset($pventa[$i]) ? $pventa[$i] : 0, 2, 0);
		$precioCompra = decimalSeguro(isset($pcompra[$i]) ? $pcompra[$i] : 0, 2, 0);
		if ($precioVenta < 0 || $precioCompra < 0) {
			return array(false, 'Los precios de la presentación "' . $nombre . '" no pueden ser negativos');
		}
		if ($codigo !== '') {
			if (mb_strlen($codigo) > 50) {
				return array(false, 'El código de la presentación "' . $nombre . '" supera 50 caracteres');
			}
			if ($codigo === $codigoBase || isset($vistosCodigo[$codigo])) {
				return array(false, 'El código ' . $codigo . ' se repite dentro del artículo');
			}
			if ($articulo->codigoEnUso($codigo, $idarticulo)) {
				return array(false, 'El código ' . $codigo . ' ya lo usa otro artículo');
			}
			$vistosCodigo[$codigo] = true;
		}
		$filas[] = array(
			'idpresentacion' => enteroSeguro(isset($ids[$i]) ? $ids[$i] : 0),
			'nombre' => $nombre,
			'factor' => $factor,
			'precio_venta' => $precioVenta,
			'precio_compra' => $precioCompra,
			'codigo' => $codigo
		);
	}
	return array(true, $filas);
}

/**
 * Lee y valida las escalas de precio por mayor. Devuelve array(true, filas)
 * o array(false, mensaje).
 */
function leerEscalasPost($permiteFraccion) {
	$cantidades = isset($_POST['escala_cantidad']) && is_array($_POST['escala_cantidad']) ? $_POST['escala_cantidad'] : array();
	$precios = isset($_POST['escala_precio']) && is_array($_POST['escala_precio']) ? $_POST['escala_precio'] : array();
	$filas = array();
	$vistas = array();
	foreach ($cantidades as $i => $cantRaw) {
		$precioRaw = isset($precios[$i]) ? trim((string)$precios[$i]) : '';
		if (trim((string)$cantRaw) === '' && $precioRaw === '') {
			continue;
		}
		$cantidad = cantidadSegura($cantRaw, $permiteFraccion);
		$precio = decimalSeguro($precioRaw, 2, -1);
		if ($cantidad <= 0) {
			return array(false, 'La cantidad mínima de cada precio por mayor debe ser mayor que cero');
		}
		if ($precio <= 0) {
			return array(false, 'Indica el precio por mayor desde ' . formatearCantidad($cantidad) . ' unidades');
		}
		$clave = (string)$cantidad;
		if (isset($vistas[$clave])) {
			return array(false, 'Hay dos precios por mayor desde la misma cantidad (' . formatearCantidad($cantidad) . ')');
		}
		$vistas[$clave] = true;
		$filas[] = array('cantidad_minima' => $cantidad, 'precio' => $precio);
	}
	usort($filas, function ($a, $b) { return $a['cantidad_minima'] <=> $b['cantidad_minima']; });
	return array(true, $filas);
}

/**
 * Lee y valida las tallas/colores del formulario. Devuelve array(true, filas)
 * o array(false, mensaje). El stock solo se toma para variantes nuevas.
 */
function leerVariantesPost($articulo, $idarticulo, $codigoBase, $permiteFraccion) {
	$ids      = isset($_POST['var_id']) && is_array($_POST['var_id']) ? $_POST['var_id'] : array();
	$tallas   = isset($_POST['var_talla']) && is_array($_POST['var_talla']) ? $_POST['var_talla'] : array();
	$colores  = isset($_POST['var_color']) && is_array($_POST['var_color']) ? $_POST['var_color'] : array();
	$codigos  = isset($_POST['var_codigo']) && is_array($_POST['var_codigo']) ? $_POST['var_codigo'] : array();
	$stocks   = isset($_POST['var_stock']) && is_array($_POST['var_stock']) ? $_POST['var_stock'] : array();
	$minimos  = isset($_POST['var_stock_minimo']) && is_array($_POST['var_stock_minimo']) ? $_POST['var_stock_minimo'] : array();
	$precios  = isset($_POST['var_precio']) && is_array($_POST['var_precio']) ? $_POST['var_precio'] : array();

	$filas = array();
	$vistas = array();
	$vistosCodigo = array();
	foreach ($tallas as $i => $tallaRaw) {
		$talla = Variante::textoCorto(limpiarCadena($tallaRaw), 20);
		$color = Variante::textoCorto(limpiarCadena(isset($colores[$i]) ? $colores[$i] : ''), 30);
		$codigo = limpiarCadena(isset($codigos[$i]) ? $codigos[$i] : '');
		if ($talla === '' && $color === '') {
			if ($codigo !== '') {
				return array(false, 'Hay un código de talla/color sin talla ni color');
			}
			continue;
		}
		$etiqueta = Variante::etiqueta($talla, $color);
		$clave = mb_strtolower($talla . '|' . $color);
		if (isset($vistas[$clave])) {
			return array(false, 'La combinación ' . $etiqueta . ' está repetida');
		}
		$vistas[$clave] = true;
		if ($codigo !== '') {
			if (mb_strlen($codigo) > 50) {
				return array(false, 'El código de ' . $etiqueta . ' supera 50 caracteres');
			}
			if ($codigo === $codigoBase || isset($vistosCodigo[$codigo])) {
				return array(false, 'El código ' . $codigo . ' se repite dentro del artículo');
			}
			if ($articulo->codigoEnUso($codigo, $idarticulo)) {
				return array(false, 'El código ' . $codigo . ' ya lo usa otro artículo');
			}
			$vistosCodigo[$codigo] = true;
		}
		$precio = decimalSeguro(isset($precios[$i]) ? $precios[$i] : 0, 2, 0);
		if ($precio < 0) {
			return array(false, 'El precio de ' . $etiqueta . ' no puede ser negativo');
		}
		$filas[] = array(
			'idvariante' => enteroSeguro(isset($ids[$i]) ? $ids[$i] : 0),
			'talla' => $talla,
			'color' => $color,
			'codigo' => $codigo,
			'stock_inicial' => cantidadSegura(isset($stocks[$i]) ? $stocks[$i] : 0, $permiteFraccion),
			'stock_minimo' => cantidadSegura(isset($minimos[$i]) ? $minimos[$i] : 0, $permiteFraccion),
			'precio_venta' => $precio
		);
	}
	return array(true, $filas);
}

$op = isset($_GET["op"]) ? $_GET["op"] : '';

switch ($op) {
	case 'guardaryeditar':
		requierePermiso(array('almacen'));

		// Validaciones de negocio
		if ($nombre === '') {
			echo "El nombre del artículo es obligatorio";
			break;
		}
		if (mb_strlen($nombre) > 100) {
			echo "El nombre no puede superar 100 caracteres";
			break;
		}
		if ($idcategoria <= 0) {
			echo "Selecciona una categoría válida";
			break;
		}
		if ($idunidad <= 0) {
			echo "Selecciona una unidad de medida válida";
			break;
		}
		if (mb_strlen($codigo) > 50) {
			echo "El código no puede superar 50 caracteres";
			break;
		}
		if ($codigo !== '' && $articulo->codigoEnUso($codigo, $idarticulo)) {
			echo "El código ya está registrado en otro artículo o presentación";
			break;
		}
		if ($articulo->existeNombre($nombre, $idarticulo)) {
			echo "Ya existe un artículo con ese nombre";
			break;
		}

		// Imagen: si se sube archivo se valida; si no, se conserva la actual.
		$hayArchivo = isset($_FILES['imagen']) && is_array($_FILES['imagen'])
			&& isset($_FILES['imagen']['error']) && !is_array($_FILES['imagen']['error'])
			&& (int)$_FILES['imagen']['error'] !== UPLOAD_ERR_NO_FILE;
		if ($hayArchivo) {
			list($okImg, $resImg) = guardarImagenSubida('imagen', '../files/articulos');
			if (!$okImg) {
				echo $resImg;
				break;
			}
			$imagen = $resImg;
		} else {
			$imagen = nombreArchivoSeguro(isset($_POST["imagenactual"]) ? $_POST["imagenactual"] : '');
		}

		// Presentaciones y precio por mayor: solo si el rubro los usa. Si no, se dejan intactos.
		$usaPresentaciones = negocioTiene('equivalencias') && isset($_POST['pres_enviadas']);
		$usaEscalas = negocioTiene('precio_mayor') && isset($_POST['escalas_enviadas']);
		$usaVariantes = Variante::activo() && isset($_POST['var_enviadas']);
		$usaTemporada = negocioTiene('temporada');
		$temporada = $usaTemporada ? Variante::textoCorto(limpiarCadena(isset($_POST['temporada']) ? $_POST['temporada'] : ''), 40) : '';
		$coleccion = $usaTemporada ? Variante::textoCorto(limpiarCadena(isset($_POST['coleccion']) ? $_POST['coleccion'] : ''), 40) : '';
		$variantes = array();
		if ($usaVariantes) {
			list($okVar, $resVar) = leerVariantesPost($articulo, $idarticulo, $codigo, $stockFraccion);
			if (!$okVar) { echo $resVar; break; }
			$variantes = $resVar;
		}
		$presentaciones = array();
		$escalas = array();
		if ($usaPresentaciones) {
			list($okPres, $resPres) = leerPresentacionesPost($articulo, $idarticulo, $codigo);
			if (!$okPres) { echo $resPres; break; }
			$presentaciones = $resPres;
		}
		if ($usaEscalas) {
			list($okEsc, $resEsc) = leerEscalasPost($stockFraccion);
			if (!$okEsc) { echo $resEsc; break; }
			$escalas = $resEsc;
		}

		$esNuevo = $idarticulo <= 0;
		$errorGuardado = '';
		$idGuardado = dbTransaccion(function () use ($articulo, $esNuevo, $idarticulo, $idcategoria, $idunidad, $codigo, $nombre, $stock, $stock_minimo, $precio_compra, $precio_venta, $descripcion, $imagen, $usaPresentaciones, $presentaciones, $usaEscalas, $escalas, $usaVariantes, $variantes, $usaTemporada, $temporada, $coleccion, &$errorGuardado) {
			if ($esNuevo) {
				$id = $articulo->insertarId($idcategoria, $idunidad, $codigo, $nombre, $stock, $stock_minimo, $precio_compra, $precio_venta, $descripcion, $imagen);
				if ($id <= 0) { $errorGuardado = "No se pudo registrar los datos"; return false; }
			} else {
				$id = $idarticulo;
				if (!$articulo->editar($id, $idcategoria, $idunidad, $codigo, $nombre, $stock, $stock_minimo, $precio_compra, $precio_venta, $descripcion, $imagen)) {
					$errorGuardado = "No se pudo actualizar los datos"; return false;
				}
				// Stock editado a mano: los lotes no pueden quedar por encima
				if (!Lote::ajustarAlStock($id)) {
					$errorGuardado = "No se pudo actualizar los lotes del artículo"; return false;
				}
			}
			if ($usaPresentaciones && !$articulo->guardarPresentaciones($id, $presentaciones)) {
				$errorGuardado = "No se pudieron guardar las presentaciones (revisa que los nombres no se repitan)"; return false;
			}
			if ($usaEscalas && !$articulo->guardarEscalas($id, $escalas)) {
				$errorGuardado = "No se pudieron guardar los precios por mayor"; return false;
			}
			if ($usaVariantes) {
				$resVar = Variante::sincronizar($id, $variantes);
				if ($resVar !== true) { $errorGuardado = $resVar; return false; }
			}
			// Con tallas/colores el stock del articulo es la suma de sus variantes, en cualquier rubro
			if (!Variante::recalcularArticulo($id) || !Lote::ajustarAlStock($id)) {
				$errorGuardado = "No se pudo actualizar el stock del artículo"; return false;
			}
			if ($usaTemporada && !$articulo->guardarTemporada($id, $temporada, $coleccion)) {
				$errorGuardado = "No se pudo guardar la temporada"; return false;
			}
			return $id;
		});

		if (!$idGuardado) {
			echo $errorGuardado !== '' ? $errorGuardado : "No se pudo guardar el artículo";
			break;
		}
		$extra = '';
		if ($usaPresentaciones) { $extra .= ' · ' . count($presentaciones) . ' presentación(es)'; }
		if ($usaEscalas) { $extra .= ' · ' . count($escalas) . ' precio(s) por mayor'; }
		if ($usaVariantes) { $extra .= ' · ' . count($variantes) . ' talla(s)/color(es)'; }
		if ($esNuevo) {
			registrarAuditoria('almacen', 'crear_articulo', 'Articulo creado: ' . $nombre . ($codigo !== '' ? ' (' . $codigo . ')' : '') . $extra);
			echo "Datos registrados correctamente";
		} else {
			registrarAuditoria('almacen', 'editar_articulo', 'Articulo #' . $idarticulo . ' editado: ' . $nombre . $extra);
			echo "Datos actualizados correctamente";
		}
		break;

	case 'desactivar':
		requierePermiso(array('almacen'));
		$rspta = ($idarticulo > 0) ? $articulo->desactivar($idarticulo) : false;
		if ($rspta) {
			registrarAuditoria('almacen', 'desactivar_articulo', 'Articulo #' . $idarticulo . ' desactivado');
		}
		echo $rspta ? "Datos desactivados correctamente" : "No se pudo desactivar los datos";
		break;

	case 'activar':
		requierePermiso(array('almacen'));
		$rspta = ($idarticulo > 0) ? $articulo->activar($idarticulo) : false;
		if ($rspta) {
			registrarAuditoria('almacen', 'activar_articulo', 'Articulo #' . $idarticulo . ' activado');
		}
		echo $rspta ? "Datos activados correctamente" : "No se pudo activar los datos";
		break;

	case 'mostrar':
		$rspta = $articulo->mostrar($idarticulo);
		if (is_array($rspta)) {
			$rspta["permite_fraccion"] = negocioTiene('fracciones') && $articulo->unidadPermiteFraccion($rspta["idunidad"]);
			$rspta["stock"] = round((float)$rspta["stock"], 3);
			$rspta["stock_minimo"] = round((float)$rspta["stock_minimo"], 3);
			$rspta["presentaciones"] = negocioTiene('equivalencias') ? $articulo->presentaciones($rspta["idarticulo"]) : array();
			$rspta["escalas"] = negocioTiene('precio_mayor') ? $articulo->escalas($rspta["idarticulo"]) : array();
			$rspta["lotes"] = Lote::activo() ? Lote::deArticulo($rspta["idarticulo"]) : array();
			$rspta["variantes"] = Variante::deArticulo($rspta["idarticulo"]);
			foreach ($rspta["variantes"] as &$vr) {
				$vr["talla"] = html_entity_decode((string)$vr["talla"], ENT_QUOTES, 'UTF-8');
				$vr["color"] = html_entity_decode((string)$vr["color"], ENT_QUOTES, 'UTF-8');
			}
			unset($vr);
			$rspta["precio_compra"] = number_format((float)$rspta["precio_compra"], 2, '.', '');
			$rspta["precio_venta"] = number_format((float)$rspta["precio_venta"], 2, '.', '');
		}
		header('Content-Type: application/json; charset=utf-8');
		echo json_encode($rspta, JSON_UNESCAPED_UNICODE);
		break;

	case 'catalogoEtiquetas':
		$rs = $articulo->listarActivosVenta();
		$lista = array();
		if ($rs) {
			while ($reg = $rs->fetch_object()) {
				$lista[] = array(
					'idarticulo' => (int)$reg->idarticulo,
					'nombre' => html_entity_decode((string)$reg->nombre, ENT_QUOTES, 'UTF-8'),
					'categoria' => html_entity_decode((string)$reg->categoria, ENT_QUOTES, 'UTF-8'),
					'codigo' => (string)$reg->codigo,
					'precio_venta' => round((float)$reg->precio_venta, 2),
					'stock' => round((float)$reg->stock, 3),
					'stock_minimo' => round((float)$reg->stock_minimo, 3)
				);
			}
		}
		// Cada talla/color tambien lleva su etiqueta con su codigo y precio propios
		$vars = dbAll(
			"SELECT v.idvariante, v.idarticulo, v.talla, v.color, v.codigo, v.stock, v.stock_minimo,
				IF(v.precio_venta>0, v.precio_venta, a.precio_venta) AS precio_venta, a.nombre, c.nombre AS categoria
			 FROM articulo_variante v
			 INNER JOIN articulo a ON a.idarticulo=v.idarticulo
			 LEFT JOIN categoria c ON c.idcategoria=a.idcategoria
			 WHERE v.condicion=1 AND a.condicion=1
			 ORDER BY a.nombre, v.orden, v.idvariante"
		);
		foreach ($vars as $v) {
			$lista[] = array(
				'idarticulo' => (int)$v['idarticulo'],
				'idvariante' => (int)$v['idvariante'],
				'nombre' => html_entity_decode((string)$v['nombre'] . ' ' . Variante::etiqueta($v['talla'], $v['color']), ENT_QUOTES, 'UTF-8'),
				'categoria' => html_entity_decode((string)$v['categoria'], ENT_QUOTES, 'UTF-8'),
				'codigo' => (string)$v['codigo'],
				'precio_venta' => round((float)$v['precio_venta'], 2),
				'stock' => round((float)$v['stock'], 3),
				'stock_minimo' => round((float)$v['stock_minimo'], 3)
			);
		}
		responderJson($lista);
		break;

	case 'listar':
		$rspta = $articulo->listar();
		$data = array();

		if ($rspta instanceof mysqli_result) {
			while ($reg = $rspta->fetch_object()) {
				$id = (int)$reg->idarticulo;
				$stockVal = round((float)$reg->stock, 3);
				$stockMin = round((float)$reg->stock_minimo, 3);
				if ($stockVal <= 0) {
					$stockHtml = '<span class="stock-pill stock-empty">' . formatearCantidad($stockVal) . '</span>';
				} elseif ($stockVal <= $stockMin) {
					$stockHtml = '<span class="stock-pill stock-low">' . formatearCantidad($stockVal) . '</span>';
				} else {
					$stockHtml = '<span class="stock-pill stock-ok">' . formatearCantidad($stockVal) . '</span>';
				}
				$img = nombreArchivoSeguro($reg->imagen);
				$srcImg = ($img !== '' ) ? '../files/articulos/' . e($img) : '../public/img/default-50x50.gif';

				$extraNombre = array();
				if ((int)$reg->variantes > 0) { $extraNombre[] = (int)$reg->variantes . ' talla(s)/color(es)'; }
				if (!empty($reg->temporada)) { $extraNombre[] = e($reg->temporada) . (!empty($reg->coleccion) ? ' · ' . e($reg->coleccion) : ''); }
				$data[] = array(
					"0" => ($reg->condicion)
						? '<button class="btn btn-warning btn-xs" title="Editar" onclick="mostrar(' . $id . ')"><i class="fa fa-pencil"></i></button> <button class="btn btn-danger btn-xs" title="Desactivar" onclick="desactivar(' . $id . ')"><i class="fa fa-ban"></i></button>'
						: '<button class="btn btn-warning btn-xs" title="Editar" onclick="mostrar(' . $id . ')"><i class="fa fa-pencil"></i></button> <button class="btn btn-success btn-xs" title="Activar" onclick="activar(' . $id . ')"><i class="fa fa-check"></i></button>',
					"1" => e($reg->nombre) . ($extraNombre ? '<br><small class="text-soft">' . implode(' · ', $extraNombre) . '</small>' : ''),
					"2" => e($reg->categoria),
					"3" => e($reg->abreviatura),
					"4" => e($reg->codigo),
					"5" => $stockHtml,
					"6" => formatearCantidad($stockMin),
					"7" => formatearMoneda($reg->precio_compra),
					"8" => formatearMoneda($reg->precio_venta),
					"9" => "<img src='" . $srcImg . "' height='50px' width='50px'>",
					"10" => e($reg->descripcion),
					"11" => ($reg->condicion) ? '<span class="label bg-green">Activado</span>' : '<span class="label bg-red">Desactivado</span>'
				);
			}
		}
		$results = array(
			"sEcho" => 1,
			"iTotalRecords" => count($data),
			"iTotalDisplayRecords" => count($data),
			"aaData" => $data
		);
		header('Content-Type: application/json; charset=utf-8');
		echo json_encode($results, JSON_UNESCAPED_UNICODE);
		break;

	case 'selectUnidad':
		require_once "../modelos/Unidad.php";
		$unidad = new Unidad();
		$rspta = $unidad->select();
		if ($rspta instanceof mysqli_result) {
			while ($reg = $rspta->fetch_object()) {
				echo '<option value="' . (int)$reg->idunidad . '" data-fraccion="' . ((int)$reg->permite_fraccion === 1 ? '1' : '0') . '" data-abrev="' . e($reg->abreviatura) . '">' . e($reg->nombre) . ' (' . e($reg->abreviatura) . ')</option>';
			}
		}
		break;

	case 'selectCategoria':
		require_once "../modelos/Categoria.php";
		$categoria = new Categoria();
		$rspta = $categoria->select();
		if ($rspta instanceof mysqli_result) {
			while ($reg = $rspta->fetch_object()) {
				echo '<option value="' . (int)$reg->idcategoria . '">' . e($reg->nombre) . '</option>';
			}
		}
		break;
}

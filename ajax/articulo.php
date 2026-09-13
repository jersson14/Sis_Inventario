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
$stock         = (int)round(decimalSeguro(isset($_POST["stock"]) ? $_POST["stock"] : 0, 3, 0));
$stock_minimo  = (int)round(decimalSeguro(isset($_POST["stock_minimo"]) ? $_POST["stock_minimo"] : 1, 3, 1));
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
		if ($codigo !== '' && $articulo->existeCodigo($codigo, $idarticulo)) {
			echo "El código ya está registrado en otro artículo";
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

		if ($idarticulo <= 0) {
			$rspta = $articulo->insertar($idcategoria, $idunidad, $codigo, $nombre, $stock, $stock_minimo, $precio_compra, $precio_venta, $descripcion, $imagen);
			if ($rspta) {
				registrarAuditoria('almacen', 'crear_articulo', 'Articulo creado: ' . $nombre . ($codigo !== '' ? ' (' . $codigo . ')' : ''));
			}
			echo $rspta ? "Datos registrados correctamente" : "No se pudo registrar los datos";
		} else {
			$rspta = $articulo->editar($idarticulo, $idcategoria, $idunidad, $codigo, $nombre, $stock, $stock_minimo, $precio_compra, $precio_venta, $descripcion, $imagen);
			if ($rspta) {
				registrarAuditoria('almacen', 'editar_articulo', 'Articulo #' . $idarticulo . ' editado: ' . $nombre);
			}
			echo $rspta ? "Datos actualizados correctamente" : "No se pudo actualizar los datos";
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
			$rspta["stock"] = (int)round((float)$rspta["stock"]);
			$rspta["stock_minimo"] = (int)round((float)$rspta["stock_minimo"]);
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
					'stock' => (int)round((float)$reg->stock),
					'stock_minimo' => (int)round((float)$reg->stock_minimo)
				);
			}
		}
		responderJson($lista);
		break;

	case 'listar':
		$rspta = $articulo->listar();
		$data = array();

		if ($rspta instanceof mysqli_result) {
			while ($reg = $rspta->fetch_object()) {
				$id = (int)$reg->idarticulo;
				$stockVal = (int)round((float)$reg->stock);
				$stockMin = (int)round((float)$reg->stock_minimo);
				if ($stockVal <= 0) {
					$stockHtml = '<span class="stock-pill stock-empty">' . $stockVal . '</span>';
				} elseif ($stockVal <= $stockMin) {
					$stockHtml = '<span class="stock-pill stock-low">' . $stockVal . '</span>';
				} else {
					$stockHtml = '<span class="stock-pill stock-ok">' . $stockVal . '</span>';
				}
				$img = nombreArchivoSeguro($reg->imagen);
				$srcImg = ($img !== '' ) ? '../files/articulos/' . e($img) : '../public/img/default-50x50.gif';

				$data[] = array(
					"0" => ($reg->condicion)
						? '<button class="btn btn-warning btn-xs" onclick="mostrar(' . $id . ')"><i class="fa fa-pencil"></i></button> <button class="btn btn-danger btn-xs" onclick="desactivar(' . $id . ')"><i class="fa fa-close"></i></button>'
						: '<button class="btn btn-warning btn-xs" onclick="mostrar(' . $id . ')"><i class="fa fa-pencil"></i></button> <button class="btn btn-primary btn-xs" onclick="activar(' . $id . ')"><i class="fa fa-check"></i></button>',
					"1" => e($reg->nombre),
					"2" => e($reg->categoria),
					"3" => e($reg->abreviatura),
					"4" => e($reg->codigo),
					"5" => $stockHtml,
					"6" => $stockMin,
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
				echo '<option value="' . (int)$reg->idunidad . '">' . e($reg->nombre) . ' (' . e($reg->abreviatura) . ')</option>';
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
